<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Database, Env};
use App\Services\Channels\{Mailer, SmsGateway, WhatsAppGateway};

/**
 * Point d'entrée unique des notifications. Une panne email/SMS n'interrompt jamais le flux métier :
 * toute erreur est capturée, journalisée puis relancée plus tard (bin/queue.php ou /cron/run).
 */
final class NotificationService
{
    public const EVENTS = [
        'booking.created' => 'Réservation enregistrée', 'booking.confirmed' => 'Réservation confirmée', 'booking.rescheduled' => 'Date ou heure modifiée',
        'booking.assigned' => 'Équipe affectée', 'booking.reminder' => 'Rappel avant intervention', 'booking.cancelled' => 'Annulation',
        'booking.status_changed' => 'Changement de statut', 'booking.completed' => 'Intervention terminée',
    ];

    public static function toggleKey(string $event, string $channel): string { return 'notif_' . str_replace('.', '_', $event) . '_' . $channel; }
    public static function enabled(string $event, string $channel): bool { return setting(self::toggleKey($event, $channel), '1') === '1'; }

    public function dispatch(string $event, int $bookingId): void
    {
        try {
            $b = (new \App\Repositories\BookingRepository())->find($bookingId);
            if (!$b) return;
            $vars = $this->vars($b);
            $templates = Database::all('SELECT * FROM notifications WHERE company_id = ? AND event = ? AND active = 1', [Env::companyId(), $event]);
            $prefer = self::preferEmail();
            $chosen = $prefer ? self::chooseClientChannel($event, $b, $templates) : null;
            foreach ($templates as $t) {
                if (!self::enabled($event, $t['channel'])) continue;
                // Le client reçoit un seul message : par email s'il en a donné un, sinon par SMS (WhatsApp reste un canal à part, actif seulement avec l'API).
                if ($prefer && $t['audience'] === 'client' && in_array($t['channel'], ['email', 'sms'], true) && $t['channel'] !== $chosen) continue;
                if ($t['channel'] === 'whatsapp' && !WhatsAppGateway::isCloud()) continue;   // sans API, WhatsApp ne peut pas partir tout seul
                $to = $this->recipient($t['audience'], $t['channel'], $b);
                if (!$to) continue;
                $logId = Database::insert('notification_logs', [
                    'company_id' => Env::companyId(), 'event' => $event, 'audience' => $t['audience'], 'channel' => $t['channel'], 'recipient' => $to,
                    'subject' => $t['subject'] ? strtr($t['subject'], $vars) : null, 'content' => strtr($t['body'], $vars),
                    'template_id' => $t['id'], 'booking_id' => $bookingId, 'status' => 'pending', 'attempts' => 0, 'created_at' => now(),
                ]);
                $this->attempt($logId);
            }
        } catch (\Throwable $e) {
            error_log('Notification (' . $event . '): ' . $e->getMessage());
        }
    }

    /** Priorité à l'email pour écrire au client (réglage, activé par défaut). Désactivé : email et SMS partent tous les deux, comme avant. */
    public static function preferEmail(): bool { return setting('notif_prefer_email', '1') === '1'; }

    /**
     * Canal retenu pour écrire au client au sujet d'un événement : « email » s'il a donné une adresse valide et que ce canal est actif,
     * sinon « sms » s'il a un numéro, sinon null. @param ?array $templates modèles déjà chargés (sinon relus)
     */
    public static function chooseClientChannel(string $event, array $b, ?array $templates = null): ?string
    {
        $templates ??= Database::all('SELECT * FROM notifications WHERE company_id = ? AND event = ? AND active = 1', [Env::companyId(), $event]);
        $has = fn(string $ch) => (bool) array_filter($templates, fn($t) => $t['audience'] === 'client' && $t['channel'] === $ch) && self::enabled($event, $ch);
        if ($has('email') && filter_var($b['customer_email'] ?? '', FILTER_VALIDATE_EMAIL)) return 'email';
        if ($has('sms') && !empty($b['customer_phone'])) return 'sms';
        return null;
    }

    /** Tente l'envoi d'une ligne du journal. Ne lève jamais d'exception. */
    public function attempt(int $logId): bool
    {
        $log = Database::one('SELECT * FROM notification_logs WHERE id = ?', [$logId]);
        if (!$log || $log['status'] === 'sent') return false;
        $attempts = (int) $log['attempts'] + 1;
        try {
            match ($log['channel']) {
                'sms' => SmsGateway::send($log['recipient'], $log['content']),
                'whatsapp' => WhatsAppGateway::send($log['recipient'], $log['content'], $this->whatsappVars($log)),
                default => Mailer::send($log['recipient'], (string) $log['subject'], $log['content']),
            };
            Database::update('notification_logs', $logId, ['status' => 'sent', 'attempts' => $attempts, 'error' => null, 'sent_at' => now(), 'next_attempt_at' => null]);
            return true;
        } catch (\Throwable $e) {
            $max = (int) setting('notif_max_attempts', '4');
            $next = $attempts < $max ? gmdate('Y-m-d H:i:s', time() + (int) setting('notif_retry_minutes', '10') * 60 * $attempts) : null;
            Database::update('notification_logs', $logId, ['status' => 'failed', 'attempts' => $attempts, 'error' => mb_substr($e->getMessage(), 0, 500), 'next_attempt_at' => $next]);
            if ($log['channel'] === 'email' && $log['audience'] === 'client' && self::preferEmail() && $this->fallbackToSms($log, $e->getMessage())) {
                Database::update('notification_logs', $logId, ['next_attempt_at' => null, 'error' => mb_substr('Email impossible, SMS envoyé à la place : ' . $e->getMessage(), 0, 500)]);
            }
            return false;
        }
    }

