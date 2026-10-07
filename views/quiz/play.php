<div class="page-head"><div><span class="eyebrow">ТЕСТ</span><h1><?=e($quizTitle)?></h1><p class="muted">Вопросы проходят по одному. Используйте «Далее» для перехода.</p></div></div>
<form method="post" class="stack" id="quizForm">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<?php foreach($questions as $i=>$q): ?>
<div class="card quiz-question step-question" data-step="<?=$i?>" <?= $i===0?'':'hidden' ?>>
 <div class="question-number"><?=$i+1?></div>
 <div class="quiz-progress">Вопрос <?=$i+1?> из <?=count($questions)?></div>
 <h3><?=e($q['question_text'])?></h3>
 <?php if($q['image_path']): ?><img class="question-image" src="<?=e(base_url().'/'.$q['image_path'])?>"><?php endif;?>
 <?php foreach($q['answers'] as $a): ?>
 <label class="choice"><input type="radio" name="q[<?=$q['id']?>]" value="<?=e($a['answer_text'])?>"><span><?=e($a['answer_text'])?></span></label>
 <?php endforeach; ?>
 <div class="actions pager">
   <button type="button" class="btn ghost prevBtn" <?= $i===0?'disabled':'' ?>>← Назад</button>
   <?php if($i<count($questions)-1): ?><button type="button" class="btn primary nextBtn">Далее →</button><?php else: ?><button class="btn primary">Завершить</button><?php endif; ?>
 </div>
</div>
<?php endforeach; ?>
</form>
<script>
(()=>{const steps=[...document.querySelectorAll('.step-question')];let cur=0;
function show(i){cur=i;steps.forEach((s,n)=>s.hidden=n!==i);window.scrollTo({top:0,behavior:'smooth'});}
steps.forEach((s,i)=>{s.querySelector('.nextBtn')?.addEventListener('click',()=>{const radios=s.querySelectorAll('input[type=radio]');if(radios.length&&!s.querySelector('input[type=radio]:checked')){alert('Выберите ответ.');return;}show(i+1)});s.querySelector('.prevBtn')?.addEventListener('click',()=>show(i-1));});
})();
</script>