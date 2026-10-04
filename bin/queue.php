<?php
// À planifier toutes les 5 minutes :  */5 * * * * php /chemin/bin/queue.php
require __DIR__ . '/../bootstrap.php';
$n = new App\Services\NotificationService();
echo date('c') . ' rappels : ' . $n->sendReminders() . ' | reprises réussies : ' . $n->retryDue() . PHP_EOL;