    /** L'email n'a pas pu partir : le client est prévenu par SMS (une seule fois par événement), pour qu'il ne reste jamais sans nouvelle. */
    private function fallbackToSms(array $log, string $reason): bool
    {
        try {
            if (empty($log['booking_id'])) return false;
            $b = (new \App\Repositories\BookingRepository())->find((int) $log['booking_id']);
            if (!$b || empty($b['customer_phone']) || !self::enabled($log['event'], 'sms')) return false;
            $t = Database::one("SELECT * FROM notifications WHERE company_id = ? AND event = ? AND audience = 'client' AND channel = 'sms' AND active = 1", [Env::companyId(), $log['event']]);
            if (!$t) return false;
            if (Database::val("SELECT id FROM notification_logs WHERE booking_id = ? AND event = ? AND audience = 'client' AND channel = 'sms' AND created_at >= ?", [(int) $log['booking_id'], $log['event'], $log['created_at']])) return true;   // déjà fait
            $vars = $this->vars($b);
            $id = Database::insert('notification_logs', ['company_id' => Env::companyId(), 'event' => $log['event'], 'audience' => 'client', 'channel' => 'sms', 'recipient' => $b['customer_phone'],
                'subject' => null, 'content' => strtr($t['body'], $vars), 'template_id' => $t['id'], 'booking_id' => (int) $log['booking_id'], 'status' => 'pending', 'attempts' => 0, 'created_at' => now()]);
            $this->attempt($id);
            return true;
        } catch (\Throwable $e) { error_log('Repli SMS: ' . $e->getMessage()); return false; }
    }

    /** Relance les envois échoués dont l'heure est venue. */
    public function retryDue(int $limit = 50): int
    {
        $rows = Database::all("SELECT id FROM notification_logs WHERE status = 'failed' AND attempts < ? AND next_attempt_at IS NOT NULL AND next_attempt_at <= ? ORDER BY id LIMIT $limit", [(int) setting('notif_max_attempts', '4'), now()]);
        $ok = 0;
        foreach ($rows as $r) if ($this->attempt((int) $r['id'])) $ok++;
        return $ok;
    }

    /** Relance manuelle depuis l'administration (ignore le délai, respecte la limite de tentatives sauf demande explicite). */
    public function retryNow(int $logId, bool $force = false): bool
    {
        $log = Database::one('SELECT * FROM notification_logs WHERE id = ?', [$logId]);
        if (!$log || $log['status'] === 'sent') return false;
        if ((int) $log['attempts'] >= (int) setting('notif_max_attempts', '4') && !$force) return false;
        return $this->attempt($logId);
    }

    /** Envoie les rappels pour les interventions des prochaines heures (une seule fois par réservation). */
    public function sendReminders(): int
    {
        $hours = (int) setting('reminder_hours_before', '24');
        $limit = date('Y-m-d H:i', time() + $hours * 3600);
        $rows = Database::all("SELECT id, scheduled_date, start_time FROM bookings WHERE status IN ('pending','confirmed') AND reminder_sent_at IS NULL AND scheduled_date >= ? AND scheduled_date <= ?", [date('Y-m-d'), date('Y-m-d', time() + $hours * 3600)]);
        $n = 0;
        foreach ($rows as $r) {
            $when = $r['scheduled_date'] . ' ' . $r['start_time'];
            if ($when > $limit || $when < date('Y-m-d H:i')) continue;
            Database::update('bookings', (int) $r['id'], ['reminder_sent_at' => now()]);
            $this->dispatch('booking.reminder', (int) $r['id']);
            $n++;
        }
        return $n;
    }

    /** Variables des modèles WhatsApp de Meta (rechargées depuis la réservation). */
    private function whatsappVars(array $log): array
    {
        $b = !empty($log['booking_id']) ? (new \App\Repositories\BookingRepository())->find((int) $log['booking_id']) : null;
        return ($b ? $this->vars($b) : []) + ['{message}' => $log['content']];
    }

    private function recipient(string $audience, string $channel, array $b): ?string
    {
        if ($audience === 'admin') {
            $v = $channel !== 'email' ? setting('admin_notification_phone', '') : setting('admin_notification_email', '');
            return $v !== '' && $v !== null ? ($channel !== 'email' ? normalize_phone($v) : $v) : null;
        }
        if ($channel !== 'email') return $b['customer_phone'] ?: null;
        return filter_var($b['customer_email'] ?? '', FILTER_VALIDATE_EMAIL) ? $b['customer_email'] : null;
    }

    private function vars(array $b): array
    {
        return [
            '{client}' => trim($b['customer_first_name'] . ' ' . $b['customer_last_name']), '{telephone_client}' => $b['customer_phone'],
            '{reference}' => $b['reference'], '{service}' => $b['service_name'], '{date}' => fmt_date($b['scheduled_date']), '{heure}' => $b['start_time'],
            '{adresse}' => trim($b['address_text'] . ' (' . $b['neighborhood_name'] . ')'), '{total}' => money((int) $b['total']),
            '{equipe}' => $b['team_name'] ?? 'notre équipe', '{statut}' => status_label($b['status']),
            '{entreprise}' => setting('company_name', ''), '{telephone}' => setting('company_phone', ''),
            '{lien_suivi}' => url('/suivi' . (!empty($b['track_token']) ? '/' . $b['reference'] . '/' . $b['track_token'] : '')),
        ];
    }
}
