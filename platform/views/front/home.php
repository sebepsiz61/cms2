<section class="kahraman">
  <h1><?= e($ayar->get('site_tagline')) ?></h1>
  <p><?= e($ayar->get('site_description')) ?></p>
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

<?php if ($yazilar !== []): ?>
<section>
  <h2>Blogdan</h2>
  <div class="yazilar">
    <?php foreach ($yazilar as $yazi): ?>
      <article class="yazi-ozet">
        <h3><a href="/blog/<?= e($yazi['slug']) ?>"><?= e($yazi['title']) ?></a></h3>
        <?php if ($yazi['excerpt'] !== null && $yazi['excerpt'] !== ''): ?>
          <p><?= e($yazi['excerpt']) ?></p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <p><a href="/blog">Tum yazilar &rarr;</a></p>
</section>
<?php endif; ?>
