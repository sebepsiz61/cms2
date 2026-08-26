<?php use Onay\App\Kernel\Csrf; ?>
<div class="satir">
  <form method="post" action="<?= url('/yonetim/katalog/senkron') ?>">
    <?= Csrf::field() ?>
    <button type="submit" class="dugme">Katalogu simdi senkronla</button>
  </form>
  <span class="not">Stokta <?= $offerCount ?> teklif var.</span>
</div>

<h2>Eslenmemis saglayici kodlari</h2>
<?php if ($unmapped === []): ?>
  <p>Eslenmemis kod yok.</p>
<?php else: ?>
  <p class="not">Bu kodlar saglayicidan geldi ama karsiligi tanimli degil, bu yuzden katalogda gorunmuyorlar.</p>
  <div class="tablo-kaydir">
<table class="liste">
    <thead><tr><th>Saglayici</th><th>Tur</th><th>Saglayici kodu</th><th>Bizim kodumuz</th></tr></thead>
    <tbody>
    <?php foreach ($unmapped as $kod): ?>
      <tr>
        <td><?= e($kod['provider']) ?></td>
        <td><?= e($kod['kind']) ?></td>
        <td class="tekaralik"><?= e($kod['provider_code']) ?></td>
        <td>
          <form method="post" action="<?= url('/yonetim/katalog/esle') ?>" class="satir">
            <?= Csrf::field() ?>
            <input type="hidden" name="provider" value="<?= e($kod['provider']) ?>">
            <input type="hidden" name="kind" value="<?= e($kod['kind']) ?>">
            <input type="hidden" name="provider_code" value="<?= e($kod['provider_code']) ?>">
            <select name="canonical_code" required>
              <option value="">Secin</option>
              <?php foreach (($kod['kind'] === 'country' ? $countries : $services) as $hedef): ?>
                <option value="<?= e($hedef['code']) ?>"><?= e($hedef['name']) ?> (<?= e($hedef['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="dugme kucuk">Esle</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<div class="ikili">
  <section>
    <h2>Ulkeler</h2>
    <form method="post" action="<?= url('/yonetim/katalog/ulke') ?>" class="satir">
      <?= Csrf::field() ?>
      <input type="text" name="code" placeholder="GB" maxlength="8" required>
      <input type="text" name="name" placeholder="Ingiltere" required>
      <button type="submit" class="dugme kucuk">Ekle</button>
    </form>
    <ul class="rozetler">
      <?php foreach ($countries as $ulke): ?>
        <li><?= e($ulke['name']) ?> <code><?= e($ulke['code']) ?></code></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section>
    <h2>Servisler</h2>
    <form method="post" action="<?= url('/yonetim/katalog/servis') ?>" class="satir">
      <?= Csrf::field() ?>
      <input type="text" name="code" placeholder="whatsapp" maxlength="40" required>
      <input type="text" name="name" placeholder="WhatsApp" required>
      <button type="submit" class="dugme kucuk">Ekle</button>
    </form>
    <ul class="rozetler">
      <?php foreach ($services as $servis): ?>
        <li><?= e($servis['name']) ?> <code><?= e($servis['code']) ?></code></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
