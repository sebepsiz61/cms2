<div class="dar">
  <h1>Kayit ol</h1>
  <form method="post" action="<?= url('/kayit') ?>">
    <?= \Onay\App\Kernel\Csrf::field() ?>
    <label>Ad soyad
      <input type="text" name="name" required autofocus>
    </label>
    <label>E-posta
      <input type="email" name="email" required autocomplete="email">
    </label>
    <label>Sifre <small>en az 8 karakter</small>
      <input type="password" name="password" required minlength="8" autocomplete="new-password">
    </label>
    <button type="submit" class="dugme">Hesap olustur</button>
  </form>
  <p>Zaten uye misiniz? <a href="<?= url('/giris') ?>">Giris yapin</a></p>
</div>
