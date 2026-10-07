<div class="page-head live-head"><div><a class="back" href="<?=url('course/'.$quiz['course_id'])?>">← Курс</a><span class="eyebrow">LIVE QUIZ</span><h1>🎮 <?=e($quiz['title'])?></h1><p class="muted">Збережений квіз. PIN і QR-код створюються окремо для кожного запуску.</p></div></div>

<div class="live-manager-toolbar card">
<div class="actions">
<a class="btn ghost" href="<?=url('live-edit/'.$quiz['id'])?>">✏️ Редагувати</a>
<form method="post" action="<?=url('live-delete/'.$quiz['id'])?>" class="inline-form" onsubmit="return confirm('Видалити цей Live-квіз і всі його сесії?')">
<input type="hidden" name="csrf" value="<?=csrf()?>"><button class="btn danger">🗑 Видалити</button>
</form>
</div>
<span class="muted">Збережено <?=e($quiz['created_at'])?></span>
</div>

<div class="live-quiz-manager">
<section class="card live-manager-card live-launch-card">
<div class="section-title"><div><span class="eyebrow">ЗАПУСК</span><h2>Нова гра</h2></div><span class="live-manager-icon">🚀</span></div>
<p class="muted">Після запуску відкриється окремий екран ведучого з великим QR-кодом і PIN. Учні грають на своїх телефонах.</p>
<form method="post" action="<?=url('live-launch/'.$quiz['id'])?>"><input type="hidden" name="csrf" value="<?=csrf()?>"><button class="btn primary live-launch-btn">🚀 Запустити нову гру</button></form>
</section>

<section class="card live-manager-card"><div class="section-title"><h2>Питання квізу</h2><b><?=count($questions)?></b></div><div class="live-question-summary">
<?php foreach($questions as $i=>$q): ?><div class="live-summary-row"><span class="live-summary-num"><?=($i+1)?></span><div class="grow"><b><?=e($q['prompt'])?></b><p class="muted"><?=e((string)$q['time_limit'])?> секунд<?php if($q['image_url']): ?> · 🖼️ картинка<?php endif; ?></p></div></div><?php endforeach; ?>
</div></section>
</div>