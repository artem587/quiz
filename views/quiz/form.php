<div class="page-head"><div><a class="back" href="<?=url('course/'.$courseId)?>">← Курс</a><span class="eyebrow">КОНСТРУКТОР ТЕСТУ</span><h1><?=$quiz?'Редагувати тест':'Новий тест'?></h1><p class="muted">Починайте з <b>2 відповідей</b>. За потреби додавайте ще — правильну відповідь можна змінити в будь-який момент.</p></div></div>
<form method="post" enctype="multipart/form-data" class="stack" id="quiz-form"><input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card form"><label>Назва<input name="title" value="<?=e($quiz['title']??'')?>" required></label><label>Опис<textarea name="description"><?=e($quiz['description']??'')?></textarea></label></div>
<div class="card form"><div class="section-title"><h3>⚡ Быстрый импорт вопросов</h3></div><p class="muted">Формат: <b>вопрос - правильный ответ; вопрос - правильный ответ; ...</b>. Каждый <code>;</code> создаёт отдельный вопрос, а часть после первого <code>-</code> становится правильным ответом.</p><textarea id="text-import" rows="5" placeholder="What is the capital of France? - Paris; 2 + 2 = ? - 4; Hello - Привет"></textarea><div class="actions"><button type="button" class="btn ghost" id="import-text">＋ Преобразовать в вопросы</button><span id="import-status" class="muted"></span></div></div>
<div id="questions" class="stack">
<?php foreach($questions as $i=>$q): ?><div class="card question-editor" data-question>
<div class="section-title"><h3>Питання <?=$i+1?></h3><button type="button" class="btn danger" data-remove-question>🗑 Прибрати</button></div>
<input type="hidden" name="question_id[<?=$i?>]" value="<?=$q['id']?>">
<label>Питання<textarea name="question_text[<?=$i?>]" required><?=e($q['question_text'])?></textarea></label>
<?php if($q['image_path']): ?><img class="editor-image" src="<?=e(base_url().'/'.$q['image_path'])?>" alt="">
<?php endif; ?><label>Зображення<input type="file" name="question_image_<?=$i?>" accept="image/*"></label>
<div class="answer-editor" data-answers>
<div class="answer-editor-head"><b>Варіанти відповідей</b><button type="button" class="btn ghost small" data-add-answer>＋ Додати відповідь</button></div>
<?php foreach($q['answers'] as $ai=>$a): ?><div class="answer-line" data-answer><input name="answers[<?=$i?>][<?=$ai?>]" value="<?=e($a['answer_text'])?>" placeholder="Варіант <?=($ai+1)?>"><label class="radio"><input type="radio" name="correct[<?=$i?>]" value="<?=$ai?>" <?=$a['is_correct']?'checked':''?>> правильний</label><button type="button" class="icon-btn" data-remove-answer title="Видалити відповідь">×</button></div><?php endforeach; ?>
</div><div class="answer-hint">Мінімум 2 заповнені відповіді. Для учня порядок відповідей можна перемішувати.</div>
</div><?php endforeach; ?></div>
<div class="actions"><button type="button" class="btn ghost" id="add-question">＋ Додати питання</button><span id="autosave-status" class="muted" aria-live="polite"></span><button class="btn primary">💾 Зберегти тест</button></div></form>

