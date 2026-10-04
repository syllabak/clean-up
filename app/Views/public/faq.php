<div class="page-head"><div class="container"><h1>Questions fréquentes</h1></div></div>
<div class="container section" style="max-width:820px">
  <div class="accordion" id="faq">
    <?php foreach ($faq as $i => $q): ?>
    <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#f<?= $i ?>"><?= e($q['question']) ?></button></h2>
      <div id="f<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faq"><div class="accordion-body"><?= nl2br(e($q['answer'])) ?></div></div></div>
    <?php endforeach ?>
  </div>
</div>
