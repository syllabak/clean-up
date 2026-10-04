<?php
declare(strict_types=1);
namespace Database\Seeders;

use App\Core\Database as DB;

/** Données de démonstration / tests (zones de Dakar, 6 services, 3 équipes, comptes). SQLite de test uniquement. */
final class DemoSeeder
{
    public const ADMIN_EMAIL = 'admin@netexpress.test';
    public const ADMIN_PASSWORD = 'Admin#2026';

    public static function run(): void
    {
        DB::transaction(function () {
            self::geo();
            [$skills, $cats] = [self::skills(), self::categories()];
            $svc = self::services($skills, $cats);
            self::teams($skills, $svc);
            self::users();
            self::content();
        });
    }

    private static function geo(): void
    {
        $region = DB::insert('regions', ['name' => 'Dakar']);
        $city = DB::insert('cities', ['region_id' => $region, 'name' => 'Dakar']);
        $thies = DB::insert('regions', ['name' => 'Thiès']);
        $thiesCity = DB::insert('cities', ['region_id' => $thies, 'name' => 'Thiès']);
        $zones = [ // commune => [ville, [quartier, frais, trajet(min), jours ISO, début, fin]]
            'Parcelles Assainies' => [$city, [['Unité 15', 1000, 25, '1,3,5', '', ''], ['Unité 24', 1000, 25, '1,3,5', '', ''], ['Unité 26', 1500, 30, '1,3,5', '', '']]],
            'Ngor' => [$city, [['Almadies', 2000, 30, '2,4,6', '09:00', '17:00'], ['Ngor Village', 2000, 30, '2,4,6', '09:00', '17:00']]],
            'Ouakam' => [$city, [['Ouakam Village', 1500, 20, '1,4', '', ''], ['Cité Avion', 1500, 20, '', '', '']]],
            'Dakar Plateau' => [$city, [['Plateau', 500, 15, '', '', ''], ['Médina', 500, 15, '', '', '']]],
            'Mermoz-Sacré-Cœur' => [$city, [['Sacré-Cœur 3', 1000, 20, '', '', ''], ['Mermoz', 1000, 20, '', '', '']]],
            'Thiès Nord' => [$thiesCity, [['Randoulène', 3000, 45, '5,6', '09:00', '16:00']]],
        ];
        foreach ($zones as $commune => [$cityId, $list]) {
            $cid = DB::insert('communes', ['city_id' => $cityId, 'name' => $commune]);
            foreach ($list as $i => [$n, $fee, $trav, $days, $s, $e]) {
                DB::insert('neighborhoods', ['commune_id' => $cid, 'name' => $n, 'travel_fee' => $fee, 'avg_travel_minutes' => $trav, 'intervention_days' => $days, 'slot_start' => $s, 'slot_end' => $e, 'sort' => $i]);
            }
        }
    }

    private static function skills(): array
    {
        return ['auto' => DB::insert('skills', ['name' => 'Lavage automobile']), 'textile' => DB::insert('skills', ['name' => 'Nettoyage textile (canapés, matelas, tapis)']),
                'maison' => DB::insert('skills', ['name' => 'Entretien de locaux'])];
    }

    private static function categories(): array
    {
        return ['auto' => DB::insert('service_categories', ['name' => 'Automobile', 'slug' => 'automobile', 'sort' => 1]),
                'textile' => DB::insert('service_categories', ['name' => 'Mobilier & textile', 'slug' => 'mobilier-textile', 'sort' => 2]),
                'locaux' => DB::insert('service_categories', ['name' => 'Maison & bureaux', 'slug' => 'maison-bureaux', 'sort' => 3])];
    }

