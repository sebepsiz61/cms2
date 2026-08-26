<?php use Onay\App\Kernel\Csrf; ?>
<p><a class="dugme" href="/yonetim/yazilar/yeni">Yeni yazi</a></p>

<div class="ikili">
  <section>
    <h2>Yazilar</h2>
    <?php if ($yazilar === []): ?>
      <p>Henuz yazi yok.</p>
    <?php else: ?>
      <div class="tablo-kaydir">
      <table class="liste">
        <thead><tr><th>Baslik</th><th>Kategori</th><th>Durum</th><th>Islem</th></tr></thead>
        <tbody>
        <?php foreach ($yazilar as $y): ?>
          <tr>
            <td><?= e($y['title']) ?><br><small class="tekaralik">/blog/<?= e($y['slug']) ?></small></td>
            <td><?= e($y['category_name'] ?? '—') ?></td>
            <td><span class="durum <?= $y['status'] === 'published' ? 'completed' : 'pending' ?>">
              <?= $y['status'] === 'published' ? 'Yayinda' : 'Taslak' ?></span></td>
            <td class="satir">
              <a class="dugme kucuk" href="/yonetim/yazilar/<?= (int) $y['id'] ?>">Duzenle</a>
              <form method="post" action="/yonetim/yazilar/<?= (int) $y['id'] ?>/sil"
                    onsubmit="return confirm('Bu yazi silinsin mi?')">
                <?= Csrf::field() ?>
                <button type="submit" class="dugme kucuk ikincil">Sil</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </section>

  <section>
    <h2>Kategoriler</h2>
    <form method="post" action="/yonetim/kategoriler" class="satir">
      <?= Csrf::field() ?>
      <input type="text" name="name" placeholder="Rehberler" required>
      <input type="text" name="sort_order" size="3" placeholder="0">
      <button type="submit" class="dugme kucuk">Ekle</button>
    </form>

    <?php if ($kategoriler !== []): ?>
      <ul class="yan-liste">
        <?php foreach ($kategoriler as $k): ?>
          <li>
            <span><?= e($k['name']) ?> <span class="sayi"><?= (int) $k['post_count'] ?></span></span>
            <form method="post" action="/yonetim/kategoriler/<?= (int) $k['id'] ?>/sil"
                  onsubmit="return confirm('Kategori silinsin mi? Yazilar kategorisiz kalir.')">
              <?= Csrf::field() ?>
              <button type="submit" class="baglanti">sil</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
