<div class="page-head"><div><a class="back" href="<?= $me['role']==='admin' ? url('admin/teachers') : url('teacher/courses') ?>">← Назад</a><span class="eyebrow">КУРС</span><h1><?=e($course['title'])?></h1><p class="muted"><?=e($course['description'])?></p><div class="meta"><span>👨‍🏫 <?=e($course['teacher_name'])?></span></div></div><?php if($me['role']==='teacher'||$me['role']==='admin'): ?><div class="actions"><a class="btn ghost" href="<?=url('course-edit/'.$course['id'])?>">✎ Редагувати</a><form method="post" action="<?=url('delete/'.$course['id'].'/course')?>" data-confirm="Видалити курс і всі матеріали?"><input type="hidden" name="csrf" value="<?=csrf()?>"><button class="btn danger">🗑</button></form></div><?php endif; ?></div>
<?php if($me['role']==='teacher'||$me['role']==='admin'): ?><div class="quick"><a class="btn primary" href="<?=url('quiz-create/'.$course['id'])?>">＋ Тест</a><a class="btn primary" href="<?=url('live-create/'.$course['id'])?>">🎮 Live QR-гра</a><a class="btn primary" href="<?=url('truefalse-create/'.$course['id'])?>">＋ True / False</a><a class="btn primary" href="<?=url('flashcard-quiz-create/'.$course['id'])?>">＋ Квиз з картками</a><a class="btn primary" href="<?=url('qa-quiz-create/'.$course['id'])?>">＋ Питання → Відповідь квиз</a><a class="btn primary" href="<?=url('matching/'.$course['id'])?>">＋ Сопоставлення</a><a class="btn primary" href="<?=url('roulette/'.$course['id'])?>">＋ Рулетка</a><a class="btn ghost" href="<?=url('students/'.$course['id'])?>">Учні</a><a class="btn ghost" href="<?=url('analytics/'.$course['id'])?>">📊 Аналітика</a></div><?php endif; ?>

<section class="content-section"><div class="section-title"><div><span class="eyebrow">ТЕСТУВАННЯ</span><h2>Тести</h2></div></div><div class="quiz-list-stack"><?php foreach($quizzes as $q): ?><div class="card material-card quiz-list-card">
<div class="quiz-card-icon icon">📝</div>
<div class="quiz-card-content"><div class="quiz-type-badge"><?=($q['quiz_type']==='true_false'?'TRUE / FALSE':'ЗВИЧАЙНИЙ КВІЗ')?></div>
<div class="quiz-card-main">
<h3 class="quiz-card-title"><?=e($q['title'])?></h3>
<?php if(!empty($q['description'])): ?><p class="muted quiz-card-description"><?=e($q['description'])?></p><?php endif; ?>
<div class="meta quiz-card-meta"><span>📝 <?=$q['question_count']?> питань</span></div>
</div>
<div class="actions quiz-card-actions">
<a class="btn primary quiz-card-play" href="<?=url('quiz-play/'.$q['id'])?>">▶ Пройти</a>
<?php if($me['role']!=='student'): ?>
<a class="btn ghost quiz-card-icon-btn" href="<?=url('quiz-edit/'.$q['id'])?>" title="Редагувати" aria-label="Редагувати">✎</a>
<form method="post" action="<?=url('delete/'.$q['id'].'/quiz')?>" data-confirm="Видалити тест?"><input type="hidden" name="csrf" value="<?=csrf()?>"><button type="submit" class="btn danger quiz-card-icon-btn" title="Видалити" aria-label="Видалити">🗑</button></form>
<?php endif; ?>
</div>
</div>
</div><?php endforeach; ?><?php if(!$quizzes): ?><div class="card empty">Тестів ще немає.</div><?php endif; ?></div></section>

<section class="content-section">
<div class="section-title"><div><span class="eyebrow">ИНТЕРАКТИВНЫЕ КВИЗЫ</span><h2>Отдельные форматы</h2><p class="muted">Это отдельные квизы. Они не смешиваются с обычными вопросами.</p></div></div>
<?php if($me['role']!=='student'): ?>
<div class="quiz-list-stack">
<div class="card material-card"><div class="icon">🔀</div><div class="grow"><h3>Слова в разброс</h3><p class="muted">Создайте квиз, задайте тему и добавляйте сколько угодно предложений.</p><a class="btn primary" href="<?=url('sentence-create/'.$course['id'])?>">＋ Добавить</a></div></div>
<div class="card material-card"><div class="icon">🧩</div><div class="grow"><h3>Вставка слова</h3><p class="muted">Создайте отдельный квиз и добавляйте предложения с 2–4 вариантами.</p><a class="btn primary" href="<?=url('fill-create/'.$course['id'])?>">＋ Добавить</a></div></div>
<div class="card material-card"><div class="icon">🃏</div><div class="grow"><h3>Закрытые карточки</h3><p class="muted">Начните с 2 карточек и добавляйте новые кнопкой.</p><a class="btn primary" href="<?=url('memory-create/'.$course['id'])?>">＋ Добавить</a></div></div>
</div>
<?php endif; ?>

