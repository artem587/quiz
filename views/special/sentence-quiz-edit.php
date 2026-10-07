<div class="qs-game-shell">
<div class="section-title"><div><span class="eyebrow">ПОРЯДОК СЛОВ</span><h1>Редактировать квиз</h1><p class="muted">Все предложения этого квиза редактируются здесь. Озвучки в этом режиме нет.</p></div><a class="btn ghost" href="<?=url('course/'.$quiz['course_id'])?>">← Назад</a></div>
<form method="post" class="qs-game-builder"><input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card"><label>Название квиза<input name="quiz_title" value="<?=e($quiz['title'])?>" required></label></div>
<div id="sentenceItems">
<?php foreach($items as $i=>$item):?><div class="card sentence-item"><div class="item-head"><b>#<?=($i+1)?></b><button type="button" class="btn danger remove-row">🗑</button></div><input type="hidden" name="item_id[]" value="<?=$item['id']?>"><label>Подсказка<textarea name="prompt[]" required><?=e($item['prompt'])?></textarea></label><label>Правильное предложение<textarea name="correct_sentence[]" required><?=e($item['correct_sentence'])?></textarea></label></div><?php endforeach;?>
</div>
<div class="actions"><button type="button" class="btn ghost" id="addSentence">＋ Добавить предложение</button><button class="btn primary">Сохранить квиз</button></div>
</form>
<script>
(()=>{const box=document.getElementById('sentenceItems');
function ren(){box.querySelectorAll('.sentence-item').forEach((x,i)=>{x.querySelector('.item-head b').textContent='#'+(i+1);x.querySelectorAll('[name]').forEach(y=>{if(y.name.startsWith('item_id'))y.name='item_id[]';else if(y.name.startsWith('prompt'))y.name='prompt[]';else if(y.name.startsWith('correct_sentence'))y.name='correct_sentence[]'})})}
document.getElementById('addSentence').onclick=()=>{const d=document.createElement('div');d.className='card sentence-item';d.innerHTML='<div class="item-head"><b>#</b><button type="button" class="btn danger remove-row">🗑</button></div><input type="hidden" name="item_id[]" value="0"><label>Подсказка<textarea name="prompt[]" required></textarea></label><label>Правильное предложение<textarea name="correct_sentence[]" required></textarea></label>';box.appendChild(d);ren()};
box.addEventListener('click',e=>{if(e.target.closest('.remove-row')){e.target.closest('.sentence-item').remove();ren()}});ren();
})();
</script>