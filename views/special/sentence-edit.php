<div class="section-title"><div><span class="eyebrow">SENTENCE BUILDER</span><h1>Редактирование</h1></div><a class="btn ghost" href="<?=url('course/'.$item['course_id'])?>">← Назад</a></div>
<form method="post" class="card form"><input type="hidden" name="csrf" value="<?=csrf()?>">
<label>Подсказка<textarea name="prompt" required><?=e($item['prompt'])?></textarea></label>
<label>Правильное предложение<textarea name="correct_sentence" required><?=e($item['correct_sentence'])?></textarea></label>
<button class="btn primary">Сохранить</button></form>