<?php
declare(strict_types=1);
namespace App\Services\Channels;

use App\Services\ChannelConfig;

/** SMS via API configurable. SMS_DRIVER : log (fichier) ou http (POST JSON défini par SMS_BODY_TEMPLATE). */
final class SmsGateway
{
    public static function send(string $to, string $message): void
    {
        $driver = ChannelConfig::get('sms_driver', 'SMS_DRIVER', 'log');
        if ($driver === 'log') {
            file_put_contents(BASE_PATH . '/storage/logs/sms.log', sprintf("[%s] TO:%s | %s\n", date('c'), $to, $message), FILE_APPEND | LOCK_EX);
            return;
        }
        $url = ChannelConfig::get('sms_api_url', 'SMS_API_URL');
        if ($url === '' || !preg_match('#^https?://#i', $url)) throw new \RuntimeException('Adresse de l\'API SMS non configurée ou invalide.');
        $tpl = ChannelConfig::get('sms_body_template', 'SMS_BODY_TEMPLATE', '{"to":"{to}","from":"{sender}","text":"{message}"}');
        $esc = fn(string $s) => substr(json_encode($s, JSON_UNESCAPED_UNICODE), 1, -1);   // échappement JSON sûr
        $body = strtr($tpl, ['{to}' => $esc($to), '{message}' => $esc($message), '{sender}' => $esc(ChannelConfig::get('sms_sender', 'SMS_SENDER'))]);
        $headers = "Content-Type: application/json\r\n";
        $token = ChannelConfig::get('sms_token', 'SMS_API_TOKEN');
        if ($token !== '') $headers .= 'Authorization: Bearer ' . $token . "\r\n";
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => $headers, 'content' => $body, 'timeout' => 10, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $status = (int) $m[1];
        if ($res === false || $status < 200 || $status >= 300) throw new \RuntimeException("API SMS : échec (HTTP $status)");
    }
}
