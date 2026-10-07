<section class="auth-shell"><section class="auth card">
<div><span class="eyebrow">НОВИЙ ПАРОЛЬ</span><h1>Підтвердження</h1><p class="muted">Введіть код із листа та новий пароль.</p></div>
<?php if(!empty($dev_code)): ?><div class="devcode">Локальний XAMPP-код: <b><?=e($dev_code)?></b></div><?php endif; ?>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?=csrf()?>"><label>Код<input name="code" inputmode="numeric" maxlength="6" required></label><label>Новий пароль<input name="password" type="password" minlength="6" required></label><button class="btn primary wide">Змінити пароль</button></form>
</section></section>