<script>
(() => {
  let qi = <?=max(count($questions),0)?>;
  const questions = document.getElementById('questions');

  function answerHtml(qi, ai, text='', correct=false){
    return `<div class="answer-line" data-answer><input name="answers[${qi}][${ai}]" value="${escapeHtml(text)}" placeholder="Варіант ${ai+1}"><label class="radio"><input type="radio" name="correct[${qi}]" value="${ai}" ${correct?'checked':''}> правильний</label><button type="button" class="icon-btn" data-remove-answer title="Видалити відповідь">×</button></div>`;
  }
  function escapeHtml(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
  function renumber(){
    [...questions.querySelectorAll('[data-question]')].forEach((q,n)=>q.querySelector('h3').textContent=`Питання ${n+1}`);
  }
  function addQuestion(){
    const i=qi++;
    const d=document.createElement('div'); d.className='card question-editor question-new'; d.dataset.question='';
    d.innerHTML=`<div class="section-title"><h3>Питання</h3><button type="button" class="btn danger" data-remove-question>🗑 Прибрати</button></div>
      <input type="hidden" name="question_id[${i}]" value="0"><label>Питання<textarea name="question_text[${i}]" required></textarea></label>
      <label>Зображення<input type="file" name="question_image_${i}" accept="image/*"></label>
      <div class="answer-editor" data-answers><div class="answer-editor-head"><b>Варіанти відповідей</b><button type="button" class="btn ghost small" data-add-answer>＋ Додати відповідь</button></div>
      ${answerHtml(i,0,'',true)}${answerHtml(i,1,'',false)}</div><div class="answer-hint">Мінімум 2 заповнені відповіді.</div>`;
    questions.appendChild(d); renumber(); d.querySelector('textarea').focus();
  }
  function addAnswer(box){
    const q=box.closest('[data-question]');
    const hidden=q.querySelector('input[name^="question_id"]');
    const qiMatch=hidden.name.match(/\[(\d+)\]/); const qid=qiMatch?qiMatch[1]:0;
    const used=[...box.querySelectorAll('[data-answer] input[type=text], [data-answer] input:not([type=radio])')].map(x=>x.name.match(/\[(\d+)\]$/)?.[1]).filter(Boolean).map(Number);
    const ai=used.length?Math.max(...used)+1:0;
    box.insertAdjacentHTML('beforeend',answerHtml(qid,ai));
    box.lastElementChild?.querySelector('input:not([type=radio])')?.focus();
  }
  function removeAnswer(row){
    const box=row.closest('[data-answers]');
    const rows=box.querySelectorAll('[data-answer]');
    if(rows.length<=2){row.animate([{transform:'translateX(0)'},{transform:'translateX(-5px)'},{transform:'translateX(5px)'},{transform:'translateX(0)'}],{duration:220});return;}
    const radio=row.querySelector('input[type=radio]'); const was=radio?.checked;
    row.classList.add('answer-removing');
    setTimeout(()=>{row.remove(); if(was){const first=box.querySelector('[data-answer] input[type=radio]'); if(first) first.checked=true;}},180);
  }
  questions.addEventListener('click',e=>{
    if(e.target.closest('[data-add-answer]')) addAnswer(e.target.closest('[data-answers]'));
    if(e.target.closest('[data-remove-answer]')) removeAnswer(e.target.closest('[data-remove-answer]').closest('[data-answer]'));
    if(e.target.closest('[data-remove-question]')){
      const q=e.target.closest('[data-question]'); q.classList.add('question-removing'); setTimeout(()=>{q.remove();renumber();},180);
    }
  });

  const importText = document.getElementById('text-import');
  const importButton = document.getElementById('import-text');
  const importStatus = document.getElementById('import-status');
  function importQuestionsFromText(){
    const raw = (importText?.value || '').trim();
    if(!raw){ if(importStatus) importStatus.textContent='Вставьте текст для импорта.'; return; }
    const parts = raw.split(';').map(x=>x.trim()).filter(Boolean);
    const parsed=[]; let skipped=0;
    parts.forEach(part=>{
      const dash = part.indexOf('-');
      if(dash <= 0 || dash === part.length-1){ skipped++; return; }
      const question = part.slice(0,dash).trim();
      const answer = part.slice(dash+1).trim();
      if(!question || !answer){ skipped++; return; }
      parsed.push({question,answer});
    });
    if(!parsed.length){ if(importStatus) importStatus.textContent='Не найдено ни одной пары «вопрос - ответ».'; return; }

    // Собираем все импортированные правильные ответы заранее, чтобы для каждого
    // вопроса сразу сделать 1 правильный + 3 разных неправильных ответа.
    const pool=[...new Set(parsed.map(x=>x.answer))];
    let added=0;
    parsed.forEach(item=>{
      const i=qi++;
      const wrong=pool.filter(a=>a!==item.answer).sort(()=>Math.random()-0.5).slice(0,3);
      const options=[item.answer,...wrong];
      const optionHtml=options.map((a,ai)=>answerHtml(i,ai,a,ai===0)).join('');
      const blanks=Math.max(0,4-options.length);
      const d=document.createElement('div'); d.className='card question-editor question-new'; d.dataset.question='';
      d.innerHTML=`<div class="section-title"><h3>Питання</h3><button type="button" class="btn danger" data-remove-question>🗑 Прибрати</button></div>
        <input type="hidden" name="question_id[${i}]" value="0"><label>Питання<textarea name="question_text[${i}]" required></textarea></label>
        <label>Зображення<input type="file" name="question_image_${i}" accept="image/*"></label>
        <div class="answer-editor" data-answers><div class="answer-editor-head"><b>Варіанти відповідей</b><button type="button" class="btn ghost small" data-add-answer>＋ Додати відповідь</button></div>
        ${optionHtml}${Array.from({length:blanks},(_,k)=>answerHtml(i,options.length+k,'',false)).join('')}</div><div class="answer-hint">Автоматично создано: правильный ответ + до 3 неправильных ответов из других импортированных вопросов.</div>`;
      questions.appendChild(d);
      d.querySelector('textarea').value=item.question;
      added++;
    });
    renumber();
    const note=pool.length<4?' · Для полного набора из 4 вариантов нужно минимум 4 разных ответа.':' · Создано по 4 варианта';
    if(importStatus) importStatus.textContent=`Добавлено: ${added}${skipped ? ` · Пропущено: ${skipped}` : ''}${note}`;
    if(added){ importText.value=''; scheduleAutosave(300); }
  }
  importButton?.addEventListener('click', importQuestionsFromText);

  document.getElementById('add-question').addEventListener('click',addQuestion);
  document.getElementById('quiz-form').addEventListener('submit',e=>{
    let bad=false;
    questions.querySelectorAll('[data-question]').forEach(q=>{
      const filled=[...q.querySelectorAll('[data-answer] input:not([type=radio])')].filter(x=>x.value.trim()).length;
      if(filled<2){bad=true;q.classList.add('validation-shake');setTimeout(()=>q.classList.remove('validation-shake'),350);}
    });
    if(bad){e.preventDefault();alert('У кожному питанні має бути щонайменше 2 заповнені відповіді.');}
  });
  const form = document.getElementById('quiz-form');
  const titleInput = form.querySelector('input[name="title"]');
  const autosaveStatus = document.getElementById('autosave-status');
  let autosaveTimer = null;
  let autosaveBusy = false;
  let autosavePending = false;
  let autosavedQuizId = <?= (int)($quiz['id']??0) ?>;

  function setAutosaveStatus(text){
    if (autosaveStatus) autosaveStatus.textContent = text;
  }

  function buildAutosaveData(){
    const fd = new FormData(form);
    fd.append('course_id', '<?= (int)$courseId ?>');
    fd.append('quiz_id', String(autosavedQuizId || <?= (int)($quiz['id']??0) ?>));
    fd.append('autosave', '1');
    return fd;
  }

  async function autosave(){
    const title = titleInput?.value.trim() || '';
    if (!title) {
      setAutosaveStatus('');
      return;
    }
    if (autosaveBusy) {
      autosavePending = true;
      return;
    }

    autosaveBusy = true;
    autosavePending = false;
    setAutosaveStatus('Сохраняем…');

    try {
      const response = await fetch('<?=url('quiz-autosave')?>', {
        method: 'POST',
        body: buildAutosaveData(),
        credentials: 'same-origin',
        headers: {'X-Requested-With':'XMLHttpRequest'}
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Ошибка');

      autosavedQuizId = Number(data.quiz_id || autosavedQuizId);
      if (autosavedQuizId && location.pathname.indexOf('/quiz-edit/') === -1) {
        const editUrl = '<?=url('quiz-edit')?>/' + autosavedQuizId;
        if (history.replaceState) history.replaceState({}, '', editUrl);
      }
      setAutosaveStatus('✓ Автосохранено ' + (data.saved_at || ''));
    } catch (err) {
      setAutosaveStatus('⚠ Не удалось автосохранить');
    } finally {
      autosaveBusy = false;
      if (autosavePending) {
        autosavePending = false;
        scheduleAutosave(300);
      }
    }
  }

  function scheduleAutosave(delay=900){
    clearTimeout(autosaveTimer);
    if (!(titleInput?.value.trim())) {
      setAutosaveStatus('');
      return;
    }
    setAutosaveStatus('Есть несохранённые изменения…');
    autosaveTimer = setTimeout(autosave, delay);
  }

  form.addEventListener('input', e=>{
    if (e.target.name === 'title' || e.target.name === 'description' ||
        e.target.matches('textarea[name^="question_text"], input[name^="answers["]')) {
      scheduleAutosave();
    }
  });
  form.addEventListener('change', e=>{
    if (e.target.name === 'title' || e.target.name === 'description' ||
        e.target.type === 'radio' || e.target.name.startsWith('answers[')) {
      scheduleAutosave();
    }
  });
  document.getElementById('add-question').addEventListener('click', ()=>scheduleAutosave(1200));

  if(!questions.querySelector('[data-question]')) addQuestion();
  if (titleInput?.value.trim()) {
    setAutosaveStatus('Автосохранение включено');
  }
})();
</script>
