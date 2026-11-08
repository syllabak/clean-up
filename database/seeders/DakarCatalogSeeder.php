<?php
declare(strict_types=1);
namespace Database\Seeders;

use App\Core\Database as DB;

/**
 * Catalogue de départ pour Dakar : quartiers (avec coordonnées pour détecter le quartier d'un client géolocalisé), services et tarifs,
 * équipes, horaires et deux agents modèles par équipe (à renommer). Pas de comptes : à ajuster ensuite dans l'administration (Configuration).
 *
 * Tarifs de référence relevés sur le marché dakarois (prestataires de lavage auto à domicile et de nettoyage textile) :
 *  - lavage auto à domicile : 5 000 (extérieur), 9 000 (complet), 18 000 FCFA (détaillage)
 *  - canapé : dès 5 000 FCFA la place ; tapis et moquette : dès 2 500 FCFA le m² ; matelas : 15 000 (1 place) à 20 000 FCFA (2 places)
 *  - repassage : dès 3 500 FCFA le panier
 * Les autres prix (ménage, vitres, bureaux, après travaux) sont des estimations cohérentes avec ces repères : à valider avec vos coûts réels.
 * Les coordonnées des quartiers sont approximatives (centre du quartier) : corrigez-les dans « Quartiers desservis » si besoin.
 */
final class DakarCatalogSeeder
{
    /** @return array{services:int, neighborhoods:int, teams:int} */
    public static function run(): array
    {
        $count = ['services' => 0, 'neighborhoods' => 0, 'teams' => 0];
        DB::transaction(function () use (&$count) {
            $count['neighborhoods'] = self::geo();
            $sk = self::skills(); $cat = self::categories();
            $svc = self::services($sk, $cat); $count['services'] = count($svc);
            $count['teams'] = self::teams($svc, $sk);
        });
        return $count;
    }

    /** Vrai si un catalogue (services ou quartiers) existe déjà : on ne charge alors rien sans --force. */
    public static function alreadyLoaded(): bool
    {
        return (int) DB::val('SELECT COUNT(*) FROM services') > 0 || (int) DB::val('SELECT COUNT(*) FROM neighborhoods') > 0;
    }

    private static function geo(): int
    {
        $region = DB::insert('regions', ['name' => 'Dakar']);
        $city = fn(string $n) => DB::insert('cities', ['region_id' => $region, 'name' => $n]);
        $dakar = $city('Dakar'); $pikine = $city('Pikine'); $gued = $city('Guédiawaye'); $rufisque = $city('Rufisque');
        // commune => [ville, [[quartier, lat, lng, frais de déplacement FCFA, trajet moyen (min)]]]
        $zones = [
            'Dakar-Plateau' => [$dakar, [['Plateau', 14.6708, -17.4380, 0, 15]]],
            'Médina - Gueule Tapée - Fass' => [$dakar, [['Médina', 14.6860, -17.4510, 500, 15], ['Gueule Tapée', 14.6800, -17.4480, 500, 15], ['Fass', 14.6900, -17.4560, 500, 15], ['Colobane', 14.6980, -17.4490, 500, 20]]],
            'Fann - Point E - Amitié' => [$dakar, [['Fann', 14.6900, -17.4640, 500, 15], ['Point E', 14.6960, -17.4650, 500, 15], ['Amitié', 14.7010, -17.4600, 500, 20]]],
            'Grand Dakar - HLM' => [$dakar, [['Grand Dakar', 14.7070, -17.4540, 500, 20], ['HLM', 14.7060, -17.4420, 500, 20], ['Castors', 14.7140, -17.4520, 500, 20], ['Dieuppeul - Derklé', 14.7180, -17.4570, 500, 20]]],
            'Mermoz - Sacré-Cœur' => [$dakar, [['Mermoz', 14.7090, -17.4700, 1000, 20], ['Sacré-Cœur', 14.7160, -17.4650, 1000, 20], ['Cité Keur Gorgui', 14.7120, -17.4780, 1000, 25]]],
            'Sicap - Liberté' => [$dakar, [['Sicap Liberté', 14.7230, -17.4590, 1000, 25], ['Liberté 6', 14.7290, -17.4600, 1000, 25], ['Patte d\'Oie', 14.7344, -17.4572, 1000, 25]]],
            'Hann - Bel-Air' => [$dakar, [['Hann Maristes', 14.7240, -17.4300, 1000, 25], ['Bel-Air', 14.7190, -17.4340, 1000, 25]]],
            'Ouakam' => [$dakar, [['Ouakam', 14.7210, -17.4870, 1000, 25], ['Mamelles', 14.7260, -17.4960, 1500, 30]]],
            'Ngor - Almadies' => [$dakar, [['Ngor', 14.7510, -17.5130, 1500, 35], ['Almadies', 14.7440, -17.5260, 1500, 35]]],
            'Yoff' => [$dakar, [['Yoff', 14.7580, -17.4750, 1500, 35]]],
            'Grand Yoff - Parcelles Assainies' => [$dakar, [['Grand Yoff', 14.7440, -17.4560, 1000, 30], ['Parcelles Assainies', 14.7600, -17.4400, 1500, 35]]],
            'Pikine' => [$pikine, [['Pikine', 14.7550, -17.3900, 2000, 45], ['Thiaroye', 14.7590, -17.3590, 2500, 50]]],
            'Guédiawaye' => [$gued, [['Guédiawaye', 14.7790, -17.4010, 2000, 45]]],
            'Rufisque' => [$rufisque, [['Rufisque', 14.7160, -17.2740, 3000, 60], ['Keur Massar', 14.7840, -17.3180, 3000, 55], ['Diamniadio', 14.7180, -17.1850, 3500, 70]]],
        ];
        $n = 0;
        foreach ($zones as $commune => [$cityId, $list]) {
            $cid = DB::insert('communes', ['city_id' => $cityId, 'name' => $commune]);
            foreach ($list as $i => [$name, $lat, $lng, $fee, $min]) {
                DB::insert('neighborhoods', ['commune_id' => $cid, 'name' => $name, 'lat' => $lat, 'lng' => $lng, 'travel_fee' => $fee, 'avg_travel_minutes' => $min, 'intervention_days' => '', 'sort' => $i]);
                $n++;
            }
        }
        return $n;
    }

