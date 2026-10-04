<?php
// Processus de test de concurrence : tente de réserver le même créneau à l'instant T.
define('TESTING', true);
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/TestCase.php';
[, $db, $date, $start, $i, $at] = $argv;
putenv('DB_DRIVER=sqlite'); putenv('DB_SQLITE_PATH=' . $db); putenv('MAIL_DRIVER=log'); putenv('SMS_DRIVER=log');
while (microtime(true) < (float) $at) usleep(200);
try {
    $in = wash_input($date, $start, 'Unité 15', ['phone' => '77000' . str_pad($i, 4, '0', STR_PAD_LEFT), 'first_name' => "Client$i"]);
    $b = (new App\Services\BookingService())->create($in);
    echo 'OK:' . $b['reference'];
} catch (App\Services\SlotUnavailableException $e) {
    echo 'TAKEN';
} catch (Throwable $e) {
    echo 'ERROR:' . get_class($e) . ':' . $e->getMessage();
}
