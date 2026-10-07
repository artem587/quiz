<div class="page-head"><div><a class="back" href="<?=url('admin/teachers')?>">← Вчителі</a><span class="eyebrow">УЧИТЕЛЬ</span><h1><?=e($teacher['name'])?></h1><p class="muted"><?=e($teacher['email'])?></p><div class="course-admin-stats">📝 <?=((int)($c['normal_quiz_count']??0))?> тестів · ❓ <?=((int)($c['qa_quiz_count']??0))?> квизов П→О · 🎮 <?=((int)($c['interactive_quiz_count']??0))?> інтерактивних</div></div></div>
<div class="section-title"><div><span class="eyebrow">НАВЧАЛЬНИЙ КОНТЕНТ</span><h2>Курси учителя</h2></div><span class="muted">Натисніть «Відкрити», щоб перейти всередину курсу.</span></div>
<div class="grid">
<?php foreach($courses as $c): ?>
<div class="card course-card admin-course-card"><div class="icon">📚</div><div class="grow"><h3><?=e($c['title'])?></h3><p class="muted"><?=e($c['description'])?></p><div class="meta"><span>📝 <?=$c['quiz_count']?> тестів</span><span>❓ <?=$c['question_count']?> питань</span><span>🧠 <?=$c['bank_count']?> банк</span><span>🃏 <?=$c['card_count']?> карток</span><span>🔗 <?=$c['matching_count']?> сопоставл.</span></div></div><div class="actions"><a class="btn primary" href="<?=url('course/'.$c['id'])?>">Відкрити курс →</a></div></div>
<?php endforeach; ?>
<?php if(!$courses): ?><div class="card empty">У цього учителя ще немає курсів.</div><?php endif; ?></div>
