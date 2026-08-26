<?php use Onay\App\Kernel\Csrf; $y = $sayfa ?? []; ?>
<form method="post" action="/yonetim/sayfalar/<?= isset($y['id']) ? (int) $y['id'] : 'yeni' ?>" class="form-genis">
  <?= Csrf::field() ?>

  <label>Baslik
    <input type="text" name="title" required value="<?= e($y['title'] ?? '') ?>">
  </label>

  <label>Adres <small>bos birakirsan basliktan uretilir</small>
    <input type="text" name="slug" value="<?= e($y['slug'] ?? '') ?>" placeholder="hakkimizda">
  </label>

  <label>Icerik <small>HTML kullanabilirsin</small>
    <textarea name="content" rows="16"><?= e($y['content'] ?? '') ?></textarea>
  </label>

  <label>Arama motoru aciklamasi <small>en fazla 255 karakter</small>
    <input type="text" name="meta_description" maxlength="255" value="<?= e($y['meta_description'] ?? '') ?>">
  </label>

  <div class="satir">
    <label style="margin:0">Durum
      <select name="status">
        <option value="draft" <?= ($y['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Taslak</option>
        <option value="published" <?= ($y['status'] ?? '') === 'published' ? 'selected' : '' ?>>Yayinda</option>
      </select>
    </label>
    <label style="margin:0">Menu sirasi
      <input type="text" name="menu_order" size="4" value="<?= e((string) ($y['menu_order'] ?? 0)) ?>">
    </label>
    <label class="onay">
      <input type="checkbox" name="show_in_menu" value="1" <?= !empty($y['show_in_menu']) ? 'checked' : '' ?>>
      Ust menude goster
    </label>
  </div>

  <div class="satir">
    <button type="submit" class="dugme">Kaydet</button>
    <a href="/yonetim/sayfalar">Vazgec</a>
  </div>
</form>
