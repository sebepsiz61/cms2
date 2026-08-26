<?php
use Onay\App\Repository\SettingsRepository;

$ayar = new SettingsRepository();
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '') ?> · Yonetim</title>
<link rel="stylesheet" href="<?= asset('/assets/style.css') ?>">
</head>
<body class="yonetim">
<header class="ust">
  <a class="marka" href="<?= url('/yonetim') ?>"><?= e($ayar->get('site_title')) ?> · Yonetim</a>
  <nav>
    <a href="<?= url('/yonetim/havaleler') ?>">Havaleler</a>
    <a href="<?= url('/yonetim/siparisler') ?>">Siparisler</a>
    <a href="<?= url('/yonetim/katalog') ?>">Katalog</a>
    <a href="<?= url('/yonetim/kullanicilar') ?>">Kullanicilar</a>
    <a href="<?= url('/yonetim/sayfalar') ?>">Sayfalar</a>
    <a href="<?= url('/yonetim/yazilar') ?>">Blog</a>
    <a href="<?= url('/yonetim/ayarlar') ?>">Ayarlar</a>
    <a href="<?= url('/panel') ?>">Musteri paneli</a>
    <form method="post" action="<?= url('/cikis') ?>" class="satirici">
      <?= \Onay\App\Kernel\Csrf::field() ?>
      <button type="submit" class="baglanti">Cikis</button>
    </form>
  </nav>
</header>

<main class="kapsayici genis">
  <?php if (\Onay\App\Service\ProviderFactory::demoEtkin()): ?>
    <div class="uyari demo">
      <strong>DEMO KİPİ AÇIK.</strong> Satılan numaralar gerçek değildir, SMS'ler sahtedir.
      Gerçek satışa geçmeden önce <code>config/config.php</code> içinde demo sağlayıcıyı kapatın.
    </div>
  <?php endif; ?>

  <?php foreach (($flash ?? []) as $mesaj): ?>
    <div class="uyari <?= e($mesaj['type']) ?>"><?= e($mesaj['message']) ?></div>
  <?php endforeach; ?>
  <h1><?= e($title ?? '') ?></h1>
  <?= $content ?>
</main>
</body>
</html>