    private static function svc(array $d): int
    {
        return DB::insert('services', array_merge(['company_id' => 1, 'base_price' => 0, 'billing_unit' => 'fixed', 'base_duration' => 30, 'duration_per_unit' => 0,
            'featured' => 0, 'availability_status' => 'available', 'active' => 1, 'created_at' => now()], $d));
    }
    private static function formula(int $s, string $n, string $desc, int $amount, int $dur, int $sort): void { DB::insert('service_prices', ['service_id' => $s, 'name' => $n, 'description' => $desc, 'amount' => $amount, 'duration_minutes' => $dur, 'sort' => $sort]); }
    private static function option(int $s, string $n, int $p, int $d): void { DB::insert('service_options', ['service_id' => $s, 'name' => $n, 'price' => $p, 'duration_minutes' => $d]); }
    private static function field(int $s, string $key, string $label, string $type, string $role, int $sort, ?array $choices = null, ?int $min = null, ?int $max = null, string $help = ''): void
    {
        $f = DB::insert('service_fields', ['service_id' => $s, 'field_key' => $key, 'label' => $label, 'type' => $type, 'role' => $role, 'required' => 1, 'min_value' => $min, 'max_value' => $max, 'help' => $help ?: null, 'sort' => $sort]);
        foreach ($choices ?? [] as $i => [$l, $delta, $mult, $dur]) DB::insert('service_field_choices', ['field_id' => $f, 'label' => $l, 'price_delta' => $delta, 'multiplier' => $mult, 'duration_delta' => $dur, 'sort' => $i]);
    }

