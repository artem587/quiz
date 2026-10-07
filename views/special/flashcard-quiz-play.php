<div class="page-head"><div><span class="eyebrow">КВИЗ • КАРТОЧКИ</span><h1><?=e($quiz['title'])?></h1><p class="muted">Нажмите на карточку, чтобы перевернуть её.</p></div></div>
<div class="flash3d-grid" id="flashcardQuizGrid">
<?php foreach($cards as $i=>$c): ?>
<div class="flash3d-card" data-card-id="<?=e($c['id'])?>" tabindex="0" role="button" aria-label="Карточка <?=($i+1)?>">
 <div class="flash3d-inner">
  <div class="flash3d-face flash3d-front">
   <span class="flash3d-number">#<?=($i+1)?></span>
   <div class="flash3d-text"><?=e($c['front_text'])?></div>
   <?php if(!empty($c['front_image_path'])):?><img src="<?=e(base_url().'/'.$c['front_image_path'])?>" alt="" class="card-image"><?php endif;?>
   <button type="button" class="audio-one" data-src="<?=e($c['front_audio_path']??'')?>" data-text="<?=e($c['front_text'])?>">🔊</button>
  </div>
  <div class="flash3d-face flash3d-back">
   <span class="flash3d-number">#<?=($i+1)?></span>
   <div class="flash3d-text"><?=e($c['back_text'])?></div>
   <?php if(!empty($c['back_image_path'])):?><img src="<?=e(base_url().'/'.$c['back_image_path'])?>" alt="" class="card-image"><?php endif;?>
   <button type="button" class="audio-one" data-src="<?=e($c['back_audio_path']??'')?>" data-text="<?=e($c['back_text'])?>">🔊</button>
  </div>
 </div>
</div>
<?php endforeach; ?></div>
<div class="qs-inline-actions" style="margin-top:20px"><button type="button" class="btn primary" id="finishFlashcards">Завершить</button><a class="btn ghost" href="<?=url('course/'.$quiz['course_id'])?>">← Вернуться к курсу</a></div>
<script>
document.querySelectorAll('.flash3d-card').forEach(c=>{c.addEventListener('click',e=>{if(e.target.closest('.audio-one'))return;c.classList.toggle('is-flipped')});c.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();c.classList.toggle('is-flipped')}})});
document.querySelectorAll('.audio-one').forEach(b=>b.addEventListener('click',e=>{e.stopPropagation();const src=b.dataset.src;const text=b.dataset.text||'';if(src){new Audio(src.startsWith('http')?src:'<?=e(base_url())?>/'+src).play().catch(()=>{})}else if(text&&speechSynthesis){speechSynthesis.cancel();speechSynthesis.speak(new SpeechSynthesisUtterance(text));}}));
document.getElementById('finishFlashcards')?.addEventListener('click',()=>{
 const payload=[...document.querySelectorAll('.flash3d-card')].map(c=>({id:Number(c.dataset.cardId),answer:c.classList.contains('is-flipped')?'1':'0'}));
 const form=document.createElement('form');form.method='post';form.action='<?=url('attempt-save/'.$quiz['id'])?>';
 form.innerHTML='<input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="mode" value="flashcards"><input type="hidden" name="answers">';
 form.elements.answers.value=JSON.stringify(payload);document.body.appendChild(form);form.submit();
});
</script>