    private static function skills(): array
    {
        return ['auto' => DB::insert('skills', ['name' => 'Lavage automobile']), 'textile' => DB::insert('skills', ['name' => 'Nettoyage textile (canapés, matelas, tapis)']),
            'maison' => DB::insert('skills', ['name' => 'Entretien de logements et de locaux']), 'chantier' => DB::insert('skills', ['name' => 'Remise en état et vitres'])];
    }

    private static function categories(): array
    {
        return ['auto' => DB::insert('service_categories', ['name' => 'Automobile', 'slug' => 'automobile', 'sort' => 1]),
            'textile' => DB::insert('service_categories', ['name' => 'Mobilier et textile', 'slug' => 'mobilier-textile', 'sort' => 2]),
            'maison' => DB::insert('service_categories', ['name' => 'Maison', 'slug' => 'maison', 'sort' => 3]),
            'pro' => DB::insert('service_categories', ['name' => 'Bureaux et chantiers', 'slug' => 'bureaux-chantiers', 'sort' => 4])];
    }

    private static function svc(array $d): int
    {
        return DB::insert('services', array_merge(['company_id' => 1, 'base_price' => 0, 'billing_unit' => 'fixed', 'base_duration' => 30, 'duration_per_unit' => 0,
            'featured' => 0, 'availability_status' => 'available', 'active' => 1, 'created_at' => now()], $d));
    }
    private static function formula(int $s, string $n, string $desc, int $amount, int $dur, int $sort, string $includes = '', bool $popular = false): void
    {
        DB::insert('service_prices', ['service_id' => $s, 'name' => $n, 'description' => $desc, 'amount' => $amount, 'duration_minutes' => $dur, 'sort' => $sort, 'includes' => $includes ?: null, 'popular' => $popular ? 1 : 0]);
    }
    private static function option(int $s, string $n, int $p, int $d): void { DB::insert('service_options', ['service_id' => $s, 'name' => $n, 'price' => $p, 'duration_minutes' => $d]); }
    private static function field(int $s, string $key, string $label, string $type, string $role, int $sort, ?array $choices = null, ?int $min = null, ?int $max = null, string $help = ''): void
    {
        $f = DB::insert('service_fields', ['service_id' => $s, 'field_key' => $key, 'label' => $label, 'type' => $type, 'role' => $role, 'required' => 1, 'min_value' => $min, 'max_value' => $max, 'help' => $help ?: null, 'sort' => $sort]);
        foreach ($choices ?? [] as $i => [$l, $delta, $mult, $dur]) DB::insert('service_field_choices', ['field_id' => $f, 'label' => $l, 'price_delta' => $delta, 'multiplier' => $mult, 'duration_delta' => $dur, 'sort' => $i]);
    }

