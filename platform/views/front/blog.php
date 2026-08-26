<div class="blog-duzen">
  <main>
    <h1><?= $kategori === null ? 'Blog' : e($kategori['name']) ?></h1>
    <?php if ($yazilar === []): ?>
      <p>Henuz yazi yok.</p>
    <?php else: ?>
      <div class="yazilar">
        <?php foreach ($yazilar as $yazi): ?>
          <article class="yazi-ozet">
            <?php if ($yazi['category_name'] !== null): ?>
              <a class="kategori-etiket" href="/blog/kategori/<?= e($yazi['category_slug']) ?>"><?= e($yazi['category_name']) ?></a>
            <?php endif; ?>
            <h2><a href="/blog/<?= e($yazi['slug']) ?>"><?= e($yazi['title']) ?></a></h2>
            <?php if ($yazi['excerpt'] !== null && $yazi['excerpt'] !== ''): ?>
              <p><?= e($yazi['excerpt']) ?></p>
            <?php endif; ?>
            <span class="tarih"><?= e(substr((string) ($yazi['published_at'] ?? $yazi['created_at']), 0, 10)) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <aside>
    <h2>Kategoriler</h2>
    <ul class="yan-liste">
      <li><a href="/blog">Tumu</a></li>
      <?php foreach ($kategoriler as $k): ?>
        <li>
          <a href="/blog/kategori/<?= e($k['slug']) ?>"><?= e($k['name']) ?></a>
          <span class="sayi"><?= (int) $k['post_count'] ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </aside>
</div>
