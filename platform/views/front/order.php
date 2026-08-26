<?php use Onay\App\Kernel\Csrf; ?>
<h1>Siparis #<?= (int) $order['id'] ?></h1>

<div class="kartlar">
  <div class="kart">
    <span class="etiket">Numara</span>
    <strong class="buyuk tekaralik" id="telefon"><?= e($order['phone']) ?></strong>
  </div>
  <div class="kart">
    <span class="etiket">Dogrulama kodu</span>
    <strong class="buyuk" id="kod"><?= $order['code'] === null ? 'Bekleniyor…' : e($order['code']) ?></strong>
  </div>
  <div class="kart">
    <span class="etiket">Kalan sure</span>
    <strong class="buyuk" id="sure">—</strong>
  </div>
</div>

<p>Durum: <span class="durum <?= e($order['status']) ?>" id="durum"><?= e(durumAdi($order['status'])) ?></span></p>

<div id="mesajlar">
  <?php foreach ($messages as $mesaj): ?>
    <blockquote><strong><?= e($mesaj['sender']) ?></strong> <?= e($mesaj['body']) ?></blockquote>
  <?php endforeach; ?>
</div>

<div class="satir" id="eylemler">
  <form method="post" action="/siparis/<?= (int) $order['id'] ?>/tamamla">
    <?= Csrf::field() ?>
    <button type="submit" class="dugme">Kodu aldim, tamamla</button>
  </form>
  <form method="post" action="/siparis/<?= (int) $order['id'] ?>/iptal">
    <?= Csrf::field() ?>
    <button type="submit" class="dugme ikincil">Iptal et ve iade al</button>
  </form>
</div>

<script>
// Kuyruk isciisi yok; tarayici sunucuyu yokluyor, sunucu saglayiciya soruyor.
(function () {
  var id = <?= (int) $order['id'] ?>;
  var bitti = ['completed', 'cancelled', 'expired', 'refunded'];
  // Sunucudaki durumAdi() ile ayni karsiliklar: ekranda kod degil metin gorunsun.
  var durumAdlari = {
    waiting_sms: 'SMS bekleniyor', received: 'SMS geldi', completed: 'Tamamlandi',
    cancelled: 'Iptal edildi', expired: 'Sure doldu, iade edildi', refunded: 'Iade edildi'
  };
  var kalan = <?= max(0, strtotime((string) $order['expires_at']) - time()) ?>;

  function sureYaz() {
    var dk = Math.floor(kalan / 60), sn = kalan % 60;
    document.getElementById('sure').textContent = kalan > 0 ? dk + ':' + String(sn).padStart(2, '0') : 'doldu';
  }
  sureYaz();
  setInterval(function () { if (kalan > 0) { kalan--; sureYaz(); } }, 1000);

  function yokla() {
    fetch('/siparis/' + id + '/durum', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.error) { return; }
        var durumEl = document.getElementById('durum');
        durumEl.textContent = durumAdlari[d.status] || d.status;
        durumEl.className = 'durum ' + d.status;
        if (d.code) { document.getElementById('kod').textContent = d.code; }
        if (typeof d.expiresIn === 'number') { kalan = d.expiresIn; }

        if (d.messages && d.messages.length) {
          document.getElementById('mesajlar').innerHTML = d.messages.map(function (m) {
            return '<blockquote><strong>' + (m.sender || '') + '</strong> ' + m.body + '</blockquote>';
          }).join('');
        }

        if (bitti.indexOf(d.status) === -1) { setTimeout(yokla, 3000); }
        else { document.getElementById('eylemler').style.display = 'none'; }
      })
      .catch(function () { setTimeout(yokla, 8000); });
  }

  if (bitti.indexOf('<?= e($order['status']) ?>') === -1) { yokla(); }
  else { document.getElementById('eylemler').style.display = 'none'; }
})();
</script>