    private static function services(array $sk, array $cat): array
    {
        $out = [];
        $s = $out['auto'] = self::svc(['category_id' => $cat['auto'], 'required_skill_id' => $sk['auto'], 'name' => 'Lavage automobile', 'slug' => 'lavage-automobile', 'featured' => 1, 'sort' => 1,
            'short_description' => 'Lavage à domicile ou au bureau, du simple au complet.', 'billing_unit' => 'per_item', 'base_duration' => 15,
            'description' => "Notre équipe se déplace avec son matériel et son eau. Choisissez la formule adaptée à votre véhicule, de l'extérieur rapide au traitement complet intérieur et extérieur.",
            'conditions' => "Prévoir un accès au véhicule et un emplacement dégagé. Véhicules très sales : supplément possible après constat."]);
        self::formula($s, 'Simple', 'Extérieur : carrosserie, vitres, jantes', 3000, 30, 1);
        self::formula($s, 'Complet', 'Extérieur + intérieur : aspiration, plastiques, vitres', 6000, 75, 2);
        self::formula($s, 'Premium', 'Complet + lustrage et protection', 12000, 135, 3);
        self::field($s, 'vehicle', 'Type de véhicule', 'select', 'none', 1, [['Citadine', 0, 1, 0], ['Berline', 1000, 1, 10], ['SUV / 4x4', 2000, 1, 20], ['Pick-up', 2500, 1, 20], ['Minibus', 4000, 1, 40]]);
        self::field($s, 'count', 'Nombre de véhicules', 'number', 'quantity', 2, null, 1, 10);
        self::option($s, 'Shampoing moteur', 3000, 30); self::option($s, 'Traitement cuir', 5000, 30); self::option($s, 'Désodorisation', 1500, 10);

        $s = $out['canape'] = self::svc(['category_id' => $cat['textile'], 'required_skill_id' => $sk['textile'], 'name' => 'Nettoyage de canapés et fauteuils', 'slug' => 'nettoyage-canapes-fauteuils', 'featured' => 1, 'sort' => 2,
            'short_description' => 'Injection-extraction, détachage et séchage rapide.', 'base_duration' => 30,
            'description' => "Nettoyage en profondeur par injection-extraction : poussière, taches, odeurs. Séchage en quelques heures.",
            'conditions' => "Prévoir une prise électrique et un point d'eau à proximité."]);
        self::field($s, 'seats', 'Type / nombre de places', 'select', 'none', 1, [['Fauteuil', 5000, 1, 0], ['Canapé 2 places', 10000, 1, 15], ['Canapé 3 places', 14000, 1, 30], ['Canapé 4+ places / angle', 20000, 1, 60]]);
        self::field($s, 'material', 'Matière', 'select', 'none', 2, [['Tissu', 0, 1, 0], ['Velours', 0, 1.15, 10], ['Cuir / simili', 0, 1.20, 0]]);
        self::option($s, 'Anti-taches', 3000, 10); self::option($s, 'Désinfection anti-acariens', 4000, 15);

        $s = $out['matelas'] = self::svc(['category_id' => $cat['textile'], 'required_skill_id' => $sk['textile'], 'name' => 'Nettoyage de matelas', 'slug' => 'nettoyage-matelas', 'sort' => 3,
            'short_description' => 'Aspiration, détachage et traitement anti-acariens.', 'base_duration' => 15,
            'description' => "Élimination des acariens, taches et odeurs. Votre matelas est utilisable le soir même."]);
        self::field($s, 'size', 'Dimensions', 'select', 'none', 1, [['1 place (90 cm)', 6000, 1, 30], ['2 places (140 cm)', 9000, 1, 40], ['Grand lit (160–180 cm)', 12000, 1, 50]]);
        self::field($s, 'count', 'Nombre de matelas', 'number', 'quantity', 2, null, 1, 10);
        self::option($s, 'Traitement anti-acariens', 3000, 15);

        $s = $out['tapis'] = self::svc(['category_id' => $cat['textile'], 'required_skill_id' => $sk['textile'], 'name' => 'Nettoyage de tapis et moquettes', 'slug' => 'nettoyage-tapis-moquettes', 'sort' => 4,
            'short_description' => 'Tarif au mètre carré, sur place.', 'billing_unit' => 'per_sqm', 'base_duration' => 30, 'duration_per_unit' => 3,
            'description' => "Shampoing et extraction sur place pour tapis et moquettes. Le prix dépend de la surface."]);
        self::formula($s, 'Tapis', 'Tarif au m²', 1500, 0, 1); self::formula($s, 'Moquette', 'Tarif au m²', 1200, 0, 2);
        self::field($s, 'area', 'Surface totale (m²)', 'number', 'area', 1, null, 1, 300, 'Mesurez la surface à nettoyer, en m².');
        self::option($s, 'Traitement anti-taches', 5000, 15);

        $s = $out['maison'] = self::svc(['category_id' => $cat['locaux'], 'required_skill_id' => $sk['maison'], 'name' => 'Nettoyage de maison', 'slug' => 'nettoyage-maison', 'featured' => 1, 'sort' => 5,
            'short_description' => 'Entretien courant, grand ménage ou remise en état.', 'base_duration' => 30,
            'description' => "Du ménage régulier au nettoyage de fin de chantier. Le niveau de nettoyage et le type de logement déterminent la durée."]);
        self::formula($s, 'Standard', 'Entretien courant', 10000, 90, 1); self::formula($s, 'Approfondi', 'Grand ménage', 20000, 150, 2); self::formula($s, 'Remise en état', 'Fin de chantier / déménagement', 35000, 210, 3);
        self::field($s, 'housing', 'Type de logement', 'select', 'none', 1, [['Studio', 0, 1, 0], ['F2', 0, 1.4, 30], ['F3', 0, 1.8, 60], ['Villa', 0, 2.8, 120]]);
        self::field($s, 'surface', 'Surface approximative (m²)', 'number', 'none', 2, null, 10, 1000);
        self::field($s, 'rooms', 'Nombre de pièces', 'number', 'none', 3, null, 1, 30);
        self::option($s, 'Nettoyage des vitres', 5000, 30); self::option($s, 'Repassage (1 h)', 4000, 60);

        $s = $out['bureaux'] = self::svc(['category_id' => $cat['locaux'], 'required_skill_id' => $sk['maison'], 'name' => 'Nettoyage de bureaux', 'slug' => 'nettoyage-bureaux', 'sort' => 6,
            'short_description' => 'Open space, cabinets, boutiques : au m², ponctuel ou récurrent.', 'billing_unit' => 'per_sqm', 'base_duration' => 30, 'duration_per_unit' => 1,
            'description' => "Sols, sanitaires, postes de travail, vitres intérieures. Intervention en dehors des heures d'ouverture possible sur demande."]);
        self::formula($s, 'Standard', 'Prix au m²', 300, 0, 1); self::formula($s, 'Approfondi', 'Prix au m²', 500, 30, 2);
        self::field($s, 'area', 'Surface (m²)', 'number', 'area', 1, null, 10, 2000);
        self::field($s, 'premises', 'Type de locaux', 'select', 'none', 2, [['Open space', 0, 1, 0], ['Bureaux cloisonnés', 0, 1.1, 20], ['Boutique / commerce', 0, 1.2, 20]]);
        self::field($s, 'frequency', 'Fréquence', 'select', 'none', 3, [['Ponctuel', 0, 1, 0], ['Hebdomadaire', 0, 0.9, 0], ['Quotidien', 0, 0.8, 0]]);
        self::option($s, 'Vitres intérieures et extérieures', 15000, 45);
        return $out;
    }

