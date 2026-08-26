<?php use Onay\App\Kernel\Csrf; ?>
<p><a class="dugme" href="<?= url('/yonetim/sayfalar/yeni') ?>">Yeni sayfa</a></p>

<?php if ($sayfalar === []): ?>
  <p>Henuz sayfa yok. "Hakkimizda", "SSS", "Mesafeli satis sozlesmesi" gibi sayfalarla baslayin.</p>
<?php else: ?>
  <div class="tablo-kaydir">
  <table class="liste">
    <thead><tr><th>Baslik</th><th>Adres</th><th>Durum</th><th>Menu</th><th>Islem</th></tr></thead>
    <tbody>
    <?php foreach ($sayfalar as $s): ?>
      <tr>
        <td><?= e($s['title']) ?></td>
        <td class="tekaralik">/sayfa/<?= e($s['slug']) ?></td>
        <td><span class="durum <?= $s['status'] === 'published' ? 'completed' : 'pending' ?>">
          <?= $s['status'] === 'published' ? 'Yayinda' : 'Taslak' ?></span></td>
        <td><?= $s['show_in_menu'] ? 'Menude (' . (int) $s['menu_order'] . ')' : '—' ?></td>
        <td class="satir">
          <a class="dugme kucuk" href="<?= url('') ?>/yonetim/sayfalar/<?= (int) $s['id'] ?>">Duzenle</a>
          <?php if ($s['status'] === 'published'): ?>
            <a class="dugme kucuk ikincil" href="<?= url('') ?>/sayfa/<?= e($s['slug']) ?>" target="_blank" rel="noopener">Gor</a>
          <?php endif; ?>
          <form method="post" action="<?= url('') ?>/yonetim/sayfalar/<?= (int) $s['id'] ?>/sil"
                onsubmit="return confirm('Bu sayfa silinsin mi?')">
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
