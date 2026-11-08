<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Database as DB, Env};

/** Outils du catalogue : duplication d'un service et contrôle de « prêt à être réservé ». */
final class CatalogService
{
    /** Copie un service (formules, options, champs et choix). La copie est créée inactive, à relire avant publication. @return int id */
    public function duplicate(int $id, ?int $userId = null): int
    {
        $s = DB::one('SELECT * FROM services WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [$id, Env::companyId()]) ?? throw new \RuntimeException('Service introuvable.');
        return DB::transaction(function () use ($s, $id, $userId) {
            $n = 2; $base = $s['slug'] . '-copie'; $slug = $base;
            while (DB::val('SELECT id FROM services WHERE company_id = ? AND slug = ?', [Env::companyId(), $slug])) $slug = $base . '-' . $n++;
            unset($s['id']);
            $new = DB::insert('services', array_merge($s, ['name' => mb_substr($s['name'], 0, 140) . ' (copie)', 'slug' => $slug, 'active' => 0, 'featured' => 0, 'created_at' => now(), 'updated_at' => null, 'deleted_at' => null]));
            foreach (['service_prices', 'service_options'] as $t) {
                foreach (DB::all("SELECT * FROM $t WHERE service_id = ? AND deleted_at IS NULL", [$id]) as $r) { unset($r['id']); DB::insert($t, array_merge($r, ['service_id' => $new])); }
            }
            foreach (DB::all('SELECT * FROM service_fields WHERE service_id = ? AND deleted_at IS NULL', [$id]) as $f) {
                $fid = (int) $f['id']; unset($f['id']);
                $nf = DB::insert('service_fields', array_merge($f, ['service_id' => $new]));
                foreach (DB::all('SELECT * FROM service_field_choices WHERE field_id = ? AND deleted_at IS NULL', [$fid]) as $c) { unset($c['id']); DB::insert('service_field_choices', array_merge($c, ['field_id' => $nf])); }
            }
            foreach (DB::all('SELECT * FROM service_images WHERE service_id = ?', [$id]) as $im) { unset($im['id']); DB::insert('service_images', array_merge($im, ['service_id' => $new])); }
            foreach (DB::all('SELECT team_id FROM team_services WHERE service_id = ?', [$id]) as $t) DB::insert('team_services', ['team_id' => $t['team_id'], 'service_id' => $new]);
            Audit::log('service.duplicate', 'service', $new, ['from' => $id], $userId);
            return $new;
        });
    }

    /**
     * Contrôles avant publication. Chaque élément : [niveau, message, lien]. Niveau : ok | warn | bad.
     * « bad » = le client ne pourra pas réserver ; « warn » = réservable mais incomplet.
     */
    public function readiness(int $id): array
    {
        $s = DB::one('SELECT * FROM services WHERE id = ?', [$id]);
        $out = [];
        $formulas = (int) DB::val('SELECT COUNT(*) FROM service_prices WHERE service_id = ? AND active = 1 AND deleted_at IS NULL', [$id]);
        // Un prix peut aussi venir des choix d'une liste (ex. canapé : 2 places = 10 000, 3 places = 15 000).
        $choicePriced = (int) DB::val("SELECT COUNT(*) FROM service_field_choices c JOIN service_fields f ON f.id = c.field_id WHERE f.service_id = ? AND f.active = 1 AND f.deleted_at IS NULL AND c.active = 1 AND c.deleted_at IS NULL AND c.price_delta > 0", [$id]);
        $priced = $formulas > 0 || (int) $s['base_price'] > 0 || $choicePriced > 0;
        $out[] = $priced ? ['ok', $formulas ? "$formulas formule(s) active(s)" : ((int) $s['base_price'] > 0 ? 'Prix de base défini' : 'Prix défini par les choix du formulaire'), null] : ['bad', "Aucun prix : ajoutez au moins une formule, un prix de base ou des choix avec un prix.", 'formules'];
        $teams = DB::all("SELECT t.id FROM teams t JOIN team_services ts ON ts.team_id = t.id WHERE ts.service_id = ? AND t.deleted_at IS NULL AND t.status = 'active'", [$id]);
        if (!$teams) {
            $out[] = ['bad', "Aucune équipe n'assure ce service : aucun créneau ne sera proposé. Cochez-le dans la fiche d'une équipe.", '/admin/crud/teams'];
        } else {
            $skill = (int) $s['required_skill_id'];
            if ($skill) {
                $teams = array_values(array_filter($teams, fn($t) => (int) DB::val("SELECT COUNT(*) FROM staff st JOIN staff_skills ss ON ss.staff_id = st.id WHERE st.team_id = ? AND st.status = 'active' AND st.deleted_at IS NULL AND ss.skill_id = ?", [$t['id'], $skill])));
            }
            if (!$teams) {
                $out[] = ['bad', 'Aucune équipe de ce service ne compte un agent ayant la compétence requise : aucun créneau ne sera proposé.', '/admin/crud/staff'];
            } else {
                $out[] = ['ok', count($teams) . ' équipe(s) en mesure de réaliser ce service', null];
                $in = implode(',', array_map('intval', array_column($teams, 'id')));
                // Un quartier sans équipe désignée est servi par toutes les équipes compétentes (voir AvailabilityService).
                $zones = (int) DB::val("SELECT COUNT(*) FROM neighborhoods n WHERE n.company_id = ? AND n.active = 1 AND n.deleted_at IS NULL AND (
                    NOT EXISTS (SELECT 1 FROM neighborhood_teams nt WHERE nt.neighborhood_id = n.id)
                    OR EXISTS (SELECT 1 FROM neighborhood_teams nt WHERE nt.neighborhood_id = n.id AND nt.team_id IN ($in)))", [Env::companyId()]);
                $out[] = $zones ? ['ok', "$zones quartier(s) desservis", null] : ['bad', "Aucun quartier n'est desservi par ces équipes.", '/admin/crud/neighborhoods'];
                if (!(int) DB::val("SELECT COUNT(*) FROM working_hours WHERE team_id IN ($in)")) $out[] = ['bad', "Les équipes concernées n'ont aucun horaire de travail.", '/admin/crud/teams'];
            }
        }
        $emptyFields = DB::all("SELECT f.label FROM service_fields f WHERE f.service_id = ? AND f.type = 'select' AND f.active = 1 AND f.deleted_at IS NULL
            AND NOT EXISTS (SELECT 1 FROM service_field_choices c WHERE c.field_id = f.id AND c.active = 1 AND c.deleted_at IS NULL)", [$id]);
        foreach ($emptyFields as $f) $out[] = ['bad', 'Le champ « ' . $f['label'] . ' » est une liste sans aucun choix.', 'champs'];
        $out[] = trim((string) $s['short_description']) !== '' ? ['ok', 'Résumé renseigné', null] : ['warn', 'Ajoutez un résumé court : il apparaît sur les cartes du site.', 'edit'];
        $out[] = trim((string) $s['description']) !== '' ? ['ok', 'Description renseignée', null] : ['warn', 'Ajoutez une description détaillée.', 'edit'];
        $out[] = ($s['image'] || DB::val('SELECT COUNT(*) FROM service_images WHERE service_id = ?', [$id])) ? ['ok', 'Photo ajoutée', null] : ['warn', 'Ajoutez une photo : les services illustrés sont plus réservés.', 'edit'];
        if ($formulas > 1 && !DB::val('SELECT COUNT(*) FROM service_prices WHERE service_id = ? AND active = 1 AND popular = 1 AND deleted_at IS NULL', [$id])) $out[] = ['warn', 'Aucune formule n’est mise en avant (« la plus choisie »).', 'formules'];
        return $out;
    }
}