    private static function services(array $sk, array $cat): array
    {
        $out = [];
        $s = $out['auto'] = self::svc(['category_id' => $cat['auto'], 'required_skill_id' => $sk['auto'], 'name' => 'Lavage automobile à domicile', 'slug' => 'lavage-automobile', 'featured' => 1, 'sort' => 1,
            'short_description' => 'Lavage chez vous ou au bureau, eau et matériel fournis.', 'billing_unit' => 'per_item', 'base_duration' => 10,
            'description' => "Notre équipe vient avec son eau, son aspirateur et ses produits. Choisissez la formule : extérieur rapide, complet intérieur et extérieur, ou détaillage comme à la sortie d'usine.",
            'conditions' => "Prévoir un emplacement dégagé autour du véhicule. Véhicule très sale : supplément possible après constat."]);
        self::formula($s, 'Express', 'Extérieur uniquement', 5000, 30, 1, "Lavage de la carrosserie\nJantes et vitres\nSéchage microfibre");
        self::formula($s, 'Complet', 'Extérieur et intérieur', 9000, 60, 2, "Tout l'Express\nAspiration intérieure\nNettoyage du tableau de bord\nDésodorisation", true);
        self::formula($s, 'Détaillage', 'Comme à la sortie d\'usine', 18000, 120, 3, "Tout le Complet\nPolish de la carrosserie\nShampoing des sièges\nTraitement des plastiques");
        self::field($s, 'vehicle', 'Type de véhicule', 'select', 'none', 1, [['Citadine', 0, 1, 0], ['Berline', 1000, 1, 10], ['SUV / 4x4', 2000, 1, 15], ['Pick-up', 2500, 1, 15], ['Minibus', 4000, 1, 30]]);
        self::field($s, 'count', 'Nombre de véhicules', 'number', 'quantity', 2, null, 1, 10);
        self::option($s, 'Shampoing moteur', 3000, 20); self::option($s, 'Traitement du cuir', 5000, 30); self::option($s, 'Désodorisation', 1500, 10); self::option($s, 'Shampoing des sièges', 6000, 30);

        $s = $out['canape'] = self::svc(['category_id' => $cat['textile'], 'required_skill_id' => $sk['textile'], 'name' => 'Nettoyage de canapés et fauteuils', 'slug' => 'nettoyage-canapes-fauteuils', 'featured' => 1, 'sort' => 2,
            'short_description' => 'Dès 5 000 FCFA la place : injection-extraction et détachage.', 'base_duration' => 20,
            'description' => "Nettoyage en profondeur à l'injection-extraction : poussière, taches, odeurs. Séchage en quelques heures. Envoyez-nous une photo avec votre commande : nous confirmons le prix au besoin.",
            'conditions' => "Prévoir une prise électrique et un point d'eau à proximité. Les taches anciennes ou incrustées peuvent ne pas disparaître totalement."]);
        self::field($s, 'seats', 'Type et nombre de places', 'select', 'none', 1, [['Fauteuil ou chaise rembourrée (1 place)', 5000, 1, 0], ['Canapé 2 places', 10000, 1, 15], ['Canapé 3 places', 15000, 1, 30], ['Canapé 4 places', 20000, 1, 45], ['Canapé d\'angle (5 places et +)', 25000, 1, 60]]);
        self::field($s, 'material', 'Matière', 'select', 'none', 2, [['Tissu', 0, 1, 0], ['Velours', 0, 1.15, 10], ['Cuir ou simili', 0, 1.2, 0]]);
        self::option($s, 'Traitement anti-taches', 3000, 10); self::option($s, 'Désinfection anti-acariens à la vapeur', 4000, 15);

        $s = $out['matelas'] = self::svc(['category_id' => $cat['textile'], 'required_skill_id' => $sk['textile'], 'name' => 'Nettoyage de matelas', 'slug' => 'nettoyage-matelas', 'sort' => 3,
            'short_description' => 'Dès 15 000 FCFA : acariens, taches et odeurs en moins.', 'base_duration' => 15,
            'description' => "Nettoyage en profondeur et à la vapeur pour retirer les saletés, réduire les acariens et retrouver un matelas frais. Utilisable le soir même.",
            'conditions' => "Prévoir un accès au matelas et une prise électrique."]);
        self::field($s, 'size', 'Dimensions', 'select', 'none', 1, [['1 place (90 cm)', 15000, 1, 30], ['2 places (140 cm)', 20000, 1, 40], ['Grand lit (160 à 180 cm)', 25000, 1, 50]]);
        self::field($s, 'count', 'Nombre de matelas', 'number', 'quantity', 2, null, 1, 10);
        self::option($s, 'Traitement anti-acariens renforcé', 3000, 15);

        $s = $out['tapis'] = self::svc(['category_id' => $cat['textile'], 'required_skill_id' => $sk['textile'], 'name' => 'Nettoyage de tapis et moquettes', 'slug' => 'nettoyage-tapis-moquettes', 'sort' => 4,
            'short_description' => 'Dès 2 500 FCFA le m², sur place.', 'billing_unit' => 'per_sqm', 'base_duration' => 30, 'duration_per_unit' => 3,
            'description' => "Shampoing et extraction sur place pour tapis et moquettes. Le prix dépend de la surface : mesurez-la ou envoyez-nous une photo.",
            'conditions' => "Dégager l'accès et retirer les objets posés sur le tapis ou la moquette."]);
        self::formula($s, 'Tapis', 'Tarif au m²', 2500, 0, 1); self::formula($s, 'Moquette', 'Tarif au m²', 2000, 0, 2);
        self::field($s, 'area', 'Surface totale (m²)', 'number', 'area', 1, null, 1, 300, 'Mesurez la surface à nettoyer, en m².');
        self::option($s, 'Traitement anti-taches', 5000, 15);

        $s = $out['maison'] = self::svc(['category_id' => $cat['maison'], 'required_skill_id' => $sk['maison'], 'name' => 'Ménage à domicile', 'slug' => 'menage-domicile', 'featured' => 1, 'sort' => 5,
            'short_description' => 'Entretien courant, grand ménage ou remise en état.', 'base_duration' => 30,
            'description' => "Du ménage régulier au grand nettoyage. Le niveau de nettoyage et le type de logement déterminent la durée et le prix.",
            'conditions' => "Prévoir l'accès à l'eau et à l'électricité."]);
        self::formula($s, 'Standard', 'Entretien courant', 8000, 90, 1, "Sols et poussières\nCuisine et sanitaires\nVidage des poubelles", true);
        self::formula($s, 'Approfondi', 'Grand ménage', 16000, 150, 2, "Tout le Standard\nIntérieur des placards\nDétartrage des sanitaires\nPlacards de cuisine");
        self::formula($s, 'Remise en état', 'Déménagement ou arrivée', 30000, 210, 3);
        self::field($s, 'housing', 'Type de logement', 'select', 'none', 1, [['Studio ou F1', 0, 1, 0], ['F2', 0, 1.4, 30], ['F3', 0, 1.8, 60], ['F4 et plus', 0, 2.3, 90], ['Villa', 0, 2.8, 120]]);
        self::option($s, 'Nettoyage des vitres', 5000, 30); self::option($s, 'Repassage (1 h)', 4000, 60); self::option($s, 'Intérieur du réfrigérateur et du four', 3000, 20);

        $s = $out['vitres'] = self::svc(['category_id' => $cat['maison'], 'required_skill_id' => $sk['chantier'], 'name' => 'Nettoyage de vitres', 'slug' => 'nettoyage-vitres', 'sort' => 6,
            'short_description' => 'Fenêtres, baies vitrées et vitrines, sans traces.', 'billing_unit' => 'per_item', 'base_duration' => 15,
            'description' => "Vitres nettoyées à l'intérieur et à l'extérieur, cadres essuyés. Les vitres en hauteur demandent un devis : envoyez-nous une photo.",
            'conditions' => "Accès extérieur sécurisé uniquement jusqu'au 1er étage ; au-delà, nous contacter."]);
        self::field($s, 'type', 'Type de vitre', 'select', 'none', 1, [['Fenêtre', 1500, 1, 8], ['Baie vitrée', 5000, 1, 20], ['Vitrine de boutique', 7000, 1, 25]]);
        self::field($s, 'count', 'Nombre de vitres', 'number', 'quantity', 2, null, 1, 60);

        $s = $out['bureaux'] = self::svc(['category_id' => $cat['pro'], 'required_skill_id' => $sk['maison'], 'name' => 'Nettoyage de bureaux et commerces', 'slug' => 'nettoyage-bureaux', 'sort' => 7,
            'short_description' => 'Open space, cabinets, boutiques : au m², ponctuel ou régulier.', 'billing_unit' => 'per_sqm', 'base_duration' => 30, 'duration_per_unit' => 1,
            'description' => "Sols, sanitaires, postes de travail, vitres intérieures. Intervention en dehors des heures d'ouverture possible sur demande. Contrat régulier : nous contacter pour un tarif mensuel.",
            'conditions' => "Prévoir l'accès aux locaux et à un point d'eau."]);
        self::formula($s, 'Standard', 'Prix au m²', 300, 0, 1); self::formula($s, 'Approfondi', 'Prix au m²', 500, 30, 2);
        self::field($s, 'area', 'Surface (m²)', 'number', 'area', 1, null, 10, 2000);
        self::field($s, 'premises', 'Type de locaux', 'select', 'none', 2, [['Open space', 0, 1, 0], ['Bureaux cloisonnés', 0, 1.1, 20], ['Boutique ou commerce', 0, 1.2, 20]]);
        self::field($s, 'frequency', 'Fréquence', 'select', 'none', 3, [['Ponctuel', 0, 1, 0], ['Hebdomadaire', 0, 0.9, 0], ['Quotidien', 0, 0.8, 0]]);
        self::option($s, 'Vitres intérieures et extérieures', 15000, 45);

        $s = $out['chantier'] = self::svc(['category_id' => $cat['pro'], 'required_skill_id' => $sk['chantier'], 'name' => 'Nettoyage après travaux', 'slug' => 'nettoyage-apres-travaux', 'sort' => 8,
            'short_description' => 'Remise en état après chantier ou rénovation, au m².', 'billing_unit' => 'per_sqm', 'base_duration' => 60, 'duration_per_unit' => 4,
            'description' => "Évacuation des poussières de chantier, décapage des projections de peinture et de ciment, sols, sanitaires, vitres. Un devis précis est confirmé après photos.",
            'conditions' => "Gravats et gros déchets évacués avant notre passage."]);
        self::formula($s, 'Standard', 'Prix au m²', 1000, 0, 1); self::formula($s, 'Approfondi', 'Prix au m²', 1500, 30, 2, "Tout le Standard\nDécapage des projections\nVitres et encadrements\nDésinfection finale");
        self::field($s, 'area', 'Surface (m²)', 'number', 'area', 1, null, 10, 2000);

        $s = $out['repassage'] = self::svc(['category_id' => $cat['maison'], 'required_skill_id' => $sk['maison'], 'name' => 'Repassage à domicile', 'slug' => 'repassage-domicile', 'sort' => 9,
            'short_description' => 'Dès 3 500 FCFA le panier de linge.', 'billing_unit' => 'per_item', 'base_duration' => 30,
            'description' => "Nous repassons votre linge chez vous et vous le rendons prêt à ranger ou à porter. Un panier correspond à un grand sac de linge (environ 15 pièces).",
            'conditions' => "Prévoir une table, une prise électrique et un fer si possible."]);
        self::formula($s, 'Panier de linge', 'Environ 15 pièces', 3500, 60, 1);
        self::field($s, 'count', 'Nombre de paniers', 'number', 'quantity', 1, null, 1, 10);
        return $out;
    }

