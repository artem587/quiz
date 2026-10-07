<div class="qs-game-shell">
<div class="section-title"><div><span class="eyebrow">ИНТЕРАКТИВНЫЙ КВИЗ</span><h1>Вставка слова</h1><p class="muted">Сначала назовите квиз, затем добавляйте предложения.</p></div><a class="btn ghost" href="<?=url('course/'.$courseId)?>">← Назад</a></div>
<form method="post" enctype="multipart/form-data" class="qs-game-builder"><input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card"><label>Тема / название квиза<input name="quiz_title" required></label></div>
<div id="fillItems">
<div class="card fill-item"><div class="item-head"><b>#1</b><button type="button" class="btn danger remove-row" style="display:none">🗑</button></div>
<label>Предложение<textarea class="sentence-input" name="sentence_template[]" required placeholder="I go to school every day."></textarea></label>
<div class="insert-tools"><span class="muted">Поставьте курсор в нужное место и вставьте пропуск прямо в предложение:</span><button type="button" class="btn ghost insert-blank">＋ Вставить пропуск</button></div>
<div class="blank-preview">Предпросмотр: <span></span></div>
<label>Правильный ответ<input name="correct_answer[]" required></label>
<label>Режим ответа<select name="answer_mode[]"><option value="choice">Выбрать слово</option><option value="drag">Перетащить слово</option><option value="type">Вписать самому</option></select></label>
<div class="options"><b>Варианты ответа (минимум 2)</b><div class="option-list"><input name="options[0][]" required><input name="options[0][]" required></div><button type="button" class="btn ghost add-option">＋ Добавить вариант</button></div>
</div></div>
<div class="actions"><button type="button" class="btn ghost" id="addFill">＋ Добавить предложение</button><button class="btn primary">Создать квиз</button></div>
</form>
<script>
(()=>{const box=document.getElementById('fillItems');function bind(row){
 const ta=row.querySelector('.sentence-input'),prev=row.querySelector('.blank-preview span');
 const syncPreview=()=>prev.textContent=ta.value.replace(/___/g,'______');
 row.querySelector('.insert-blank').onclick=()=>{const p=ta.selectionStart,q=ta.selectionEnd;ta.setRangeText('___',p,q,'end');syncPreview();ta.focus()};
 ta.addEventListener('input',syncPreview);syncPreview();
 row.querySelector('.add-option').onclick=()=>{
 const list=row.querySelector('.option-list'), i=document.createElement('input');
 i.placeholder='Новый вариант'; i.name=list.querySelector('input')?.name||'';
 list.appendChild(i);
};
 row.addEventListener('click',e=>{if(e.target.closest('.remove-row')){row.remove();renumber()}});
}
function renumber(){box.querySelectorAll('.fill-item').forEach((r,i)=>{r.querySelector('.item-head b').textContent='#'+(i+1);r.querySelectorAll('.option-list input').forEach(x=>x.name=`options[${i}][]`)})}
bind(box.querySelector('.fill-item'));
document.getElementById('addFill').onclick=()=>{const i=box.querySelectorAll('.fill-item').length;const d=box.querySelector('.fill-item').cloneNode(true);d.querySelectorAll('input,textarea').forEach(x=>x.value='');d.querySelector('.option-list').innerHTML=`<input name="options[${i}][]" required><input name="options[${i}][]" required>`;d.querySelector('.remove-row').style.display='inline-flex';d.querySelector('.blank-preview').textContent='После вставки получится: ';box.appendChild(d);bind(d);renumber();};
})();
</script>
