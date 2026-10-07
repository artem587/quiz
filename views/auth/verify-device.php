<section class="auth-shell"><section class="auth card">
<span class="eyebrow">БЕЗОПАСНОСТЬ</span><h1>Подтвердите устройство</h1>
<p class="muted">Мы отправили 6-значный код на ваш email. Устройство будет запомнено на 180 дней.</p>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?=csrf()?>">
<label>Код<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus></label>
<button class="btn primary wide">Подтвердить</button></form>
<?php if($dev_code): ?><div class="flash">Локальная разработка: код <?=$dev_code?></div><?php endif; ?>
<div class="links"><a href="<?=url('login')?>">Назад</a></div>
</section></section>