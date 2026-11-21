<?php
// Notifications client : un seul message, par email si le client en a donné un, sinon par SMS (SMS aussi en secours si l'email échoue).
// Le réglage est écrit explicitement pour apparaître coché dans Paramètres → Notifications.
use App\Core\Database;

return function (): void {
    if (Database::val('SELECT skey FROM settings WHERE company_id = 1 AND skey = ?', ['notif_prefer_email']) === null) {
        Database::insert('settings', ['company_id' => 1, 'skey' => 'notif_prefer_email', 'svalue' => '1']);
    }
};
