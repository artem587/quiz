<section class="auth-shell"><section class="auth card">
<div><span class="eyebrow">РЕЄСТРАЦІЯ</span><h1>Створити акаунт</h1><p class="muted">Нові користувачі реєструються як учні.</p></div>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?=csrf()?>">
<label>Ім'я<input name="name" required></label><label>Email<input name="email" type="email" required></label>
<label>Пароль<input name="password" type="password" minlength="6" required></label>
<button class="btn primary wide">Зареєструватися</button></form>
<div class="links"><a href="<?=url('login')?>">← Увійти</a></div>
</section></section>