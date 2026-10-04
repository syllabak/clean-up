<?php
declare(strict_types=1);
namespace App\Core;

final class Validator
{
    /** Règles : required, email, phone, int, min:N, max:N, minlen:N, maxlen:N, in:a,b, date */
    public static function check(array $data, array $rules, array $labels = []): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleStr) {
            $val = $data[$field] ?? null;
            $label = $labels[$field] ?? $field;
            $str = is_scalar($val) ? trim((string) $val) : '';
            $rs = explode('|', $ruleStr);
            $required = in_array('required', $rs, true);
            if ($str === '') { if ($required) $errors[$field] = "$label est obligatoire."; continue; }
            foreach ($rs as $r) {
                [$name, $arg] = array_pad(explode(':', $r, 2), 2, null);
                $bad = match ($name) {
                    'email' => !filter_var($str, FILTER_VALIDATE_EMAIL) ? "$label n'est pas une adresse email valide." : null,
                    'phone' => !preg_match('/^\d{9,15}$/', normalize_phone($str)) ? "$label n'est pas un numéro valide." : null,
                    'int' => !preg_match('/^-?\d+$/', $str) ? "$label doit être un nombre entier." : null,
                    'min' => (float) $str < (float) $arg ? "$label doit être au moins $arg." : null,
                    'max' => (float) $str > (float) $arg ? "$label doit être au plus $arg." : null,
                    'minlen' => mb_strlen($str) < (int) $arg ? "$label doit contenir au moins $arg caractères." : null,
                    'maxlen' => mb_strlen($str) > (int) $arg ? "$label est trop long ($arg caractères maximum)." : null,
                    'in' => !in_array($str, explode(',', (string) $arg), true) ? "$label contient une valeur non autorisée." : null,
                    'date' => !(\DateTime::createFromFormat('Y-m-d', $str) && \DateTime::createFromFormat('Y-m-d', $str)->format('Y-m-d') === $str) ? "$label n'est pas une date valide." : null,
                    default => null,
                };
                if ($bad) { $errors[$field] = $bad; break; }
            }
        }
        return $errors;
    }
}
