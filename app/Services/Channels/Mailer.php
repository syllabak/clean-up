<?php
declare(strict_types=1);
namespace App\Services\Channels;

use App\Core\Env;

/** Envoi d'emails. MAIL_DRIVER : log (fichier), mail (fonction PHP mail()), smtp (client SMTP intégré, SSL/STARTTLS + AUTH LOGIN). */
final class Mailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        $driver = Env::get('MAIL_DRIVER', 'log');
        $from = Env::get('MAIL_FROM_ADDRESS', 'noreply@localhost');
        $fromName = Env::get('MAIL_FROM_NAME', 'Net\'Express');
        if ($driver === 'log') {
            file_put_contents(BASE_PATH . '/storage/logs/mail.log', sprintf("[%s] TO:%s | %s\n%s\n---\n", date('c'), $to, $subject, $body), FILE_APPEND | LOCK_EX);
            return;
        }
        if (preg_match('/[\r\n]/', $to . $subject . $from . $fromName)) throw new \RuntimeException('En-tête email invalide.');
        $headers = [
            'From' => self::encodeName($fromName) . " <$from>", 'To' => $to, 'Subject' => '=?UTF-8?B?' . base64_encode($subject) . '?=',
            'Date' => date('r'), 'Message-ID' => '<' . bin2hex(random_bytes(10)) . '@' . (parse_url(Env::get('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost') . '>',
            'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8', 'Content-Transfer-Encoding' => 'base64',
        ];
        $encoded = chunk_split(base64_encode($body));
        if ($driver === 'mail') {
            $h = $headers; unset($h['To'], $h['Subject']);
            $ok = @mail($to, $headers['Subject'], $encoded, implode("\r\n", array_map(fn($k, $v) => "$k: $v", array_keys($h), $h)));
            if (!$ok) throw new \RuntimeException('mail() a échoué.');
            return;
        }
        self::smtp($to, $from, $headers, $encoded);
    }

    private static function encodeName(string $n): string { return '=?UTF-8?B?' . base64_encode($n) . '?='; }

    private static function smtp(string $to, string $from, array $headers, string $body): void
    {
        $host = Env::get('MAIL_HOST', ''); $port = (int) Env::get('MAIL_PORT', '587'); $enc = strtolower(Env::get('MAIL_ENCRYPTION', 'tls'));
        if ($host === '') throw new \RuntimeException('MAIL_HOST non configuré.');
        $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 10);
        if (!$fp) throw new \RuntimeException("Connexion SMTP impossible ($errstr)");
        stream_set_timeout($fp, 10);
        $read = function () use ($fp): string {
            $out = '';
            while (($l = fgets($fp, 515)) !== false) { $out .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
            return $out;
        };
        $cmd = function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $ok, true)) throw new \RuntimeException('SMTP : ' . trim($r));
            return $r;
        };
        try {
            $r = $read();
            if ((int) substr($r, 0, 3) !== 220) throw new \RuntimeException('SMTP : ' . trim($r));
            $name = parse_url(Env::get('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
            $cmd("EHLO $name", [250]);
            if ($enc === 'tls') {
                $cmd('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new \RuntimeException('STARTTLS refusé.');
                $cmd("EHLO $name", [250]);
            }
            if (Env::get('MAIL_USERNAME', '') !== '') {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode(Env::get('MAIL_USERNAME')), [334]);
                $cmd(base64_encode(Env::get('MAIL_PASSWORD', '')), [235]);
            }
            $cmd("MAIL FROM:<$from>", [250]);
            $cmd("RCPT TO:<$to>", [250, 251]);
            $cmd('DATA', [354]);
            $msg = implode("\r\n", array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers)) . "\r\n\r\n" . $body;
            $msg = preg_replace('/^\./m', '..', $msg);
            fwrite($fp, $msg . "\r\n.\r\n");
            $r = $read();
            if ((int) substr($r, 0, 3) !== 250) throw new \RuntimeException('SMTP : ' . trim($r));
            @fwrite($fp, "QUIT\r\n");
        } finally {
            fclose($fp);
        }
    }
}
