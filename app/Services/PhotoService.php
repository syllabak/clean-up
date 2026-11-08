<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Database, Upload};

/** Photos d'une commande : « avant intervention » envoyées par le client à la commande, photos de l'équipe sur le terrain. Stockées hors du dossier public. */
final class PhotoService
{
    public const MAX_CLIENT = 8;

    public static function dir(): string { return BASE_PATH . '/storage/uploads/missions'; }

    public function forBooking(int $bookingId): array
    {
        return Database::all('SELECT id, kind, user_id, created_at FROM booking_photos WHERE booking_id = ? ORDER BY id', [$bookingId]);
    }

    /** Photos envoyées par le client (pas par un agent) pour cette commande. */
    public function clientCount(int $bookingId): int
    {
        return (int) Database::val('SELECT COUNT(*) FROM booking_photos WHERE booking_id = ? AND user_id IS NULL', [$bookingId]);
    }

    /** Enregistre une photo « avant » du client. @throws \RuntimeException message lisible si le fichier est refusé ou la limite atteinte */
    public function addFromClient(array $booking, array $file): int
    {
        if (!in_array($booking['status'], ['pending', 'confirmed'], true)) throw new \RuntimeException("Cette commande n'accepte plus de photos.");
        if ($this->clientCount((int) $booking['id']) >= self::MAX_CLIENT) throw new \RuntimeException('Maximum ' . self::MAX_CLIENT . ' photos par commande.');
        $name = Upload::image($file, self::dir());
        if (!$name) throw new \RuntimeException('Aucune photo reçue.');
        $id = Database::insert('booking_photos', ['booking_id' => (int) $booking['id'], 'user_id' => null, 'kind' => 'before', 'path' => $name, 'created_at' => now()]);
        Audit::log('booking.photo.client', 'booking', (int) $booking['id'], ['photo' => $id], null);
        return $id;
    }

    private const THUMBS = [160, 480];   // largeurs de vignettes autorisées

    /**
     * Envoie la photo au navigateur, en vignette si $width vaut 160 ou 480 (mise en cache ; l'original si GD est absent).
     * L'appelant a déjà vérifié le droit d'accès.
     */
    public static function send(array $photo, ?int $width = null): never
    {
        $file = self::dir() . '/' . basename((string) $photo['path']);
        if (!is_file($file)) abort(404);
        if ($width !== null && in_array($width, self::THUMBS, true) && function_exists('imagecreatefromstring')) {
            $thumb = BASE_PATH . '/storage/cache/thumbs/' . (int) $photo['id'] . '_' . $width . '.jpg';
            if (!is_file($thumb) && !self::makeThumb($file, $thumb, $width)) $thumb = null;
            if ($thumb) { self::out($thumb, 'image/jpeg'); }
        }
        $info = getimagesize($file);
        self::out($file, $info['mime'] ?? 'application/octet-stream');
    }

    private static function out(string $file, string $mime): never
    {
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    private static function makeThumb(string $src, string $dest, int $w): bool
    {
        $img = @imagecreatefromstring((string) file_get_contents($src));
        if (!$img) return false;
        $sw = imagesx($img); $sh = imagesy($img);
        if ($sw <= $w) { imagedestroy($img); return false; }   // déjà plus petite que la vignette : on sert l'original
        $h = max(1, (int) round($sh * $w / $sw));
        $out = imagecreatetruecolor($w, $h);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, $sw, $sh);
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
        $ok = imagejpeg($out, $dest, 80);
        imagedestroy($img); imagedestroy($out);
        return $ok;
    }
}
