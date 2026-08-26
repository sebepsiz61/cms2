<?php use Onay\App\Kernel\Csrf; ?>
<?php if ($payments === []): ?>
  <p>Bekleyen havale talebi yok.</p>
<?php else: ?>
  <div class="tablo-kaydir">
<table class="liste">
    <thead><tr><th>#</th><th>Kullanici</th><th>Tutar</th><th>Referans</th><th>Dekont</th><th>Tarih</th><th>Islem</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $talep): ?>
      <tr>
        <td><?= (int) $talep['id'] ?></td>
        <td><?= e($talep['email']) ?></td>
        <td><strong><?= para((int) $talep['amount_minor']) ?></strong></td>
        <td class="tekaralik"><?= e($talep['reference_code']) ?></td>
        <td>
          <?php if ($talep['receipt_path'] !== null): ?>
            <a href="/yonetim/havaleler/<?= (int) $talep['id'] ?>/dekont" target="_blank" rel="noopener">Goruntule</a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><?= e($talep['created_at']) ?></td>
        <td class="satir">
          <form method="post" action="/yonetim/havaleler/<?= (int) $talep['id'] ?>/onayla">
            <?= Csrf::field() ?>
            <button type="submit" class="dugme kucuk">Onayla</button>
          </form>
          <form method="post" action="/yonetim/havaleler/<?= (int) $talep['id'] ?>/reddet">
            <?= Csrf::field() ?>
            <button type="submit" class="dugme kucuk ikincil">Reddet</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="not">
    Onaydan once havalenin hesaba gectigini dogrulayin. Ayni talep ikinci kez onaylanamaz;
    sistem bunu hem talep durumu hem defter anahtari ile engeller.
  </p>
<?php endif; ?>
