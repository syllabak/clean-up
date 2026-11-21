<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Csrf, Database, Env};
use App\Services\PaymentService;

/** Données de l'assistant de réservation. Il passe ensuite par les mêmes routes que le formulaire (prix, disponibilités, réservation) : aucune règle n'est dupliquée. */
final class ChatbotController extends Controller
{
    public function config(): void
    {
        $cid = Env::companyId();
        $services = Database::all("SELECT id, name, slug, short_description, billing_unit FROM services WHERE company_id = ? AND active = 1 AND deleted_at IS NULL AND availability_status = 'available' ORDER BY sort", [$cid]);
        foreach ($services as &$s) $s['from_price'] = ServiceController::fromPrice(Database::one('SELECT * FROM services WHERE id = ?', [$s['id']]));
        unset($s);
        json_response([
            'csrf' => Csrf::token(),
            'company' => ['name' => setting('company_name', ''), 'phone' => setting('company_phone', ''), 'whatsapp' => preg_replace('/\D/', '', (string) setting('company_whatsapp', '')), 'hours' => setting('opening_text', '')],
            'currency' => setting('currency', 'FCFA'),
            'services' => $services,
            'hoods' => Database::all('SELECT n.id, n.name, c.name AS commune FROM neighborhoods n JOIN communes c ON c.id = n.commune_id WHERE n.company_id = ? AND n.active = 1 AND n.deleted_at IS NULL ORDER BY n.name', [$cid]),
            'faq' => array_map(fn($f) => ['q' => $f['question'], 'a' => $f['answer']], Database::all('SELECT question, answer FROM faq WHERE company_id = ? AND active = 1 ORDER BY sort, id LIMIT 30', [$cid])),
            'pay' => array_keys(PaymentService::available()),
        ]);
    }
}
