<div class="qs-game-shell">
<div class="section-title"><div><span class="eyebrow">ИНТЕРАКТИВНЫЙ КВИЗ</span><h1>Слова в разброс</h1><p class="muted">Сначала назовите квиз, затем добавляйте предложения.</p></div><a class="btn ghost" href="<?=url('course/'.$courseId)?>">← Назад</a></div>
<form method="post" enctype="multipart/form-data" class="qs-game-builder">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card"><label>Тема / название квиза<input name="quiz_title" required placeholder="Например: Present Simple — порядок слов"></label></div>
<div id="sentenceItems">
<div class="card sentence-item"><div class="item-head"><b>#1</b></div>
<label>Подсказка<textarea name="prompt[]" required placeholder="Что должен собрать ученик?"></textarea></label>
<label>Правильное предложение<textarea name="correct_sentence[]" required placeholder="I usually go to school."></textarea></label>

</div>
</div>
<div class="actions"><button type="button" class="btn ghost" id="addSentence">＋ Добавить</button><button type="submit" class="btn primary">Создать квиз</button></div>
</form>
</div>
<script>
(()=>{const box=document.getElementById('sentenceItems'),add=document.getElementById('addSentence');let n=1;
function row(){n++;const d=document.createElement('div');d.className='card sentence-item';d.innerHTML=`<div class="item-head"><b>#${n}</b><button type="button" class="btn danger remove">🗑</button></div><label>Подсказка<textarea name="prompt[]" required></textarea></label><label>Правильное предложение<textarea name="correct_sentence[]" required></textarea></label>`;box.appendChild(d)}
add.onclick=row;
box.addEventListener('click',e=>{const item=e.target.closest('.sentence-item');if(e.target.closest('.remove')){item.remove()}})
})();
</script>