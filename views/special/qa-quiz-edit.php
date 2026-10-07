<div class="qs-game-shell">
<div class="section-title"><div><span class="eyebrow">ПИТАННЯ → ВІДПОВІДЬ</span><h1><?=($createMode??false)?'Новый квиз':'Редактирование квиза'?></h1><p class="muted">Учитель вводит только вопрос и правильный ответ. Варианты для ученика приложение собирает автоматически из ответов этого квиза.</p></div><a class="btn ghost" href="<?=url('course/'.$courseId)?>">← Назад</a></div>
<form method="post" class="qs-game-builder" id="qa-form"><input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card"><label>Название квиза<input name="title" value="<?=e($quiz['title']??'')?>" required placeholder="Vocabulary Practice"></label>
<label>Количество вариантов ответа для ученика
<select name="option_count">
<option value="2" <?=((int)($quiz['qa_option_count']??4)===2?'selected':'')?>>2</option>
<option value="3" <?=((int)($quiz['qa_option_count']??4)===3?'selected':'')?>>3</option>
<option value="4" <?=((int)($quiz['qa_option_count']??4)===4?'selected':'')?>>4</option>
</select>
<span class="muted">Правильный ответ + случайные ответы из других вопросов этого же квиза.</span>
</label></div>
<div class="card"><div class="section-title"><h3>⚡ Быстрый импорт вопросов</h3></div><p class="muted">Формат: <b>вопрос - правильный ответ; вопрос - правильный ответ; ...</b>. После импорта все правильные ответы используются как источник неправильных вариантов.</p><textarea id="qa-text-import" rows="5" placeholder="What is the capital of France? - Paris; 2 + 2 = ? - 4; Hello - Привет"></textarea><div class="actions"><button type="button" class="btn ghost" id="qa-import-text">＋ Преобразовать в вопросы</button><span id="qa-import-status" class="muted"></span></div></div>
<div id="qaItems"><?php foreach(($items??[]) as $i=>$item): ?><div class="card qa-item"><div class="item-head"><b>#<?=($i+1)?></b><button type="button" class="btn danger remove-row">🗑</button></div><input type="hidden" name="item_id[]" value="<?=e($item['id']??0)?>"><label>Питання<textarea name="question_text[]" required><?=e($item['question_text']??'')?></textarea></label><label>Правильна відповідь<textarea name="correct_answer[]" required><?=e($item['correct_answer']??'')?></textarea></label></div><?php endforeach; ?></div>
<div class="actions"><button type="button" class="btn ghost" id="addQa">＋ Додати питання</button><span id="qa-autosave-status" class="muted" aria-live="polite"></span><button class="btn primary"><?=($createMode??false)?'Создать квиз':'Сохранить'?></button></div>
</form>
<script>
(()=>{
  const box=document.getElementById('qaItems'), form=document.getElementById('qa-form'), title=form.querySelector('[name="title"]'), status=document.getElementById('qa-autosave-status');
  const importText=document.getElementById('qa-text-import'), importBtn=document.getElementById('qa-import-text'), importStatus=document.getElementById('qa-import-status');
  let nextId=box.querySelectorAll('.qa-item').length, timer=null, busy=false, pending=false, savedQuizId=<?= (int)($quiz['id']??0) ?>;
  function esc(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
  function ren(){box.querySelectorAll('.qa-item').forEach((x,i)=>x.querySelector('.item-head b').textContent='#'+(i+1));}
  function add(q='',a='',id=0){const i=nextId++,d=document.createElement('div');d.className='card qa-item';d.innerHTML=`<div class="item-head"><b>#</b><button type="button" class="btn danger remove-row">🗑</button></div><input type="hidden" name="item_id[]" value="${id}"><label>Питання<textarea name="question_text[]" required>${esc(q)}</textarea></label><label>Правильна відповідь<textarea name="correct_answer[]" required>${esc(a)}</textarea></label>`;box.appendChild(d);ren();return d;}
  document.getElementById('addQa').onclick=()=>{add();schedule();};
  box.addEventListener('click',e=>{if(e.target.closest('.remove-row')){e.target.closest('.qa-item').remove();if(!box.children.length)add();ren();schedule();}});

  function importTextQuestions(){
    const raw=(importText.value||'').trim(); if(!raw){importStatus.textContent='Вставьте текст для импорта.';return;}
    const parts=raw.split(';').map(x=>x.trim()).filter(Boolean), parsed=[]; let skipped=0;
    parts.forEach(part=>{const dash=part.indexOf('-');if(dash<=0||dash===part.length-1){skipped++;return;}const q=part.slice(0,dash).trim(),a=part.slice(dash+1).trim();if(!q||!a){skipped++;return;}parsed.push([q,a]);});
    parsed.forEach(([q,a])=>add(q,a)); ren(); importText.value='';
    importStatus.textContent=`Добавлено: ${parsed.length}${skipped?' · Пропущено: '+skipped:''} · Варианты для ученика создаются автоматически из ответов других вопросов.`;
    if(parsed.length)schedule(250);
  }
  importBtn.onclick=importTextQuestions;

  function setStatus(t){status.textContent=t;}
  function schedule(delay=900){clearTimeout(timer);if(!title.value.trim()){setStatus('');return;}setStatus('Есть несохранённые изменения…');timer=setTimeout(autosave,delay);}
  async function autosave(){
    if(!title.value.trim()){setStatus('');return;} if(busy){pending=true;return;} busy=true;pending=false;setStatus('Сохраняем…');
    try{
      const fd=new FormData(form);fd.append('autosave','1');fd.append('quiz_id',String(savedQuizId));fd.append('course_id','<?= (int)$courseId ?>');
      const r=await fetch('<?=url('qa-quiz-autosave')?>',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'Ошибка');
      savedQuizId=Number(data.quiz_id||savedQuizId); if(savedQuizId && location.pathname.indexOf('/qa-quiz-edit/')===-1 && history.replaceState)history.replaceState({},'', '<?=url('qa-quiz-edit')?>/'+savedQuizId);
      setStatus('✓ Автосохранено '+(data.saved_at||''));
    }catch(e){setStatus('⚠ Не удалось автосохранить');}
    finally{busy=false;if(pending){pending=false;schedule(250);}}
  }
  form.addEventListener('input',e=>{if(e.target.name==='title'||e.target.name==='question_text[]'||e.target.name==='correct_answer[]')schedule();});
  form.addEventListener('change',e=>{if(e.target.name==='title'||e.target.name==='option_count')schedule();});
  if(!box.children.length){add();add();}ren();
  if(title.value.trim())setStatus('Автосохранение включено');
})();
</script>
