<section class="kahraman">
  <h1>Tek kullanimlik sanal numara ile SMS onayi</h1>
  <p>Kendi numaranizi paylasmadan, dakikalar icinde dogrulama kodunuzu alin.
     Numara gelmezse ucret otomatik olarak bakiyenize iade edilir.</p>
  <p><a class="dugme" href="/kayit">Hesap olustur</a> <a class="dugme ikincil" href="/giris">Giris yap</a></p>
</section>

<section>
  <h2>Nasil calisir</h2>
  <ol class="adimlar">
    <li><strong>Bakiye yukleyin.</strong> Havale/EFT ile, referans kodunuzla.</li>
    <li><strong>Ulke ve servis secin.</strong> Fiyat ve stok anlik gosterilir.</li>
    <li><strong>Kodu alin.</strong> Gelen SMS ekranda belirir.</li>
    <li><strong>Gelmezse iade.</strong> Sure dolarsa ucret otomatik geri yuklenir.</li>
  </ol>
</section>

<?php if ($countries !== []): ?>
<section>
  <h2>Su an numara bulunan ulkeler</h2>
  <ul class="rozetler">
    <?php foreach ($countries as $ulke): ?>
      <li><?= e($ulke['name']) ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
