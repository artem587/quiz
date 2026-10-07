<div class="page-head"><div><a class="back" href="<?=url('course/'.$card['course_id'])?>">← Курс</a><span class="eyebrow">РЕДАГУВАННЯ КАРТКИ</span><h1>Редагувати картку</h1><p class="muted">Змініть текст або замініть зображення.</p></div></div>
<form method="post" enctype="multipart/form-data" class="card form"><input type="hidden" name="csrf" value="<?=csrf()?>">
<label>Лицьова сторона<textarea name="front_text" required><?=e($card['front_text'])?></textarea></label>
<?php if($card['front_image_path']): ?><img class="editor-image" src="<?=e(base_url().'/'.$card['front_image_path'])?>" alt=""><?php endif; ?>
<label>Нове зображення лицьової<input type="file" name="front_image" accept="image/*"></label>
<label>Зворотна сторона<textarea name="back_text" required><?=e($card['back_text'])?></textarea></label>
<?php if($card['back_image_path']): ?><img class="editor-image" src="<?=e(base_url().'/'.$card['back_image_path'])?>" alt=""><?php endif; ?>
<label>Нове зображення зворотної<input type="file" name="back_image" accept="image/*"></label>
<div class="actions"><a class="btn ghost" href="<?=url('course/'.$card['course_id'])?>">Скасувати</a><button class="btn primary">💾 Зберегти</button></div></form>