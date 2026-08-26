<?php
use Onay\App\Kernel\Auth;
use Onay\App\Repository\ContentRepository;
use Onay\App\Repository\SettingsRepository;

$user = Auth::user();
$ayar = new SettingsRepository();
$menuSayfalari = (new ContentRepository())->menuPages();
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '') ?> · <?= e($ayar->get('site_title')) ?></title>
<?php if (!empty($description)): ?>
<meta name="description" content="<?= e((string) $description) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
</head>
<body>
<header class="ust">
  <a class="marka" href="<?= url('/') ?>"><?= e($ayar->get('site_title')) ?></a>
  <nav>
    <a href="<?= url('/blog') ?>">Blog</a>
    <?php foreach ($menuSayfalari as $ms): ?>
      <a href="<?= url('') ?>/sayfa/<?= e($ms['slug']) ?>"><?= e($ms['title']) ?></a>
    <?php endforeach; ?>
    <?php if ($user !== null): ?>
      <a href="<?= url('/panel') ?>">Panel</a>
      <a href="<?= url('/bakiye') ?>">Bakiye <strong><?= para((int) $user['balance_minor']) ?></strong></a>
      <?php if (Auth::isAdmin()): ?><a href="<?= url('/yonetim') ?>">Yonetim</a><?php endif; ?>
      <form method="post" action="<?= url('/cikis') ?>" class="satirici">
        <?= \Onay\App\Kernel\Csrf::field() ?>
        <button type="submit" class="baglanti">Cikis</button>
      </form>
    <?php else: ?>
      <a href="<?= url('/giris') ?>">Giris</a>
      <a href="<?= url('/kayit') ?>" class="dugme kucuk">Kayit Ol</a>
    <?php endif; ?>
  </nav>
</header>

<?php if ($ayar->get('announcement') !== ''): ?>
  <div class="duyuru"><?= e($ayar->get('announcement')) ?></div>
<?php endif; ?>

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
  <div class="alt-blok">
    <strong><?= e($ayar->get('site_title')) ?></strong>
    <?php if ($ayar->get('footer_text') !== ''): ?>
      <span><?= e($ayar->get('footer_text')) ?></span>
    <?php endif; ?>
  </div>

  <div class="alt-blok">
    <?php foreach ($menuSayfalari as $ms): ?>
      <a href="<?= url('') ?>/sayfa/<?= e($ms['slug']) ?>"><?= e($ms['title']) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('/blog') ?>">Blog</a>
  </div>

  <div class="alt-blok">
    <?php if ($ayar->get('contact_email') !== ''): ?>
      <a href="mailto:<?= e($ayar->get('contact_email')) ?>"><?= e($ayar->get('contact_email')) ?></a>
    <?php endif; ?>
    <?php foreach (['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'instagram' => 'Instagram', 'twitter' => 'X'] as $anahtar => $etiket): ?>
      <?php if ($ayar->get($anahtar) !== ''): ?>
        <a href="<?= e($ayar->get($anahtar)) ?>" rel="noopener" target="_blank"><?= $etiket ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</footer>
</body>
</html>
