<div class="page-head"><div><span class="eyebrow">TRUE / FALSE</span><h1><?=e($quiz['title'])?></h1><p class="muted">Усі твердження знаходяться в одному квізі.</p></div></div>
<div class="stack">
<?php
$groups=[];
foreach($rows as $r){$groups[$r['id']][]=$r;}
$i=0; $total=count($groups);
foreach($groups as $qid=>$answers): $i++; $question=$answers[0]; $correct=''; foreach($answers as $a){if((int)$a['is_correct'])$correct=$a['answer_text'];}
?>
<div class="card quiz-question">
<div class="question-number"><?=$i?></div>
<h3><?=e($question['question_text'])?></h3>
<div class="option-grid">
<button type="button" class="btn ghost tf-btn" data-correct="<?=($correct==='True'?'true':'false')?>" data-value="True">True</button>
<button type="button" class="btn ghost tf-btn" data-correct="<?=($correct==='False'?'true':'false')?>" data-value="False">False</button>
</div>
<div class="flash tf-result" hidden></div>
</div>
<?php endforeach;?>
<div class="qs-inline-actions">
<a class="btn primary" href="<?=url('course/'.$quiz['course_id'])?>">← До курсу</a>
</div>
</div>
<script>document.querySelectorAll('.tf-btn').forEach(b=>b.onclick=()=>{const r=b.closest('.quiz-question').querySelector('.tf-result');r.hidden=false;r.textContent=b.dataset.correct==='true'?'✅ Правильно!':'❌ Неправильно.';});</script>
