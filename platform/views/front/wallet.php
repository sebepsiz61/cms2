<?php use Onay\App\Kernel\Csrf; ?>
<h1>Bakiye yukle</h1>

<div class="kartlar">
  <div class="kart">
    <span class="etiket">Mevcut bakiye</span>
    <strong class="buyuk"><?= para((int) $user['balance_minor']) ?></strong>
  </div>
</div>

<section class="kutu">
  <h2>Havale / EFT bilgileri</h2>
  <dl>
    <dt>Alici</dt><dd><?= e($bank['account_name'] ?? '') ?></dd>
    <dt>Banka</dt><dd><?= e($bank['bank_name'] ?? '') ?></dd>
    <dt>IBAN</dt><dd class="tekaralik"><?= e($bank['iban'] ?? '') ?></dd>
  </dl>
  <p><?= e($bank['note'] ?? '') ?></p>
</section>

<form method="post" action="/bakiye" enctype="multipart/form-data" class="dar">
  <?= Csrf::field() ?>
  <label>Yuklemek istediginiz tutar
    <input type="text" name="tutar" placeholder="250,00" required>
  </label>
  <label>Dekont <small>istege bagli · JPG, PNG veya PDF</small>
    <input type="file" name="dekont" accept=".jpg,.jpeg,.png,.pdf">
  </label>
  <button type="submit" class="dugme">Talep olustur</button>
</form>

<h2>Taleplerim</h2>
<?php if ($payments === []): ?>
  <p>Henuz talebiniz yok.</p>
<?php else: ?>
  <div class="tablo-kaydir">
<table class="liste">
    <thead><tr><th>#</th><th>Tutar</th><th>Referans kodu</th><th>Durum</th><th>Tarih</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $talep): ?>
      <tr>
        <td><?= (int) $talep['id'] ?></td>
        <td><?= para((int) $talep['amount_minor']) ?></td>
        <td class="tekaralik"><?= e($talep['reference_code']) ?></td>
        <td><span class="durum <?= e($talep['status']) ?>"><?= e(durumAdi($talep['status'])) ?></span></td>
        <td><?= e($talep['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
