<div class="live-join-page">
<div class="live-join-card card">
<div class="live-logo">🎮 <b>QuizSpace Live</b></div>
<span class="eyebrow">ПРИЄДНАТИСЯ ДО ГРИ</span><h1><?=e($game['title'])?></h1><div class="live-pin big"><?=e($game['game_code'])?></div>
<p class="muted">Введіть ім'я та оберіть свою фігурку.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>">
<label>Ваше ім'я<input name="nickname" maxlength="30" required autofocus placeholder="Наприклад, Артем"></label>
<div class="avatar-picker"><b>Фігурка</b><div><?php foreach(['🦊','🐼','🐸','🐱','🐯','🦄','🐙','🐨','🐵','🦁','🐧','🐲'] as $i=>$a): ?><label class="avatar-option"><input type="radio" name="avatar" value="<?=e($a)?>" <?=$i===0?'checked':''?>><span><?=$a?></span></label><?php endforeach;?></div></div>
<button class="btn primary live-join-btn">🚀 Увійти в гру</button></form>
</div></div>