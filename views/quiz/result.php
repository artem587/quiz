<?php
$score=(int)($attempt['score']??$score??0);
$total=(int)($attempt['total']??$total??0);
$percent=$total?round($score/$total*100):0;
$courseId=(int)($attempt['course_id']??0);
$title=$attempt['material_title']??'Результат';
?>
<div class="page-head">
  <div>
    <span class="eyebrow">РЕЗУЛЬТАТ</span>
    <h1><?=e($title)?></h1>
    <p class="muted">Ваша попытка завершена и сохранена.</p>
  </div>
</div>

<section class="card result">
  <div class="big">🎯 <?=$score?> / <?=$total?></div>
  <h1><?=$percent?>%</h1>
  <p class="muted">Правильных ответов: <?=$score?> из <?=$total?></p>
</section>

<?php if(!empty($results)): ?>
<section class="stack" style="margin-top:18px">
<?php foreach($results as $i=>$r): ?>
  <article class="card result-answer">
    <div class="question-number"><?=($i+1)?></div>
    <h3><?=e($r['prompt'])?></h3>
    <p><b>Ваш ответ:</b>
      <?php if(trim((string)$r['given_answer'])!==''): ?>
        <?=e($r['given_answer'])?>
      <?php else: ?>
        <span class="muted">Нет ответа</span>
      <?php endif; ?>
    </p>
    <p><b>Правильный ответ:</b> <?=e($r['correct_answer'])?></p>
    <div class="flash <?=((int)$r['is_correct'])?'is-correct':'is-wrong'?>">
      <?=((int)$r['is_correct'])?'✅ Правильно':'❌ Неправильно'?>
    </div>
  </article>
<?php endforeach; ?>
</section>
<?php endif; ?>

<div class="qs-inline-actions" style="margin-top:20px">
  <a class="btn primary" href="<?=url('course/'.$courseId)?>">← Вернуться к курсу</a>
</div>
