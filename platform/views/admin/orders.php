<?php use Onay\App\Kernel\Csrf; ?>
<div class="tablo-kaydir">
<table class="liste">
  <thead><tr><th>#</th><th>Kullanici</th><th>Saglayici</th><th>Numara</th><th>Servis</th>
             <th>Maliyet</th><th>Satis</th><th>Durum</th><th>Islem</th></tr></thead>
  <tbody>
  <?php foreach ($orders as $siparis): ?>
    <tr>
      <td><?= (int) $siparis['id'] ?></td>
      <td><?= e($siparis['email']) ?></td>
      <td><?= e($siparis['provider']) ?></td>
      <td class="tekaralik"><?= e($siparis['phone']) ?></td>
      <td><?= e($siparis['country_name'] ?? $siparis['country_code']) ?> / <?= e($siparis['service_name'] ?? $siparis['service_code']) ?></td>
      <td><?= number_format($siparis['cost_minor'] / 100, 2, ',', '.') ?> <?= e($siparis['cost_currency']) ?></td>
      <td><?= para((int) $siparis['price_minor']) ?></td>
      <td><span class="durum <?= e($siparis['status']) ?>"><?= e(durumAdi($siparis['status'])) ?></span></td>
      <td>
        <?php if (in_array($siparis['status'], ['waiting_sms', 'received'], true)): ?>
          <form method="post" action="<?= url('') ?>/yonetim/siparisler/<?= (int) $siparis['id'] ?>/iade">
            <?= Csrf::field() ?>
            <button type="submit" class="dugme kucuk ikincil">Iade et</button>
          </form>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
