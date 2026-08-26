<?php use Onay\App\Kernel\Csrf; ?>
<form method="post" action="/yonetim/ayarlar" class="form-genis">
  <?= Csrf::field() ?>

  <h2>Site</h2>
  <label>Site adi<input type="text" name="site_title" value="<?= e($ayarlar['site_title']) ?>"></label>
  <label>Slogan <small>ana sayfadaki buyuk baslik</small>
    <input type="text" name="site_tagline" value="<?= e($ayarlar['site_tagline']) ?>"></label>
  <label>Aciklama <small>ana sayfa girisi ve arama motoru aciklamasi</small>
    <textarea name="site_description" rows="3"><?= e($ayarlar['site_description']) ?></textarea></label>
  <label>Duyuru <small>doldurulursa her sayfanin ustunde gosterilir</small>
    <input type="text" name="announcement" value="<?= e($ayarlar['announcement']) ?>"></label>

  <h2>Iletisim</h2>
  <div class="satir">
    <label style="margin:0">E-posta<input type="text" name="contact_email" value="<?= e($ayarlar['contact_email']) ?>"></label>
    <label style="margin:0">Telefon<input type="text" name="contact_phone" value="<?= e($ayarlar['contact_phone']) ?>"></label>
  </div>

  <h2>Sosyal</h2>
  <div class="satir">
    <label style="margin:0">WhatsApp<input type="text" name="whatsapp" value="<?= e($ayarlar['whatsapp']) ?>"></label>
    <label style="margin:0">Telegram<input type="text" name="telegram" value="<?= e($ayarlar['telegram']) ?>"></label>
    <label style="margin:0">X / Twitter<input type="text" name="twitter" value="<?= e($ayarlar['twitter']) ?>"></label>
    <label style="margin:0">Instagram<input type="text" name="instagram" value="<?= e($ayarlar['instagram']) ?>"></label>
  </div>

  <h2>Alt bilgi</h2>
  <label>Alt bilgi metni<input type="text" name="footer_text" value="<?= e($ayarlar['footer_text']) ?>"></label>

  <p><button type="submit" class="dugme">Kaydet</button></p>
</form>
