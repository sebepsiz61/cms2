<?php use Onay\App\Kernel\Csrf; ?>
<h1>Numara al</h1>

<div class="kartlar">
  <div class="kart">
    <span class="etiket">Bakiye</span>
    <strong class="buyuk"><?= para((int) $user['balance_minor']) ?></strong>
    <a href="<?= url('/bakiye') ?>">Bakiye yukle</a>
  </div>
</div>

<?php if ($countries === []): ?>
  <div class="uyari bilgi">
    Katalog henuz bos. Yonetici panelinden saglayici tanimlanip katalog senkronu calistirilmali.
  </div>
<?php else: ?>
  <form method="get" action="<?= url('/panel') ?>" class="satir">
    <label>Ulke
      <select name="ulke" onchange="this.form.submit()">
        <option value="">Secin</option>
        <?php foreach ($countries as $ulke): ?>
          <option value="<?= e($ulke['code']) ?>" <?= $selected === $ulke['code'] ? 'selected' : '' ?>>
            <?= e($ulke['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button type="submit" class="dugme kucuk">Goster</button></noscript>
  </form>

  <?php if ($selected !== null && $services !== []): ?>
    <div class="tablo-kaydir">
<table class="liste">
      <thead><tr><th>Servis</th><th>Stok</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($services as $servis): ?>
        <tr>
          <td><?= e($servis['name']) ?></td>
          <td><?= (int) $servis['stok'] ?></td>
          <td>
            <form method="post" action="<?= url('/siparis') ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="ulke" value="<?= e($selected) ?>">
              <input type="hidden" name="servis" value="<?= e($servis['code']) ?>">
              <button type="submit" class="dugme kucuk">Numara al</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php elseif ($selected !== null): ?>
    <p>Bu ulke icin su an stokta servis yok.</p>
  <?php endif; ?>
<?php endif; ?>

<h2>Son siparisleriniz</h2>
<?php if ($orders === []): ?>
  <p>Henuz siparisiniz yok.</p>
<?php else: ?>
  <div class="tablo-kaydir">
<table class="liste">
    <thead><tr><th>#</th><th>Numara</th><th>Ulke</th><th>Servis</th><th>Tutar</th><th>Durum</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $siparis): ?>
      <tr>
        <td><?= (int) $siparis['id'] ?></td>
        <td class="tekaralik"><?= e($siparis['phone']) ?></td>
        <td><?= e($siparis['country_name'] ?? $siparis['country_code']) ?></td>
        <td><?= e($siparis['service_name'] ?? $siparis['service_code']) ?></td>
        <td><?= para((int) $siparis['price_minor']) ?></td>
        <td><span class="durum <?= e($siparis['status']) ?>"><?= e(durumAdi($siparis['status'])) ?></span></td>
        <td><a href="<?= url('') ?>/siparis/<?= (int) $siparis['id'] ?>">Ac</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
