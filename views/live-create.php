<div class="page-head live-head">
<div>
<a class="back" href="<?=url('course/'.$courseId)?>">← Курс</a>
<span class="eyebrow">LIVE QUIZ</span>
<h1><?=!empty($edit)?'✏️ Редагування Live-квізу':'🎮 Створення Live-квізу'?></h1>
<p class="muted"><?=!empty($edit)?'Змініть питання, відповіді та час, після чого збережіть оновлену версію.':'Створіть квіз один раз. PIN і QR-код створюються окремо для кожного запуску.'?></p>
</div>
</div>

<form method="post" class="stack" id="live-builder">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card form live-question live-title-card">
<label>Назва квізу<input name="title" required value="<?=e($quiz['title']??'')?>" placeholder="Наприклад: English B1 — Present Simple"></label>
<label>Час за замовчуванням <span class="muted">(секунди, 5–120)</span><input id="live-default-time" type="number" name="default_time" value="<?=e($questions[0]['time_limit']??20)?>" min="5" max="120"></label>
</div>

<div id="live-questions" class="stack">
<?php if(!empty($questions)): foreach($questions as $i=>$q): ?>
<div class="card live-question" data-q="<?=$i?>">
<div class="section-title"><h3>Питання <?=$i+1?></h3><button type="button" class="btn danger" data-remove-q>🗑</button></div>
<label>Питання<textarea name="prompt[<?=$i?>]" required placeholder="Напишіть питання..."><?=e($q['prompt'])?></textarea></label>
<label>Картинка з інтернету <span class="muted">(необов'язково)</span><input name="image_url[<?=$i?>]" type="url" value="<?=e($q['image_url'])?>" placeholder="https://example.com/image.jpg" inputmode="url"></label>
<label>Час <span class="muted">(секунди)</span><input name="time_limit[<?=$i?>]" type="number" value="<?=e($q['time_limit'])?>" min="5" max="120"></label>
<div class="live-answers">
<div class="section-title"><b>Варіанти відповіді</b><div class="actions"><button type="button" class="btn ghost small" data-generate-a>⚡ Згенерувати 4 блоки</button><button type="button" class="btn ghost small" data-add-a>＋ Відповідь</button></div></div>
<div class="live-answer-list" data-answer-list>
<?php foreach(($q['answers']??[]) as $j=>$a): ?>
<div class="live-answer"><input name="answer[<?=$i?>][]" value="<?=e($a['answer_text'])?>" placeholder="Варіант <?=$j+1?>" <?=$j<2?'required':''?>><label class="radio"><input type="radio" name="correct[<?=$i?>]" value="<?=$j?>" <?=$a['is_correct']?'checked':''?>> правильна</label><button type="button" class="icon-btn" data-remove-a aria-label="Видалити відповідь">×</button></div>
<?php endforeach; ?>
</div></div>
</div>
<?php endforeach; else: ?>
<div class="card live-question" data-q="0">
<div class="section-title"><h3>Питання 1</h3><button type="button" class="btn danger" data-remove-q>🗑</button></div>
<label>Питання<textarea name="prompt[0]" required placeholder="Напишіть питання..."></textarea></label>
<label>Картинка з інтернету <span class="muted">(необов'язково)</span><input name="image_url[0]" type="url" placeholder="https://example.com/image.jpg" inputmode="url"></label>
<label>Час <span class="muted">(секунди)</span><input name="time_limit[0]" type="number" value="20" min="5" max="120"></label>
<div class="live-answers"><div class="section-title"><b>Варіанти відповіді</b><div class="actions"><button type="button" class="btn ghost small" data-generate-a>⚡ Згенерувати 4 блоки</button><button type="button" class="btn ghost small" data-add-a>＋ Відповідь</button></div></div><div class="live-answer-list" data-answer-list></div></div>
</div>
<?php endif; ?>
</div>

<div class="live-builder-actions">
<button type="button" class="btn ghost" id="add-live-q">＋ Додати питання</button>
<button class="btn primary"><?=!empty($edit)?'💾 Зберегти зміни':'🚀 Зберегти квіз'?></button>
</div>
</form>

<script>
(()=>{
 const root=document.getElementById('live-questions'),defaultTime=document.getElementById('live-default-time');
 function currentDefault(){return Math.max(5,Math.min(120,parseInt(defaultTime.value,10)||20));}
 function answerMarkup(i,count=4){
   return Array.from({length:count},(_,j)=>`<div class="live-answer"><input name="answer[${i}][]" placeholder="Варіант ${j+1}" ${j<2?'required':''}><label class="radio"><input type="radio" name="correct[${i}]" value="${j}" ${j===0?'checked':''}> правильна</label><button type="button" class="icon-btn" data-remove-a aria-label="Видалити відповідь">×</button></div>`).join('');
 }
 function renumber(){
   [...root.querySelectorAll('.live-question')].forEach((q,i)=>{
     q.dataset.q=i; const h=q.querySelector('h3'); if(h)h.textContent='Питання '+(i+1);
     q.querySelectorAll('textarea').forEach(x=>x.name=`prompt[${i}]`);
     q.querySelectorAll('input[name^="image_url"]').forEach(x=>x.name=`image_url[${i}]`);
     q.querySelectorAll('input[name^="time_limit"]').forEach(x=>x.name=`time_limit[${i}]`);
     q.querySelectorAll('.live-answer input:not([type="radio"])').forEach(x=>x.name=`answer[${i}][]`);
     q.querySelectorAll('.live-answer input[type="radio"]').forEach((x,j)=>{x.name=`correct[${i}]`;x.value=j;});
   });
 }
 function ensureAnswers(q,count=4){const i=[...root.querySelectorAll('.live-question')].indexOf(q),list=q.querySelector('[data-answer-list]');if(list&&!list.children.length)list.innerHTML=answerMarkup(i,count);renumber();}
 function addQuestion(){
   const i=root.querySelectorAll('.live-question').length,t=currentDefault(),q=document.createElement('div');
   q.className='card live-question question-editor question-new';
   q.innerHTML=`<div class="section-title"><h3>Питання ${i+1}</h3><button type="button" class="btn danger" data-remove-q>🗑</button></div><label>Питання<textarea name="prompt[${i}]" required placeholder="Напишіть питання..."></textarea></label><label>Картинка з інтернету <span class="muted">(необов'язково)</span><input name="image_url[${i}]" type="url" placeholder="https://example.com/image.jpg"></label><label>Час <span class="muted">(секунди)</span><input name="time_limit[${i}]" type="number" value="${t}" min="5" max="120"></label><div class="live-answers"><div class="section-title"><b>Варіанти відповіді</b><div class="actions"><button type="button" class="btn ghost small" data-generate-a>⚡ Згенерувати 4 блоки</button><button type="button" class="btn ghost small" data-add-a>＋ Відповідь</button></div></div><div class="live-answer-list" data-answer-list>${answerMarkup(i,4)}</div></div>`;
   root.appendChild(q);renumber();
 }
 root.querySelectorAll('.live-question').forEach(q=>ensureAnswers(q,4));
 root.addEventListener('click',e=>{
   const q=e.target.closest('.live-question');if(!q)return;
   if(e.target.closest('[data-remove-q]')){if(root.querySelectorAll('.live-question').length>1){q.remove();renumber();}return;}
   if(e.target.closest('[data-generate-a]')){q.querySelector('[data-answer-list]').innerHTML=answerMarkup([...root.querySelectorAll('.live-question')].indexOf(q),4);renumber();return;}
   if(e.target.closest('[data-add-a]')){const list=q.querySelector('[data-answer-list]'),n=list.querySelectorAll('.live-answer').length;if(n>=6){alert('Максимум 6 варіантів.');return;}const i=[...root.querySelectorAll('.live-question')].indexOf(q);list.insertAdjacentHTML('beforeend',answerMarkup(i,1));const r=list.lastElementChild.querySelector('input[type="radio"]');if(r)r.checked=false;renumber();return;}
   if(e.target.closest('[data-remove-a]')){const list=q.querySelector('[data-answer-list]');if(list.querySelectorAll('.live-answer').length>2){e.target.closest('.live-answer').remove();renumber();}}
 });
 document.getElementById('add-live-q').addEventListener('click',addQuestion);
 defaultTime.addEventListener('input',()=>{const t=currentDefault();root.querySelectorAll('input[name^="time_limit"]').forEach(x=>{if(x.dataset.manual!=='1')x.value=t;});});
 root.addEventListener('input',e=>{if(e.target.matches('input[name^="time_limit"]'))e.target.dataset.manual='1';});
 document.getElementById('live-builder').addEventListener('submit',e=>{
   let ok=true;root.querySelectorAll('.live-question').forEach(q=>{if([...q.querySelectorAll('.live-answer input:not([type="radio"])')].filter(x=>x.value.trim()).length<2)ok=false;});
   if(!ok){e.preventDefault();alert('У кожному питанні потрібно щонайменше 2 заповнені відповіді.');}
 });
})();
</script>
