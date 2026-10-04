<?php
declare(strict_types=1);
namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/public'): string
    {
        $content = self::partial($view, $data);
        return self::prefix($layout === null ? $content : self::partial($layout, array_merge($data, ['content' => $content])));
    }

    /** Installation en sous-dossier : « href="/x" » devient « href="/sous-dossier/x" » (href, action, src). */
    private static function prefix(string $html): string
    {
        $base = base_path();
        if ($base === '') return $html;
        return preg_replace('#\b(href|action|src)="/(?!/)#', '$1="' . $base . '/', $html) ?? $html;
    }

    /** Les variables internes sont préfixées « __ » : elles ne peuvent pas masquer celles des gabarits (ex. $view, $data). */
    public static function partial(string $__view, array $__data = []): string
    {
        $__file = BASE_PATH . '/app/Views/' . $__view . '.php';
        if (!is_file($__file)) throw new \RuntimeException("Vue introuvable : $__view");
        extract($__data, EXTR_SKIP);
        ob_start();
        try { require $__file; } catch (\Throwable $__e) { ob_end_clean(); throw $__e; }
        return (string) ob_get_clean();
    }
}
