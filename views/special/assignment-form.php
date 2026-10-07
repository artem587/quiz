<div class="section-title"><div><span class="eyebrow">CLASSROOM</span><h1>Назначить задание</h1><p class="muted">Курс общий, но конкретное задание можно назначить выбранным ученикам.</p></div><a class="btn ghost" href="<?=url('course/'.$courseId)?>">← Назад</a></div>
<form method="post" class="card form">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<label>Тип задания<select id="assignType" name="content_type" required><?php foreach($labels as $key=>$label): ?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach; ?></select></label>
<label>Задание<select id="assignContent" name="content_id" required><?php foreach($assignItems as $x): ?><option value="<?=$x['id']?>" data-type="<?=e($x['content_type'])?>"><?=e($labels[$x['content_type']]??$x['content_type'])?> — <?=e($x['title'])?></option><?php endforeach; ?></select></label>
<label>Ученики</label>
<div class="student-select-list"><?php foreach($students as $st):?><label class="student-choice"><input type="checkbox" name="student_id[]" value="<?=$st['id']?>"><span><b><?=e($st['name'])?></b><small><?=e($st['email'])?></small></span></label><?php endforeach;?></div>
<label>Дедлайн<input name="due_at" type="datetime-local"></label>
<label>Лимит попыток<input name="attempts_limit" type="number" min="1" placeholder="Не ограничивать"></label>
<button class="btn primary">Назначить</button>
</form>
<script>
const type=document.getElementById('assignType'),content=document.getElementById('assignContent');
function filter(){const t=type.value;let first=null;Array.from(content.options).forEach(o=>{const on=o.dataset.type===t;o.hidden=!on;if(on&&!first)first=o.value});if(first)content.value=first}
type.onchange=filter;filter();
</script>