<div class="quiz-list-stack" style="margin-top:18px">
<?php foreach(($gameQuizzes??[]) as $g):
  $isTF=($g['quiz_type']==='true_false');$isQA=($g['quiz_type']==='qa');$isFC=($g['quiz_type']==='flashcards');
  $icon=$isQA?'❓':($isTF?'✓':($g['quiz_type']==='sentence'?'🔀':($g['quiz_type']==='fill'?'🧩':'🃏')));
  $kind=$isQA?'Питання → Відповідь':($isTF?'True / False':($g['quiz_type']==='sentence'?'Слова в розброс':($g['quiz_type']==='fill'?'Вставка слова':'Квиз с картками')));
  $count=$isQA?(int)$g['qa_count']:($isTF?(int)$g['question_count']:($g['quiz_type']==='sentence'?(int)$g['sentence_count']:($g['quiz_type']==='fill'?(int)$g['fill_count']:(int)$g['card_count'])));
?>
<div class="card material-card quiz-list-card">
 <div class="quiz-card-icon icon"><?=$icon?></div><div class="grow"><span class="quiz-type-badge"><?=e($kind)?></span><h3><?=e($g['title'])?></h3><p class="muted"><?=$count?> <?=($isQA?'блоков':($isTF?'утверждений':($isFC?'карточек':($g['quiz_type']==='sentence'?'предложений':'заданий'))))?></p></div>
 <div class="actions">
  <a class="btn primary" href="<?=url($isQA?'qa-quiz-play/'.$g['id']:($isFC?'flashcard-quiz-play/'.$g['id']:($isTF?'quiz-play/'.$g['id']:'game-quiz/'.$g['id'])))?>">▶ Открыть</a>
  <?php if($me['role']!=='student'): ?><a class="btn ghost" href="<?=url($isQA?'qa-quiz-edit/'.$g['id']:($isTF?'quiz-edit/'.$g['id']:($isFC?'flashcard-quiz-edit/'.$g['id']:($g['quiz_type']==='fill'?'fill-quiz-edit/'.$g['id']:'sentence-quiz-edit/'.$g['id']))))?>">✎ Редактировать</a><form method="post" action="<?=url('delete/'.$g['id'].'/gamequiz')?>" data-confirm="Удалить весь квиз?"><input type="hidden" name="csrf" value="<?=csrf()?>"><button class="btn danger">🗑</button></form><?php endif; ?>
 </div>
</div>
<?php endforeach; ?>
</div>
<?php if(empty($gameQuizzes)): ?><div class="card empty">Интерактивных квизов пока нет.</div><?php endif; ?>
</section>

<section class="content-section">
<div class="section-title">
<div>
<span class="eyebrow">КАРТКИ</span>
<h2>Квізи з картками</h2>
<p class="muted">Усі нові картки створюються всередині окремого квізу.</p>
</div>
<?php if($me['role']!=='student'): ?>
<a class="btn primary" href="<?=url('flashcard-quiz-create/'.$course['id'])?>">＋ Створити квіз з картками</a>
<?php endif; ?>
</div>
<div class="quiz-list-stack">
<?php foreach(($memorySets??[]) as $m): ?>
<div class="card material-card">
<div class="icon">🃏</div>
<div class="grow">
<h3><?=e($m['title'])?></h3>
<p class="muted"><?=$m['card_count']?> карток</p>
</div>
<div class="actions">
<a class="btn primary" href="<?=url('memory-play/'.$m['id'])?>">▶ Відкрити</a>
<?php if($me['role']!=='student'): ?>
<a class="btn ghost" href="<?=url('memory-edit/'.$m['id'])?>">✎</a>
<form method="post" action="<?=url('delete/'.$m['id'].'/memory')?>" data-confirm="Видалити набір?">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<button class="btn danger">🗑</button>
</form>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php if(empty($memorySets)): ?>
<div class="card empty">Квізів з закритими картками поки немає.</div>
<?php endif; ?>
</section>

