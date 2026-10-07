<section class="auth-shell">
<div class="auth-brand"><span class="logo large">Q</span><div><b>QuizSpace</b><small>Навчальна платформа</small></div></div>
<section class="auth card">
  <div><span class="eyebrow">ВХІД</span><h1>З поверненням 👋</h1><p class="muted">Увійдіть, щоб продовжити навчання.</p></div>
  <form method="post" class="form">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <label>Email<input name="email" type="email" autocomplete="email" placeholder="you@example.com" required></label>
    <label>Пароль<input name="password" type="password" autocomplete="current-password" placeholder="••••••••" required></label>
    <button class="btn primary wide">Увійти</button>
  </form>
  <div class="links"><a href="<?=url('register')?>">Створити акаунт</a><a href="<?=url('forgot')?>">Забули пароль?</a></div>
</section>
</section>