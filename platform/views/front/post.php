<article class="icerik">
  <?php if ($yazi['category_name'] !== null): ?>
    <a class="kategori-etiket" href="/blog/kategori/<?= e($yazi['category_slug']) ?>"><?= e($yazi['category_name']) ?></a>
  <?php endif; ?>
  <h1><?= e($yazi['title']) ?></h1>
  <p class="tarih"><?= e(substr((string) ($yazi['published_at'] ?? $yazi['created_at']), 0, 10)) ?></p>
  <?= $yazi['content'] ?>
</article>

<?php if ($digerleri !== []): ?>
  <section>
    <h2>Diger yazilar</h2>
    <div class="yazilar">
      <?php foreach ($digerleri as $d): ?>
        <article class="yazi-ozet">
          <h3><a href="/blog/<?= e($d['slug']) ?>"><?= e($d['title']) ?></a></h3>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<p><a href="/blog">&larr; Tum yazilar</a></p>
