<div class="section-title">
  <div>
    <span class="eyebrow">ЗАКРЫТЫЕ КАРТОЧКИ</span>
    <h1><?=e($set['title'])?></h1>
    <p class="muted">Нажмите на карточку, чтобы открыть её.</p>
  </div>
</div>

<div class="memory-game-grid">
<?php foreach($cards as $c): ?>
  <div class="memory-card-closed qs-memory-card" data-card data-card-id="<?=e($c['id'])?>">
    <div class="memory-card-back">#<?=e($c['card_number'])?></div>
    <div class="memory-face">
      <?php if(!empty($c['image_path'])): ?>
        <img src="<?=e(base_url().'/'.$c['image_path'])?>" alt="" class="memory-card-image">
      <?php endif; ?>
      <?php if(trim((string)$c['text_content'])!==''): ?>
        <b><?=e($c['text_content'])?></b>
      <?php endif; ?>
      <?php if(!empty($c['audio_url'])): ?>
        <audio controls preload="none" src="<?=e(base_url().'/'.$c['audio_url'])?>"></audio>
      <?php endif; ?>
      <?php if(trim((string)$c['text_content'])!==''): ?>
        <button type="button" class="btn ghost speak-card" data-text="<?=e($c['text_content'])?>">🔊 Озвучить</button>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<div class="qs-inline-actions" style="margin-top:20px"><button type="button" class="btn primary" id="finishMemory">Завершить</button><a class="btn ghost" href="<?=url('course/'.$set['course_id'])?>">← Вернуться к курсу</a></div>

<script>
document.querySelectorAll('[data-card]').forEach(card=>{
  card.addEventListener('click',e=>{
    if(e.target.closest('audio') || e.target.closest('.speak-card')) return;
    card.classList.toggle('open');
  });
});
document.querySelectorAll('.speak-card').forEach(btn=>{
  btn.addEventListener('click',e=>{
    e.stopPropagation();
    const t=btn.dataset.text;
    if(t && 'speechSynthesis' in window){
      speechSynthesis.cancel();
      const u=new SpeechSynthesisUtterance(t);u.lang='en-US';speechSynthesis.speak(u);
    }
  });
});
document.getElementById('finishMemory')?.addEventListener('click',()=>{
  const payload=[...document.querySelectorAll('[data-card]')].map(c=>({id:Number(c.dataset.cardId),answer:c.classList.contains('open')?'1':'0'}));
  const form=document.createElement('form');form.method='post';form.action='<?=url('attempt-save/'.$set['id'])?>';
  form.innerHTML='<input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="mode" value="memory"><input type="hidden" name="answers">';
  form.elements.answers.value=JSON.stringify(payload);document.body.appendChild(form);form.submit();
});
</script>
