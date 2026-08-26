<?php
use Onay\App\Kernel\Auth;
use Onay\App\Kernel\Config;
$user = Auth::user();
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '') ?> · <?= e(Config::get('app.name', 'Sanal Numara')) ?></title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="ust">
  <a class="marka" href="/"><?= e(Config::get('app.name', 'Sanal Numara')) ?></a>
  <nav>
    <?php if ($user !== null): ?>
      <a href="/panel">Panel</a>
      <a href="/bakiye">Bakiye <strong><?= para((int) $user['balance_minor']) ?></strong></a>
      <?php if (Auth::isAdmin()): ?><a href="/yonetim">Yonetim</a><?php endif; ?>
      <form method="post" action="/cikis" class="satirici">
        <?= \Onay\App\Kernel\Csrf::field() ?>
        <button type="submit" class="baglanti">Cikis</button>
      </form>
    <?php else: ?>
      <a href="/giris">Giris</a>
      <a href="/kayit" class="dugme kucuk">Kayit Ol</a>
    <?php endif; ?>
  </nav>
</header>

<main class="kapsayici">
  <?php if (\Onay\App\Service\ProviderFactory::demoEtkin()): ?>
    <div class="uyari demo">
      <strong>DEMO KİPİ AÇIK.</strong> Satılan numaralar gerçek değildir, SMS'ler sahtedir.
      Gerçek satışa geçmeden önce <code>config/config.php</code> içinde demo sağlayıcıyı kapatın.
    </div>
  <?php endif; ?>

  <?php foreach (($flash ?? []) as $mesaj): ?>
    <div class="uyari <?= e($mesaj['type']) ?>"><?= e($mesaj['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>

<footer class="alt">
  <span><?= e(Config::get('app.name', '')) ?></span>
  <span>Tum tutarlar <?= e(Config::get('currency.code', 'TRY')) ?> cinsindendir.</span>
</footer>
</body>
</html>
