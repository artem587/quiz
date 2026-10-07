<div class="page-head">
<div>
<span class="eyebrow">ВСТАВКА СЛОВА</span>
<h1><?=e($item['quiz_title'])?></h1>
<p class="muted">Задание <?=$position?> из <?=$total?></p>
</div>
</div>
<div class="card game-play-card">
<div class="audio-tools">
<?php if(!empty($item['audio_url'])):?><audio controls preload="none" src="<?=e(base_url().'/'.$item['audio_url'])?>"></audio><?php endif;?>
<button type="button" class="btn ghost" id="speakFill">🔊 Озвучить</button>
</div>
<h2><?=e($item['sentence_template'])?></h2>
<?php $mode=$item['answer_mode']??'choice'; ?>
<div id="fill-result" class="flash" hidden></div>

<?php if($mode==='choice'): ?>
<div class="option-grid">
<?php foreach($options as $o):?><button type="button" class="btn ghost option-btn" data-correct="<?=e($o['is_correct'])?>"><?=e($o['option_text'])?></button><?php endforeach;?>
</div>
<?php elseif($mode==='drag'): ?>
<p class="muted">Перетягніть відповідь у поле.</p>
<div id="dragOptions" class="qs-word-zone"><?php foreach($options as $o):?><div class="qs-word" draggable="true"><?=e($o['option_text'])?></div><?php endforeach;?></div>
<div id="dragAnswer" class="qs-word-zone" style="margin-top:12px;min-height:58px">Перетягніть сюди</div>
<button type="button" class="btn primary" id="checkDrag">Перевірити</button>
<?php else: ?>
<input id="typedAnswer" class="input" placeholder="Введіть слово">
<button type="button" class="btn primary" id="checkTyped" style="margin-top:10px">Перевірити</button>
<?php endif; ?>

<div class="qs-inline-actions" style="margin-top:18px">
<?php if($prevId):?><a class="btn ghost" href="<?=url('fill-play/'.$prevId)?>">← Назад</a><?php else: ?><span></span><?php endif;?>
<?php if($nextId):?><a class="btn primary" href="<?=url('fill-play/'.$nextId)?>">Далі →</a><?php else: ?><a class="btn primary" href="<?=url('course/'.$item['course_id'])?>">Завершити</a><?php endif;?>
</div>
</div>
<script>
(()=>{
 const correct=<?=json_encode($item['correct_answer'],JSON_UNESCAPED_UNICODE)?>;
 const result=document.getElementById('fill-result');
 const show=ok=>{result.hidden=false;result.textContent=ok?'✅ Правильно!':'❌ Неправильно.';};
 const speak=document.getElementById('speakFill');
 if(speak)speak.onclick=()=>{if(!window.speechSynthesis)return;const u=new SpeechSynthesisUtterance(<?=json_encode($item['sentence_template'],JSON_UNESCAPED_UNICODE)?>);u.lang='en-US';speechSynthesis.cancel();speechSynthesis.speak(u)};
 document.querySelectorAll('.option-btn').forEach(b=>b.onclick=()=>show(b.dataset.correct==='1'));
 const typed=document.getElementById('checkTyped'); if(typed)typed.onclick=()=>show(document.getElementById('typedAnswer').value.trim().toLowerCase()===String(correct).trim().toLowerCase());
 const bank=document.getElementById('dragOptions'), drop=document.getElementById('dragAnswer'); let dragging=null;
 document.querySelectorAll('.qs-word').forEach(el=>{
   el.addEventListener('dragstart',()=>dragging=el);el.addEventListener('dragend',()=>dragging=null);
 });
 [bank,drop].forEach(zone=>zone?.addEventListener('dragover',e=>e.preventDefault()));
 drop?.addEventListener('drop',e=>{e.preventDefault();if(dragging){drop.appendChild(dragging);}});
 bank?.addEventListener('drop',e=>{e.preventDefault();if(dragging){bank.appendChild(dragging);}});
 document.getElementById('checkDrag')?.addEventListener('click',()=>{const text=drop?.querySelector('.qs-word')?.textContent.trim()||'';show(text.toLowerCase()===String(correct).trim().toLowerCase())});
})();
</script>
