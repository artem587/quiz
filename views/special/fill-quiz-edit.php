<div class="qs-game-shell">
<div class="section-title"><div><span class="eyebrow">ВСТАВКА СЛОВА</span><h1>Редактировать квиз</h1><p class="muted">Измените название, предложения, варианты и режим ответа.</p></div><a class="btn ghost" href="<?=url('course/'.$quiz['course_id'])?>">← Назад</a></div>
<form method="post" class="qs-game-builder"><input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card"><label>Название квиза<input name="quiz_title" required value="<?=e($quiz['title'])?>"></label></div>
<div id="fillEditItems">
<?php foreach($items as $i=>$item):?>
<div class="card fill-item">
<div class="item-head"><b>#<?=($i+1)?></b><button type="button" class="btn danger remove-row">🗑</button></div>
<input type="hidden" name="items[<?=$i?>][id]" value="<?=$item['id']?>">
<label>Предложение<textarea name="items[<?=$i?>][sentence_template]" required><?=e($item['sentence_template'])?></textarea></label>
<label>Правильный ответ<input name="items[<?=$i?>][correct_answer]" required value="<?=e($item['correct_answer'])?>"></label>
<label>Режим ответа<select name="items[<?=$i?>][answer_mode]"><option value="choice" <?=$item['answer_mode']==='choice'?'selected':''?>>Выбрать слово</option><option value="drag" <?=$item['answer_mode']==='drag'?'selected':''?>>Перетащить слово</option><option value="type" <?=$item['answer_mode']==='type'?'selected':''?>>Вписать самому</option></select></label>
<div class="options"><b>Варианты (минимум 2)</b><?php foreach($item['options'] as $o):?><input name="items[<?=$i?>][options][]" value="<?=e($o['option_text'])?>"><?php endforeach;?><button type="button" class="btn ghost add-option">＋ Добавить вариант</button></div>
</div>
<?php endforeach;?>
</div>
<div class="actions"><button type="button" class="btn ghost" id="addItem">＋ Добавить предложение</button><button class="btn primary">Сохранить квиз</button></div>
</form>
<script>
(()=>{let n=document.querySelectorAll('.fill-item').length,box=document.getElementById('fillEditItems');function ren(){box.querySelectorAll('.fill-item').forEach((x,i)=>{x.querySelector('b').textContent='#'+(i+1);x.querySelectorAll('.options input').forEach(y=>y.name=`items[${i}][options][]`);x.querySelector('input[name*="[id]"]')?.setAttribute('name',`items[${i}][id]`);x.querySelector('textarea[name*="sentence_template"]')?.setAttribute('name',`items[${i}][sentence_template]`);x.querySelector('input[name*="correct_answer"]')?.setAttribute('name',`items[${i}][correct_answer]`);x.querySelector('select[name*="answer_mode"]')?.setAttribute('name',`items[${i}][answer_mode]`)});}
document.getElementById('addItem').onclick=()=>{const i=box.querySelectorAll('.fill-item').length,d=document.createElement('div');d.className='card fill-item';d.innerHTML=`<div class="item-head"><b>#${i+1}</b><button type="button" class="btn danger remove-row">🗑</button></div><input type="hidden" name="items[${i}][id]" value="0"><label>Предложение<textarea name="items[${i}][sentence_template]" required></textarea></label><label>Правильный ответ<input name="items[${i}][correct_answer]" required></label><label>Режим ответа<select name="items[${i}][answer_mode]"><option value="choice">Выбрать слово</option><option value="drag">Перетащить слово</option><option value="type">Вписать самому</option></select></label><div class="options"><b>Варианты (минимум 2)</b><input name="items[${i}][options][]" required placeholder="Вариант 1"><input name="items[${i}][options][]" required placeholder="Вариант 2"><button type="button" class="btn ghost add-option">＋ Добавить вариант</button></div>`;box.appendChild(d);ren();};
box.addEventListener('click',e=>{const item=e.target.closest('.fill-item');if(e.target.closest('.remove-row')){item.remove();ren()}if(e.target.closest('.add-option')){const wrap=e.target.closest('.options'),i=document.createElement('input');i.name=wrap.querySelector('input')?.name||'';i.placeholder='Новый вариант';e.target.before(i)}});ren();
})();
</script>