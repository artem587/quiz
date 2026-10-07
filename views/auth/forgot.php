<section class="auth-shell"><section class="auth card">
<div><span class="eyebrow">ВІДНОВЛЕННЯ</span><h1>Забули пароль?</h1><p class="muted">Введіть email. Ми надішлемо 6-значний код.</p></div>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?=csrf()?>"><label>Email<input name="email" type="email" required></label><button class="btn primary wide">Отримати код</button></form>
<div class="links"><a href="<?=url('login')?>">← Назад</a></div>
</section></section>