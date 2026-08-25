<?php
use Onay\App\Kernel\Config;
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '') ?> · Yonetim</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="yonetim">
<header class="ust">
  <a class="marka" href="/yonetim"><?= e(Config::get('app.name', '')) ?> · Yonetim</a>
  <nav>
    <a href="/yonetim/havaleler">Havaleler</a>
    <a href="/yonetim/siparisler">Siparisler</a>
    <a href="/yonetim/katalog">Katalog</a>
    <a href="/yonetim/kullanicilar">Kullanicilar</a>
    <a href="/panel">Musteri paneli</a>
  </nav>
</header>

<main class="kapsayici genis">
  <?php foreach (($flash ?? []) as $mesaj): ?>
    <div class="uyari <?= e($mesaj['type']) ?>"><?= e($mesaj['message']) ?></div>
  <?php endforeach; ?>
  <h1><?= e($title ?? '') ?></h1>
  <?= $content ?>
</main>
</body>
</html>
