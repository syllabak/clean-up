<?php
declare(strict_types=1);
namespace App\Core;

/** Téléversement d'images sécurisé : contrôle réel du contenu (getimagesize), extension imposée, nom aléatoire. */
final class Upload
{
    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** Cause réelle d'un échec de téléversement, avec la limite du serveur quand c'est elle la cause. */
    public static function explain(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image trop lourde pour ce serveur (limite ' . ini_get('upload_max_filesize') . '). Réduisez-la ou demandez à votre hébergeur d\'augmenter upload_max_filesize.',
            UPLOAD_ERR_PARTIAL => 'Envoi interrompu (connexion instable). Réessayez.',
            UPLOAD_ERR_NO_TMP_DIR => 'Le serveur n\'a pas de dossier temporaire : contactez votre hébergeur (upload_tmp_dir).',
            UPLOAD_ERR_CANT_WRITE => 'Le serveur ne peut pas écrire le fichier (disque plein ou droits insuffisants sur le dossier uploads).',
            UPLOAD_ERR_EXTENSION => 'Envoi bloqué par une extension PHP du serveur.',
            default => "Échec du téléversement (code {$code}).",
        };
    }

    /** @return string|null chemin relatif au dossier de destination, ou null si aucun fichier. @throws \RuntimeException */
    public static function image(array $file, string $destDir): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) throw new \RuntimeException(self::explain((int) $file['error']));
        if (!is_uploaded_file($file['tmp_name']) && !defined('TESTING')) throw new \RuntimeException('Fichier invalide.');
        if ($file['size'] > self::MAX_BYTES) throw new \RuntimeException('Image trop lourde (5 Mo maximum).');
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !isset(self::TYPES[$info[2]])) throw new \RuntimeException('Format refusé : JPEG, PNG, WebP ou GIF uniquement.');
        if ($info[0] > 6000 || $info[1] > 6000) throw new \RuntimeException('Dimensions trop grandes.');
        if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) throw new \RuntimeException('Le dossier d\'enregistrement est introuvable et ne peut pas être créé (droits sur le serveur).');
        if (!is_writable($destDir)) throw new \RuntimeException('Le dossier d\'enregistrement n\'est pas accessible en écriture : donnez les droits d\'écriture au serveur web sur ' . basename(dirname($destDir)) . '/' . basename($destDir) . '.');
        $name = bin2hex(random_bytes(12)) . '.' . self::TYPES[$info[2]];
        $dest = rtrim($destDir, '/') . '/' . $name;
        $ok = defined('TESTING') ? copy($file['tmp_name'], $dest) : move_uploaded_file($file['tmp_name'], $dest);
        if (!$ok) throw new \RuntimeException("Impossible d'enregistrer le fichier.");
        chmod($dest, 0644);
        return $name;
    }
}
