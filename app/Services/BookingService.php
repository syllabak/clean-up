<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Database, Env, Validator, ValidationException};
use App\Repositories\BookingRepository;

final class BookingService
{
    /** Transitions autorisées. Une réservation terminée ou annulée est définitivement close. */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'assigned', 'cancelled'],
        'confirmed' => ['assigned', 'cancelled', 'pending'],
        'assigned' => ['en_route', 'confirmed', 'cancelled'],
        'en_route' => ['in_progress', 'assigned', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    private PricingService $pricing;
    private AvailabilityService $availability;
    private NotificationService $notifier;
    private BookingRepository $repo;

    public function __construct()
    {
        $this->pricing = new PricingService();
        $this->availability = new AvailabilityService();
        $this->notifier = new NotificationService();
        $this->repo = new BookingRepository();
    }

    /**
     * Crée une réservation. Toute la validation (prix, durée, créneau) est refaite ici, côté serveur.
     * Le contrôle de disponibilité et l'insertion se font dans la même transaction verrouillée :
     * deux clients qui visent le même créneau en même temps ne peuvent pas tous deux réussir.
     * @throws ValidationException|SlotUnavailableException
     */
    public function create(array $in): array
    {
        $errors = Validator::check($in, [
            'first_name' => 'required|maxlen:100', 'last_name' => 'required|maxlen:100', 'phone' => 'required|phone', 'email' => 'email|maxlen:190',
            'neighborhood_id' => 'required|int', 'address_text' => 'required|minlen:5|maxlen:500', 'instructions' => 'maxlen:1000',
            'date' => 'required|date', 'start' => 'required',
        ], ['first_name' => 'Le prénom', 'last_name' => 'Le nom', 'phone' => 'Le téléphone', 'email' => "L'email", 'neighborhood_id' => 'Le quartier',
            'address_text' => "L'adresse", 'instructions' => 'Les instructions', 'date' => 'La date', 'start' => "L'heure"]);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($in['start'] ?? ''))) $errors['start'] = "L'heure est invalide.";
        if ($errors) throw new ValidationException($errors);

        $serviceId = (int) ($in['service_id'] ?? 0);
        $nid = (int) $in['neighborhood_id'];
        $quote = $this->pricing->quote($serviceId, $in, $nid);   // lève ValidationException si les choix sont invalides
        if (!$this->availability->neighborhood($nid)) throw new ValidationException(['neighborhood_id' => "Ce quartier n'est pas desservi."]);

        $booking = Database::transaction(function () use ($in, $quote, $serviceId, $nid) {
            // Verrouille les équipes candidates dans un ordre constant (évite les interblocages sous MySQL).
            foreach ($this->availability->eligibleTeams($serviceId, $nid) as $t) Database::lockRow('teams', (int) $t['id']);

            $slot = $this->availability->findSlot($serviceId, $nid, $quote['duration'], $in['date'], $in['start']);
            if (!$slot) throw new SlotUnavailableException("Ce créneau vient d'être pris ou n'est plus disponible. Merci d'en choisir un autre.");

            $cs = new CustomerService();
            $customerId = $cs->findOrCreate(trim($in['first_name']), trim($in['last_name']), $in['phone'], trim((string) ($in['email'] ?? '')) ?: null);
            $addressId = $cs->addAddress($customerId, $nid, trim($in['address_text']), trim((string) ($in['instructions'] ?? '')) ?: null);

            $id = Database::insert('bookings', [
                'company_id' => Env::companyId(), 'reference' => $this->newReference(), 'customer_id' => $customerId, 'address_id' => $addressId,
                'neighborhood_id' => $nid, 'service_id' => $serviceId, 'address_text' => trim($in['address_text']),
                'instructions' => trim((string) ($in['instructions'] ?? '')) ?: null, 'scheduled_date' => $in['date'], 'start_time' => $slot['start'],
                'end_time' => $slot['end'], 'duration_minutes' => $quote['duration'], 'team_id' => $slot['team_id'], 'status' => 'pending',
                'subtotal' => $quote['subtotal'], 'travel_fee' => $quote['travel_fee'], 'total' => $quote['total'],
                'source' => $in['source'] ?? 'web', 'created_at' => now(),
            ]);
            Database::insert('booking_items', [
                'booking_id' => $id, 'service_id' => $serviceId, 'name' => $quote['service']['name'], 'formula_name' => $quote['formula']['name'] ?? null,
                'details' => json_encode($quote['details'], JSON_UNESCAPED_UNICODE), 'quantity' => $quote['quantity'], 'unit_price' => $quote['unit_price'],
                'total' => $quote['subtotal'], 'duration_minutes' => $quote['duration'],
            ]);
            $this->history($id, null, 'pending', null, 'Réservation créée');
            return $id;
        });

        Audit::log('booking.create', 'booking', $booking, ['source' => $in['source'] ?? 'web'], null);
        // Après validation de la transaction : une panne de notification ne remet rien en cause.
        $this->notifier->dispatch('booking.created', $booking);
        if (setting('auto_assign', '0') === '1') (new AssignmentService())->autoAssign($booking);
        return $this->repo->find($booking);
    }

    public function changeStatus(int $id, string $to, ?int $userId = null, ?string $note = null): void
    {
        $b = $this->repo->find($id) ?? throw new \RuntimeException('Réservation introuvable.');
        $from = $b['status'];
        if ($from === $to) return;
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \RuntimeException('Transition impossible : ' . status_label($from) . ' → ' . status_label($to) . '.');
        }
        $upd = ['status' => $to, 'updated_at' => now()];
        if ($to === 'cancelled') $upd['cancel_reason'] = $note ? mb_substr($note, 0, 255) : null;
        Database::transaction(function () use ($id, $upd, $from, $to, $userId, $note) {
            Database::update('bookings', $id, $upd);
            if ($to === 'cancelled') Database::exec('UPDATE booking_assignments SET active = 0 WHERE booking_id = ?', [$id]);
            $this->history($id, $from, $to, $userId, $note);
        });
        Audit::log('booking.status', 'booking', $id, ['from' => $from, 'to' => $to], $userId);
        $event = match ($to) { 'confirmed' => 'booking.confirmed', 'cancelled' => 'booking.cancelled', 'completed' => 'booking.completed', 'assigned' => null, default => 'booking.status_changed' };
        if ($event) $this->notifier->dispatch($event, $id);
    }

    public function cancel(int $id, ?int $userId, string $reason = ''): void { $this->changeStatus($id, 'cancelled', $userId, $reason ?: null); }

    /** Déplace une réservation vers un nouveau créneau, après vérification des disponibilités. */
    public function reschedule(int $id, string $date, string $start, ?int $userId = null): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $start)) throw new ValidationException(['date' => 'Date ou heure invalide.']);
        Database::transaction(function () use ($id, $date, $start, $userId) {
            $b = $this->repo->find($id) ?? throw new \RuntimeException('Réservation introuvable.');
            if (in_array($b['status'], ['completed', 'cancelled', 'in_progress'], true)) throw new \RuntimeException('Cette réservation ne peut plus être déplacée.');
            foreach ($this->availability->eligibleTeams((int) $b['service_id'], (int) $b['neighborhood_id']) as $t) Database::lockRow('teams', (int) $t['id']);
            // On privilégie l'équipe déjà affectée ; sinon n'importe quelle équipe disponible.
            $slot = null;
            if ($b['team_id']) $slot = $this->availability->findSlot((int) $b['service_id'], (int) $b['neighborhood_id'], (int) $b['duration_minutes'], $date, $start, $id, (int) $b['team_id']);
            $slot ??= $this->availability->findSlot((int) $b['service_id'], (int) $b['neighborhood_id'], (int) $b['duration_minutes'], $date, $start, $id);
            if (!$slot) throw new SlotUnavailableException("Ce créneau n'est pas disponible.");
            $upd = ['scheduled_date' => $date, 'start_time' => $slot['start'], 'end_time' => $slot['end'], 'team_id' => $slot['team_id'], 'reminder_sent_at' => null, 'updated_at' => now()];
            $teamChanged = (int) $b['team_id'] !== (int) $slot['team_id'];
            if ($teamChanged) {
                Database::exec('UPDATE booking_assignments SET active = 0 WHERE booking_id = ?', [$id]);
                if ($b['status'] === 'assigned') { $upd['status'] = 'confirmed'; $this->history($id, 'assigned', 'confirmed', $userId, "Équipe modifiée suite au déplacement"); }
            }
            Database::update('bookings', $id, $upd);
            Database::insert('booking_notes', ['booking_id' => $id, 'user_id' => $userId, 'note' => "Déplacée du {$b['scheduled_date']} {$b['start_time']} au $date {$slot['start']}", 'created_at' => now()]);
        });
        Audit::log('booking.reschedule', 'booking', $id, ['date' => $date, 'start' => $start], $userId);
        $this->notifier->dispatch('booking.rescheduled', $id);
    }

    public function addNote(int $id, ?int $userId, string $note): void
    {
        $note = trim($note);
        if ($note === '') return;
        Database::insert('booking_notes', ['booking_id' => $id, 'user_id' => $userId, 'note' => mb_substr($note, 0, 2000), 'created_at' => now()]);
        Audit::log('booking.note', 'booking', $id, [], $userId);
    }

    public function history(int $id, ?string $from, string $to, ?int $userId, ?string $note = null): void
    {
        Database::insert('booking_status_history', ['booking_id' => $id, 'from_status' => $from, 'to_status' => $to, 'user_id' => $userId, 'note' => $note ? mb_substr($note, 0, 255) : null, 'created_at' => now()]);
    }

    private function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $r = 'NX-' . date('ymd') . '-';
            for ($i = 0; $i < 4; $i++) $r .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        } while (Database::val('SELECT id FROM bookings WHERE reference = ?', [$r]));
        return $r;
    }
}
