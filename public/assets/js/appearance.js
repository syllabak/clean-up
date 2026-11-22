/* Page Apparence : aperçu en direct et thèmes prêts à l'emploi. Le contraste du texte est recalculé comme côté serveur. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  function lum(h) {
    var c = [1, 3, 5].map(function (i) { var v = parseInt(h.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  var textOn = function (bg) { return lum(bg) > 0.4 ? '#0B2545' : '#FFFFFF'; };
  function refresh() {
    var dark = $('theme_dark').value, pri = $('theme_primary').value, acc = $('theme_accent').value;
    $('ap-preview').style.setProperty('--d', dark);
    document.querySelector('.ap-title').style.color = dark;
    document.querySelector('.ap-link').style.color = pri;
    $('ap-b1').style.background = pri; $('ap-b1').style.color = textOn(pri);
    $('ap-b2').style.background = acc; $('ap-b2').style.color = textOn(acc);
    $('ap-chat').style.background = $('cb_bg').value; $('ap-chat').style.color = $('cb_color').value; $('ap-dot').style.background = $('cb_dot').value;
    $('ap-chat-t').textContent = $('cb_label').value.trim() || $('cb_label').placeholder;
  }
  document.querySelectorAll('.ap-preset').forEach(function (b) {
    b.addEventListener('click', function () {
      $('theme_dark').value = b.dataset.dark; $('theme_primary').value = b.dataset.primary; $('theme_accent').value = b.dataset.accent;
      $('cb_bg').value = b.dataset.dark; $('cb_dot').value = b.dataset.accent; refresh();
    });
  });
  document.querySelectorAll('#appearance input').forEach(function (i) { i.addEventListener('input', refresh); });
  document.querySelectorAll('[data-confirm]').forEach(function (b) { b.addEventListener('click', function (e) { if (!window.confirm(b.dataset.confirm)) e.preventDefault(); }); });
  refresh();
})();
