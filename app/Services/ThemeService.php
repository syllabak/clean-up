<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Apparence du site public : couleurs, logo, bouton du chatbot. Tout est enregistré dans les paramètres (aucun fichier à modifier).
 * Les couleurs sont validées (#RRGGBB uniquement) avant d'être écrites dans une balise <style> : aucune autre valeur ne passe.
 */
final class ThemeService
{
    public const DEFAULTS = [
        'theme_dark' => '#0B2545', 'theme_primary' => '#1D7FD6', 'theme_accent' => '#FFD23F',
        'cb_bg' => '#0B2545', 'cb_color' => '#FFFFFF', 'cb_dot' => '#FFD23F',
    ];
    public const CB_DEFAULT_LABEL = 'Prendre rendez-vous';

    /** Thèmes prêts à l'emploi : [nom, sombre, principal, accent]. */
    public const PRESETS = [
        'eau'    => ['Eau (par défaut)', '#0B2545', '#1D7FD6', '#FFD23F'],
        'foret'  => ['Forêt', '#0F3D2E', '#1E9E6A', '#F5B63B'],
        'corail' => ['Corail', '#3B1F2B', '#E0533D', '#FFD166'],
        'nuit'   => ['Nuit', '#14142B', '#6C5CE7', '#FFD93D'],
        'ardoise' => ['Ardoise', '#1F2933', '#2F7D95', '#F0A04B'],
    ];

    public static function hex(?string $v, string $fallback): string
    {
        $v = strtoupper(trim((string) $v));
        return preg_match('/^#[0-9A-F]{6}$/', $v) ? $v : $fallback;
    }

    public static function get(string $key): string
    {
        return self::hex(setting($key), self::DEFAULTS[$key]);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $h): array { return [hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2))]; }

    /** Mélange avec une autre couleur : $t = part de $b (0 à 1). */
    public static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = self::rgb($a); [$r2, $g2, $b2] = self::rgb($b);
        return sprintf('#%02X%02X%02X', (int) round($r1 + ($r2 - $r1) * $t), (int) round($g1 + ($g2 - $g1) * $t), (int) round($b1 + ($b2 - $b1) * $t));
    }

    /** Noir ou blanc selon la couleur de fond : le texte reste lisible quelle que soit la couleur choisie. */
    public static function textOn(string $bg): string
    {
        [$r, $g, $b] = array_map(function (int $c): float { $c /= 255; return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4; }, self::rgb($bg));
        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) > 0.4 ? '#0B2545' : '#FFFFFF';
    }

    public static function logo(): ?string
    {
        $p = (string) setting('brand_logo', '');
        return preg_match('~^/uploads/site/[a-f0-9]{24}\.(png|jpg|webp|gif)$~', $p) ? $p : null;
    }

    public static function chatLabel(): string
    {
        $l = trim((string) setting('cb_label', ''));
        return $l === '' ? self::CB_DEFAULT_LABEL : mb_substr($l, 0, 30);
    }

    public static function chatPosition(): string { return setting('cb_position', 'left') === 'right' ? 'right' : 'left'; }

    /** Bloc CSS injecté après app.css. Vide si rien n'a été personnalisé. */
    public static function css(): string
    {
        $dark = self::get('theme_dark'); $pri = self::get('theme_primary'); $acc = self::get('theme_accent');
        $cbBg = self::get('cb_bg'); $cbFg = self::get('cb_color'); $cbDot = self::get('cb_dot');
        $css = ":root{--ink:{$dark};--ink-2:" . self::mix($dark, '#FFFFFF', .12) . ";--water:{$pri};--water-d:" . self::mix($pri, '#000000', .2)
            . ";--foam:" . self::mix($pri, '#FFFFFF', .9) . ";--foam-2:" . self::mix($pri, '#FFFFFF', .8) . ";--lemon:{$acc};--lemon-d:" . self::mix($acc, '#000000', .1) . ";}"
            . ".btn-primary{--bs-btn-color:" . self::textOn($pri) . ";--bs-btn-hover-color:" . self::textOn($pri) . ";--bs-btn-active-color:" . self::textOn($pri) . ";}"
            . ".btn-lemon,.btn-lemon:hover{color:" . self::textOn($acc) . ";}"
            . ".cb-launch{background:{$cbBg};color:{$cbFg};}.cb-launch:hover{background:" . self::mix($cbBg, '#FFFFFF', .12) . ";}.cb-launch .cb-dot{background:{$cbDot};}";
        if (self::chatPosition() === 'right') $css .= ".cb-launch{left:auto;right:16px;}.cb-panel{left:auto;right:16px;}.wa-float{right:auto;left:16px;}";
        return $css;
    }

    public static function isCustom(): bool
    {
        foreach (array_keys(self::DEFAULTS) as $k) if (self::get($k) !== self::DEFAULTS[$k]) return true;
        return self::logo() !== null || self::chatPosition() !== 'left' || trim((string) setting('cb_label', '')) !== '';
    }
}
