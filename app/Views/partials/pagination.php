<?php if ($pages > 1):
  $qs = $_GET; unset($qs['page']);
  $link = fn(int $p) => '?' . http_build_query($qs + ['page' => $p]);
?>
<nav aria-label="Pagination"><ul class="pagination pagination-sm justify-content-center my-3">
  <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($link(max(1, $page - 1))) ?>">Précédent</a></li>
  <li class="page-item disabled"><span class="page-link">Page <?= $page ?> / <?= $pages ?></span></li>
  <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($link(min($pages, $page + 1))) ?>">Suivant</a></li>
</ul></nav>
<?php endif ?>
