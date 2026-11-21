<?php
// Assistant de réservation (chatbot) : activé par défaut, désactivable dans Paramètres.
use App\Core\Database;

return function (): void {
    if (Database::val('SELECT skey FROM settings WHERE company_id = 1 AND skey = ?', ['chatbot_enabled']) === null) {
        Database::insert('settings', ['company_id' => 1, 'skey' => 'chatbot_enabled', 'svalue' => '1']);
    }
};
