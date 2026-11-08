<h1>Recherche</h1>
<form method="get" action="/admin/recherche" class="d-flex gap-2 mb-3"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Référence, nom, téléphone ou email" aria-label="Recherche" autofocus><button class="btn btn-primary">Chercher</button></form>
<?php if (mb_strlen($q) < 2): ?><p class="text-muted">Tapez au moins 2 caractères : une référence (CU-…), un nom, un numéro de téléphone ou un email.</p>
<?php elseif (!$bookings && !$customers && !$leads): ?><p class="text-muted">Aucun résultat pour « <?= e($q) ?> ».</p><?php endif ?>
<?php if ($bookings): ?><div class="panel"><h2>Réservations</h2><div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
  <?php foreach ($bookings as $b): ?><tr><td><a href="/admin/reservations/<?= (int) $b['id'] ?>"><?= e($b['reference']) ?></a></td><td class="text-nowrap"><?= e(date('d/m/Y', strtotime($b['scheduled_date']))) ?> <?= e($b['start_time']) ?></td>
    <td><?= e($b['customer_first_name'] . ' ' . $b['customer_last_name']) ?></td><td><?= e($b['service_name']) ?></td><td><?= status_badge($b['status']) ?></td><td class="text-nowrap text-end"><?= confirm_button($b) ?> <?= contact_buttons($b['customer_phone'], booking_message($b)) ?></td></tr><?php endforeach ?>
</tbody></table></div></div><?php endif ?>
<?php if ($customers): ?><div class="panel"><h2>Clients</h2><div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
  <?php foreach ($customers as $c): ?><tr><td><a href="/admin/clients/<?= (int) $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></a></td><td><?= e(phone_fmt($c['phone'])) ?></td><td><?= e((string) $c['email']) ?></td><td class="text-nowrap text-end"><?= contact_buttons($c['phone']) ?></td></tr><?php endforeach ?>
</tbody></table></div></div><?php endif ?>
<?php if ($leads): ?><div class="panel"><h2>Prospects</h2><div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
  <?php foreach ($leads as $l): ?><tr><td><a href="/admin/crm/prospects/<?= (int) $l['id'] ?>"><?= e($l['name']) ?></a></td><td><?= e(phone_fmt((string) $l['phone'])) ?></td><td><?= \App\Services\CrmService::statusBadge($l['status']) ?></td><td class="text-nowrap text-end"><?= $l['phone'] ? contact_buttons($l['phone']) : '' ?></td></tr><?php endforeach ?>
</tbody></table></div></div><?php endif ?>
