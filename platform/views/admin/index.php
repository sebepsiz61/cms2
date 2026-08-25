<div class="kartlar">
  <div class="kart"><span class="etiket">Ciro (tamamlanan)</span><strong class="buyuk"><?= para($ciro) ?></strong></div>
  <div class="kart"><span class="etiket">Tamamlanan siparis</span><strong class="buyuk"><?= $tamamlanan ?></strong></div>
  <div class="kart"><span class="etiket">Iade orani</span><strong class="buyuk"><?= $iadeOrani ?>%</strong>
    <small><?= $iadeAdet ?> iade</small></div>
  <div class="kart <?= $bekleyenOdeme > 0 ? 'dikkat' : '' ?>">
    <span class="etiket">Bekleyen havale</span><strong class="buyuk"><?= $bekleyenOdeme ?></strong>
    <a href="/yonetim/havaleler">Incele</a>
  </div>
  <div class="kart"><span class="etiket">Acik siparis</span><strong class="buyuk"><?= $acikSiparis ?></strong></div>
  <div class="kart <?= $eslenmemis > 0 ? 'dikkat' : '' ?>">
    <span class="etiket">Eslenmemis kod</span><strong class="buyuk"><?= $eslenmemis ?></strong>
    <a href="/yonetim/katalog">Esle</a>
  </div>
</div>

<p class="not">
  Iade orani yuksekse once saglayici basarisini kontrol edin: SMS gelmeyen numaralar hem musteriyi
  kaybettirir hem tedarikci tarafinda tahsil edilemeyen maliyet birakir.
</p>
