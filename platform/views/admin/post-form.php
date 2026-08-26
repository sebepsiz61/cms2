<?php use Onay\App\Kernel\Csrf; $y = $yazi ?? []; ?>
<form method="post" action="/yonetim/yazilar/<?= isset($y['id']) ? (int) $y['id'] : 'yeni' ?>" class="form-genis">
  <?= Csrf::field() ?>

  <label>Baslik
    <input type="text" name="title" required value="<?= e($y['title'] ?? '') ?>">
  </label>

  <label>Adres <small>bos birakirsan basliktan uretilir</small>
    <input type="text" name="slug" value="<?= e($y['slug'] ?? '') ?>" placeholder="sms-onay-nedir">
  </label>

  <label>Ozet <small>liste sayfasinda gorunur</small>
    <textarea name="excerpt" rows="3" maxlength="500"><?= e($y['excerpt'] ?? '') ?></textarea>
  </label>

  <label>Icerik <small>HTML kullanabilirsin</small>
    <textarea name="content" rows="18"><?= e($y['content'] ?? '') ?></textarea>
  </label>

  <label>Arama motoru aciklamasi
    <input type="text" name="meta_description" maxlength="255" value="<?= e($y['meta_description'] ?? '') ?>">
  </label>

  <div class="satir">
    <label style="margin:0">Kategori
      <select name="category_id">
        <option value="0">— yok —</option>
        <?php foreach ($kategoriler as $k): ?>
          <option value="<?= (int) $k['id'] ?>" <?= (int) ($y['category_id'] ?? 0) === (int) $k['id'] ? 'selected' : '' ?>>
            <?= e($k['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="margin:0">Durum
      <select name="status">
        <option value="draft" <?= ($y['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Taslak</option>
        <option value="published" <?= ($y['status'] ?? '') === 'published' ? 'selected' : '' ?>>Yayinda</option>
      </select>
    </label>
    <label style="margin:0">Yayin tarihi
      <input type="text" name="published_at" size="19"
             value="<?= e($y['published_at'] ?? date('Y-m-d H:i:s')) ?>">
    </label>
  </div>

  <div class="satir">
    <button type="submit" class="dugme">Kaydet</button>
    <a href="/yonetim/yazilar">Vazgec</a>
  </div>
</form>
