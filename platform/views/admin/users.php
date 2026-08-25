<?php use Onay\App\Kernel\Csrf; ?>
<table class="liste">
  <thead><tr><th>#</th><th>Ad</th><th>E-posta</th><th>Bakiye</th><th>Rol / Durum</th><th>Bakiye duzeltme</th></tr></thead>
  <tbody>
  <?php foreach ($users as $kullanici): ?>
    <tr>
      <td><?= (int) $kullanici['id'] ?></td>
      <td><?= e($kullanici['name']) ?></td>
      <td><?= e($kullanici['email']) ?></td>
      <td><strong><?= para((int) $kullanici['balance_minor']) ?></strong></td>
      <td>
        <form method="post" action="/yonetim/kullanicilar/<?= (int) $kullanici['id'] ?>/guncelle" class="satir">
          <?= Csrf::field() ?>
          <select name="role">
            <?php foreach (['customer' => 'Musteri', 'reseller' => 'Bayi', 'admin' => 'Yonetici'] as $kod => $ad): ?>
              <option value="<?= $kod ?>" <?= $kullanici['role'] === $kod ? 'selected' : '' ?>><?= $ad ?></option>
            <?php endforeach; ?>
          </select>
          <select name="status">
            <option value="active" <?= $kullanici['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
            <option value="suspended" <?= $kullanici['status'] === 'suspended' ? 'selected' : '' ?>>Askida</option>
          </select>
          <button type="submit" class="dugme kucuk">Kaydet</button>
        </form>
      </td>
      <td>
        <form method="post" action="/yonetim/kullanicilar/<?= (int) $kullanici['id'] ?>/bakiye" class="satir">
          <?= Csrf::field() ?>
          <input type="text" name="tutar" placeholder="50,00" size="7" required>
          <select name="yon">
            <option value="credit">Ekle</option>
            <option value="debit">Dus</option>
          </select>
          <input type="text" name="not" placeholder="aciklama" size="12">
          <button type="submit" class="dugme kucuk ikincil">Isle</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="not">Bakiye duzeltmeleri defterde <code>adjust</code> hareketi olarak kalir; hicbir kayit silinmez.</p>
