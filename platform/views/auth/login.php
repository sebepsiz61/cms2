<div class="dar">
  <h1>Giris yap</h1>
  <form method="post" action="<?= url('/giris') ?>">
    <?= \Onay\App\Kernel\Csrf::field() ?>
    <label>E-posta
      <input type="email" name="email" required autofocus autocomplete="email">
    </label>
    <label>Sifre
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button type="submit" class="dugme">Giris yap</button>
  </form>
  <p>Hesabiniz yok mu? <a href="<?= url('/kayit') ?>">Kayit olun</a></p>
</div>
