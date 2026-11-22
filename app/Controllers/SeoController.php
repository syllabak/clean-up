<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Database, Env};

/** robots.txt et sitemap.xml : les pages publiques sont indexables, tout l'espace privé est exclu. */
final class SeoController extends Controller
{
    private function origin(): string
    {
        $u = rtrim((string) Env::get('APP_URL', ''), '/');
        return $u !== '' ? $u : (Request_isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    public function robots(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /agent\nDisallow: /api/\nDisallow: /cron/\nDisallow: /webhooks/\nDisallow: /suivi\nDisallow: /reservation\nDisallow: /paiement\nDisallow: /abonnement/\nDisallow: /connexion\nAllow: /\n\nSitemap: " . $this->origin() . "/sitemap.xml\n";
        exit;
    }

    public function sitemap(): never
    {
        $o = $this->origin(); $urls = ['/', '/services', '/abonnements', '/faq', '/contact', '/a-propos', '/recrutement', '/reserver'];
        foreach (Database::all('SELECT slug FROM services WHERE company_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort', [Env::companyId()]) as $s) $urls[] = '/services/' . $s['slug'];
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) echo '  <url><loc>' . htmlspecialchars($o . $u, ENT_XML1) . "</loc></url>\n";
        echo "</urlset>\n";
        exit;
    }
}

function Request_isHttps(): bool { return \App\Core\Request::isHttps(); }