    private static function teams(array $sk, array $svc): void
    {
        $defs = [
            ['Équipe Auto', 5, 'Camionnette citerne', ['auto'], 'auto', [['Moussa Diop', '771110001'], ['Cheikh Ndiaye', '771110002']]],
            ['Équipe Textile', 4, 'Fourgon injection-extraction', ['canape', 'matelas', 'tapis'], 'textile', [['Awa Fall', '772220001'], ['Ibrahima Sarr', '772220002']]],
            ['Équipe Maison & Bureaux', 3, 'Utilitaire', ['maison', 'bureaux'], 'maison', [['Fatou Ba', '773330001'], ['Omar Sy', '773330002']]],
        ];
        $staffIds = [];
        foreach ($defs as [$name, $cap, $veh, $services, $skill, $members]) {
            $t = DB::insert('teams', ['name' => $name, 'daily_capacity' => $cap, 'vehicle' => $veh, 'status' => 'active']);
            foreach ($services as $k) DB::insert('team_services', ['team_id' => $t, 'service_id' => $svc[$k]]);
            for ($d = 1; $d <= 6; $d++) DB::insert('working_hours', ['team_id' => $t, 'day_of_week' => $d, 'start_time' => '08:00', 'end_time' => '18:00', 'break_start' => '12:30', 'break_end' => '14:00']);
            foreach ($members as [$n, $ph]) {
                $s = DB::insert('staff', ['team_id' => $t, 'name' => $n, 'phone' => $ph, 'status' => 'active']);
                DB::insert('staff_skills', ['staff_id' => $s, 'skill_id' => $sk[$skill]]);
                $staffIds[] = $s;
            }
        }
        $GLOBALS['__demo_staff'] = $staffIds;
    }

    private static function users(): void
    {
        $roles = array_column(DB::all('SELECT id, name FROM roles'), 'id', 'name');
        $hash = fn(string $p) => password_hash($p, PASSWORD_DEFAULT);
        DB::insert('users', ['role_id' => $roles['admin'], 'name' => 'Administrateur', 'email' => self::ADMIN_EMAIL, 'password_hash' => $hash(self::ADMIN_PASSWORD), 'active' => 1, 'created_at' => now()]);
        DB::insert('users', ['role_id' => $roles['manager'], 'name' => 'Gestionnaire', 'email' => 'gestion@netexpress.test', 'password_hash' => $hash('Gestion#2026'), 'active' => 1, 'created_at' => now()]);
        $first = DB::all('SELECT id, name, email FROM staff ORDER BY id');
        foreach ([[0, 'moussa@netexpress.test'], [2, 'awa@netexpress.test']] as [$i, $email]) {
            $st = $first[$i];
            DB::insert('users', ['role_id' => $roles['agent'], 'staff_id' => $st['id'], 'name' => $st['name'], 'email' => $email, 'password_hash' => $hash('Agent#2026'), 'active' => 1, 'created_at' => now()]);
        }
    }

    private static function content(): void
    {
        foreach ([['Aminata D.', 'Équipe ponctuelle, canapé comme neuf. La réservation en ligne est très simple.', 5], ['Cheikh T.', "Lavage complet de mon 4x4 au bureau pendant la réunion. Parfait.", 5], ['Société Teranga', 'Nos bureaux sont nettoyés chaque semaine, sans aucun souci.', 5]] as $i => [$n, $c, $r]) {
            DB::insert('testimonials', ['name' => $n, 'content' => $c, 'rating' => $r, 'sort' => $i, 'active' => 1]);
        }
        // Jour férié type : fermeture de toute l'entreprise (exemple)
        DB::insert('time_off', ['company_id' => 1, 'team_id' => null, 'staff_id' => null, 'start_date' => date('Y') . '-12-25', 'end_date' => date('Y') . '-12-25', 'reason' => 'Noël']);
    }
}
