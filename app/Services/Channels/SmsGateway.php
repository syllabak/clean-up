<?php
declare(strict_types=1);
namespace App\Services\Channels;

use App\Core\Env;

/** SMS via API configurable. SMS_DRIVER : log (fichier) ou http (POST JSON défini par SMS_BODY_TEMPLATE). */
final class SmsGateway
{
    public static function send(string $to, string $message): void
    {
        $driver = Env::get('SMS_DRIVER', 'log');
        if ($driver === 'log') {
            file_put_contents(BASE_PATH . '/storage/logs/sms.log', sprintf("[%s] TO:%s | %s\n", date('c'), $to, $message), FILE_APPEND | LOCK_EX);
            return;
        }
        $url = Env::get('SMS_API_URL', '');
        if ($url === '') throw new \RuntimeException('SMS_API_URL non configurée.');
        $tpl = Env::get('SMS_BODY_TEMPLATE', '{"to":"{to}","from":"{sender}","text":"{message}"}');
        $esc = fn(string $s) => substr(json_encode($s, JSON_UNESCAPED_UNICODE), 1, -1);   // échappement JSON sûr
        $body = strtr($tpl, ['{to}' => $esc($to), '{message}' => $esc($message), '{sender}' => $esc(Env::get('SMS_SENDER', ''))]);
        $headers = "Content-Type: application/json\r\n";
        if (Env::get('SMS_API_TOKEN', '') !== '') $headers .= 'Authorization: Bearer ' . Env::get('SMS_API_TOKEN') . "\r\n";
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => $headers, 'content' => $body, 'timeout' => 10, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $status = (int) $m[1];
        if ($res === false || $status < 200 || $status >= 300) throw new \RuntimeException("API SMS : échec (HTTP $status)");
    }
}
