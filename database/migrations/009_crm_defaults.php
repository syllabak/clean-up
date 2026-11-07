<?php
// Permissions CRM / gestion et canaux d'acquisition par défaut (indispensables en production).
use App\Core\Database;

return function (): void {
    $perms = ['crm.manage' => 'Gérer prospects et canaux (CRM)', 'finance.manage' => 'Gérer dépenses et rapports de gestion'];
    $pid = [];
    foreach ($perms as $n => $l) $pid[$n] = Database::insert('permissions', ['name' => $n, 'label' => $l]);
    $grant = [['admin', 'crm.manage'], ['admin', 'finance.manage'], ['manager', 'crm.manage']];
    foreach ($grant as [$role, $perm]) {
        $rid = Database::val('SELECT id FROM roles WHERE name = ?', [$role]);
        if ($rid) Database::insert('role_permissions', ['role_id' => $rid, 'permission_id' => $pid[$perm]]);
    }
    $channels = [
        ['web', 'Site web', 'organique', 1], ['contact', 'Formulaire de contact', 'organique', 0], ['whatsapp', 'WhatsApp', 'direct', 1], ['phone', 'Appel téléphonique', 'direct', 1],
        ['facebook', 'Facebook', 'reseaux', 1], ['instagram', 'Instagram', 'reseaux', 1], ['tiktok', 'TikTok', 'reseaux', 1], ['referral', 'Parrainage / bouche à oreille', 'recommandation', 1],
        ['flyers', 'Flyers / affichage', 'hors_ligne', 1], ['partner', 'Partenaire / entreprise', 'recommandation', 1], ['ads', 'Publicité en ligne', 'payant', 1], ['other', 'Autre', 'autre', 1],
    ];
    foreach ($channels as $i => [$code, $name, $kind, $public]) {
        Database::insert('acquisition_channels', ['company_id' => 1, 'code' => $code, 'name' => $name, 'kind' => $kind, 'public' => $public, 'active' => 1, 'sort' => $i]);
    }
};
