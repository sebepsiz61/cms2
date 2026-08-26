<?php
use Onay\App\Kernel\Money;
use Onay\App\Service\PricingService;

$fiyatla = static function (array $servis): ?int {
    if (!isset($servis['min_cost'], $servis['currency'])) {
        return null;
    }
    try {
        return (new PricingService())->sellPriceMinor(
            (int) $servis['min_cost'], (string) $servis['currency'], '', (string) $servis['code']
        );
    } catch (\Throwable $e) {
        return null;
    }
};
?>

<section class="kahraman">
  <div class="kahraman-metin">
    <p class="ust-etiket">Tek kullanımlık numara</p>
    <h1><?= e($ayar->get('site_tagline')) ?></h1>
    <p class="giris"><?= e($ayar->get('site_description')) ?></p>
    <p class="kahraman-eylem">
      <a class="dugme buyuk-dugme" href="<?= url('/kayit') ?>">Hemen başla</a>
      <a class="dugme ikincil buyuk-dugme" href="<?= url('/giris') ?>">Giriş yap</a>
    </p>
    <ul class="guven">
      <li>SMS gelmezse ücret iade</li>
      <li>Kendi numaranı paylaşma</li>
      <li>Dakikalar içinde teslim</li>
    </ul>
  </div>

  <?php if ($vitrin !== []): ?>
  <div class="vitrin">
    <div class="vitrin-baslik">
      <span>Popüler servisler</span>
      <?php if ($vitrinUlke !== null): ?>
        <span class="vitrin-ulke"><?= e($vitrinUlke) ?></span>
      <?php endif; ?>
    </div>
    <ul class="vitrin-liste">
      <?php foreach ($vitrin as $servis): $fiyat = $fiyatla($servis); ?>
        <li>
          <span class="vitrin-ad"><?= e($servis['name']) ?></span>
          <span class="vitrin-stok"><?= (int) $servis['stok'] ?> adet</span>
          <span class="vitrin-fiyat"><?= $fiyat === null ? '—' : e(Money::format($fiyat)) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <a class="vitrin-alt" href="<?= url('/kayit') ?>">Tümünü gör &rarr;</a>
  </div>
  <?php endif; ?>
</section>

<section class="serit">
  <h2>Nasıl çalışır</h2>
  <ol class="adim-kartlari">
    <li><span class="adim-no">1</span><strong>Bakiye yükle</strong>
        <span>Havale/EFT ile, referans kodunla.</span></li>
    <li><span class="adim-no">2</span><strong>Ülke ve servis seç</strong>
        <span>Fiyat ve stok anlık gösterilir.</span></li>
    <li><span class="adim-no">3</span><strong>Kodu al</strong>
        <span>Gelen SMS ekranda belirir.</span></li>
    <li><span class="adim-no">4</span><strong>Gelmezse iade</strong>
        <span>Süre dolarsa ücret geri yüklenir.</span></li>
  </ol>
</section>

<?php if ($countries !== []): ?>
<section class="serit">
  <h2>Numara bulunan ülkeler</h2>
  <ul class="rozetler">
    <?php foreach ($countries as $ulke): ?>
      <li><?= e($ulke['name']) ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="serit">
  <h2>Sık sorulanlar</h2>
  <div class="sss">
    <details>
      <summary>SMS ne kadar sürede gelir?</summary>
      <p>Çoğu serviste bir dakika içinde. Süre dolana kadar gelmezse ücret otomatik iade edilir.</p>
    </details>
    <details>
      <summary>Numarayı tekrar kullanabilir miyim?</summary>
      <p>Hayır. Numaralar tek kullanımlıktır ve işlem bittikten sonra devre dışı kalır.</p>
    </details>
    <details>
      <summary>Bakiye nasıl yüklerim?</summary>
      <p>Havale/EFT ile. Talep oluşturduğunuzda size özel bir referans kodu verilir;
         havale açıklamasına bu kodu yazmanız gerekir.</p>
    </details>
    <details>
      <summary>Kod gelmezse ücret alınıyor mu?</summary>
      <p>Hayır. Verilmeyen hizmetin ücreti alınmaz; süre dolduğunda bakiyeniz geri yüklenir.</p>
    </details>
  </div>
</section>

<?php if ($yazilar !== []): ?>
<section class="serit">
  <h2>Blogdan</h2>
  <div class="yazilar">
    <?php foreach ($yazilar as $yazi): ?>
      <article class="yazi-ozet">
        <h3><a href="<?= url('/blog/' . $yazi['slug']) ?>"><?= e($yazi['title']) ?></a></h3>
        <?php if ($yazi['excerpt'] !== null && $yazi['excerpt'] !== ''): ?>
          <p><?= e($yazi['excerpt']) ?></p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <p><a href="<?= url('/blog') ?>">Tüm yazılar &rarr;</a></p>
</section>
<?php endif; ?>

<section class="cagri">
  <h2>Numaranı paylaşmadan doğrula</h2>
  <p>Hesap açmak ücretsiz. Yalnızca kullandığın numara kadar ödersin.</p>
  <a class="dugme buyuk-dugme" href="<?= url('/kayit') ?>">Ücretsiz hesap aç</a>
</section>