<section class="content-section">
<div class="section-title"><div><span class="eyebrow">ПРАКТИКА</span><h2>Сопоставление и рулетка</h2></div></div>
<div class="quiz-list-stack">
<?php foreach(($matching??[]) as $m): ?><div class="card material-card"><div class="icon">🔗</div><div class="grow"><h3><?=e($m['title'])?></h3><p class="muted"><?=$m['pair_count']?> пар</p></div><div class="actions"><a class="btn primary" href="<?=url('matching-play/'.$m['id'])?>">▶</a><?php if($me['role']!=='student'):?><a class="btn ghost" href="<?=url('matching-edit/'.$m['id'])?>">✎</a><form method="post" action="<?=url('delete/'.$m['id'].'/matching')?>" data-confirm="Удалить?"><input type="hidden" name="csrf" value="<?=csrf()?>"><button class="btn danger">🗑</button></form><?php endif;?></div></div><?php endforeach;?>
<?php foreach(($roulettes??[]) as $r): ?><div class="card material-card"><div class="icon">🎡</div><div class="grow"><h3><?=e($r['title'])?></h3><p class="muted"><?=$r['segment_count']?> секторов</p></div><div class="actions"><a class="btn primary" href="<?=url('roulette-play/'.$r['id'])?>">▶</a><?php if($me['role']!=='student'):?><a class="btn ghost" href="<?=url('roulette-edit/'.$r['id'])?>">✎</a><form method="post" action="<?=url('delete/'.$r['id'].'/roulette')?>" data-confirm="Удалить?"><input type="hidden" name="csrf" value="<?=csrf()?>"><button class="btn danger">🗑</button></form><?php endif;?></div></div><?php endforeach;?>
</div></section>

<section class="content-section">
<div class="section-title"><div><span class="eyebrow">LIVE QUIZ</span><h2>🎮 Ігри з QR-кодом</h2><p class="muted">Запускайте гру на екрані, а учні приєднуються зі своїх телефонів.</p></div><?php if($me['role']!=='student'): ?><a class="btn primary" href="<?=url('live-create/'.$course['id'])?>">＋ Створити Live-гру</a><?php endif; ?></div>
<div class="quiz-list-stack">
<?php foreach(($liveGames??[]) as $lg): ?>
<div class="card material-card live-saved-card"><div class="icon">🎮</div><div class="grow"><span class="quiz-type-badge">ЗБЕРЕЖЕНИЙ КВІЗ</span><h3><?=e($lg['title'])?></h3><p class="muted">Збережено <?=e($lg['created_at'])?> · PIN і QR створюються лише під час запуску.</p></div><div class="actions"><?php if($me['role']!=='student'): ?><a class="btn primary" href="<?=url('live-quiz/'.$lg['id'])?>">▶ Відкрити / Запустити</a><?php endif; ?></div></div>
<?php endforeach; ?>
</div>
<?php if(empty($liveGames)): ?><div class="card empty">Збережених Live-квізів ще немає. Створіть перший квіз — PIN та QR будуть створені лише під час запуску.</div><?php endif; ?>
</section>

<section class="content-section">
<div class="section-title"><div><span class="eyebrow">CLASSROOM</span><h2>Задания ученикам</h2></div><?php if($me['role']!=='student'):?><a class="btn primary" href="<?=url('assignment-create/'.$course['id'])?>">＋ Назначить</a><?php endif;?></div>
<div class="grid">
<?php foreach(($assignments??[]) as $a): ?><div class="card assignment-card"><div class="icon">🎯</div><div class="grow"><h3><?=e($a['content_title'])?></h3><p class="muted"><?=e($a['content_type'])?> · <?=$a['student_count']?> учеников<?php if($a['due_at']):?> · до <?=e($a['due_at'])?><?php endif;?></p></div><?php if($me['role']!=='student'):?><form method="post" action="<?=url('delete/'.$a['id'].'/assignment')?>" data-confirm="Удалить назначение?"><input type="hidden" name="csrf" value="<?=csrf()?>"><button class="btn danger">🗑</button></form><?php endif;?></div><?php endforeach;?>
</div>
<?php if(empty($assignments)):?><div class="card empty">Назначений пока нет.</div><?php endif;?>
</section>
