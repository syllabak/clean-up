<?php
declare(strict_types=1);
namespace App\Services\Channels;

/** Appel HTTP minimal (sans dépendance) pour les API de messagerie et de publicité. */
final class Http
{
    /** @return array{0:int,1:string} statut HTTP et corps de la réponse. @throws \RuntimeException si la connexion échoue */
    public static function postJson(string $url, string $json, array $headers = [], int $timeout = 10): array
    {
        if (!preg_match('#^https?://#i', $url)) throw new \RuntimeException("Adresse d'API invalide.");
        $h = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $h), 'content' => $json, 'timeout' => $timeout, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $status = (int) $m[1];
        if ($res === false && $status === 0) throw new \RuntimeException('Connexion à l\'API impossible.');
        return [$status, (string) $res];
    }

    /** Message d'erreur lisible tiré d'une réponse d'API (champ error.message, message ou début du corps). */
    public static function errorText(int $status, string $body): string
    {
        $j = json_decode($body, true);
        $m = is_array($j) ? ($j['error']['message'] ?? $j['message'] ?? $j['error'] ?? null) : null;
        return "HTTP $status" . ($m && is_string($m) ? ' : ' . mb_substr($m, 0, 300) : ($body !== '' ? ' : ' . mb_substr(strip_tags($body), 0, 200) : ''));
    }
}