    /** Quatre équipes, leurs horaires, et deux agents « modèles » par équipe (à renommer) : sans agent, aucun créneau n'est proposé. */
    private static function teams(array $svc, array $sk): int
    {
        $defs = [
            ['Équipe Auto', 6, 'Camionnette citerne', ['auto'], ['auto']],
            ['Équipe Textile', 4, 'Fourgon injection-extraction', ['canape', 'matelas', 'tapis'], ['textile']],
            ['Équipe Maison', 3, 'Utilitaire', ['maison', 'repassage', 'vitres'], ['maison', 'chantier']],
            ['Équipe Bureaux et chantiers', 3, 'Utilitaire', ['bureaux', 'chantier', 'vitres'], ['maison', 'chantier']],
        ];
        foreach ($defs as [$name, $cap, $veh, $services, $skills]) {
            $t = DB::insert('teams', ['name' => $name, 'daily_capacity' => $cap, 'vehicle' => $veh, 'status' => 'active']);
            foreach ($services as $k) DB::insert('team_services', ['team_id' => $t, 'service_id' => $svc[$k]]);
            for ($d = 1; $d <= 6; $d++) DB::insert('working_hours', ['team_id' => $t, 'day_of_week' => $d, 'start_time' => '08:00', 'end_time' => '18:00', 'break_start' => '12:30', 'break_end' => '14:00']);
            foreach ([1, 2] as $i) {
                $st = DB::insert('staff', ['team_id' => $t, 'name' => 'Agent ' . $i . ' - ' . $name . ' (à renommer)', 'status' => 'active']);
                foreach ($skills as $k) DB::insert('staff_skills', ['staff_id' => $st, 'skill_id' => $sk[$k]]);
            }
        }
        return count($defs);
    }
}
