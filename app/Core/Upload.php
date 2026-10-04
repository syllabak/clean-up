<?php
declare(strict_types=1);
namespace App\Core;

/** Téléversement d'images sécurisé : contrôle réel du contenu (getimagesize), extension imposée, nom aléatoire. */
final class Upload
{
    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** @return string|null chemin relatif au dossier de destination, ou null si aucun fichier. @throws \RuntimeException */
    public static function image(array $file, string $destDir): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) throw new \RuntimeException("Échec du téléversement (code {$file['error']}).");
        if (!is_uploaded_file($file['tmp_name']) && !defined('TESTING')) throw new \RuntimeException('Fichier invalide.');
        if ($file['size'] > self::MAX_BYTES) throw new \RuntimeException('Image trop lourde (5 Mo maximum).');
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !isset(self::TYPES[$info[2]])) throw new \RuntimeException('Format refusé : JPEG, PNG, WebP ou GIF uniquement.');
        if ($info[0] > 6000 || $info[1] > 6000) throw new \RuntimeException('Dimensions trop grandes.');
        if (!is_dir($destDir)) mkdir($destDir, 0775, true);
        $name = bin2hex(random_bytes(12)) . '.' . self::TYPES[$info[2]];
        $dest = rtrim($destDir, '/') . '/' . $name;
        $ok = defined('TESTING') ? copy($file['tmp_name'], $dest) : move_uploaded_file($file['tmp_name'], $dest);
        if (!$ok) throw new \RuntimeException("Impossible d'enregistrer le fichier.");
        chmod($dest, 0644);
        return $name;
    }
}
