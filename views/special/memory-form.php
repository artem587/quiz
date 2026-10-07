<?php $existing=$set['cards']??[]; ?>
<div class="section-title">
  <div><span class="eyebrow">ЗАКРЫТЫЕ КАРТОЧКИ</span><h1>Новый набор</h1><p class="muted">Начните с 2 карточек и добавляйте сколько нужно.</p></div>
  <a class="btn ghost" href="<?=url('course/'.$courseId)?>">← Назад</a>
</div>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=csrf()?>">

<div class="card">
  <label>Название набора
    <input name="title" required value="<?=e($set['title']??'')?>" placeholder="Например: Animals">
  </label>
</div>

<div id="memoryItems" class="memory-grid-editor"></div>

<div class="actions">
  <button type="button" class="btn ghost" id="addCard">＋ Добавить карточку</button>
  <button type="submit" class="btn primary">Сохранить</button>
</div>
</form>

<script>
const existing=<?=json_encode(array_values($existing),JSON_UNESCAPED_UNICODE)?>;
const box=document.getElementById('memoryItems');
let next=1;

function card(data={},num){
  const n=num||next++;
  const d=document.createElement('div');
  d.className='card memory-item';
  d.dataset.n=n;
  d.innerHTML=`
    <div class="item-head">
      <b>#${n}</b>
      <button type="button" class="btn danger remove">🗑</button>
    </div>
    <input type="hidden" name="card_slot[]" value="${n}">
    <label>Слово / текст
      <input name="card_text[${n}]" value="${String(data.text_content||'').replaceAll('"','&quot;')}">
    </label>
    <label>Изображение
      <input type="file" name="card_image_${n}" accept="image/*">
    </label>
    <label>Аудиофайл
      <input type="file" name="card_audio_file_${n}" accept="audio/*">
    </label>
    <label>Аудио-ссылка
      <input name="card_audio[${n}]" value="${String(data.audio_url||'').replaceAll('"','&quot;')}" placeholder="https://.../audio.mp3">
    </label>
    ${data.image_path?`<img class="memory-preview" src="<?=e(base_url())?>/${String(data.image_path).replaceAll('"','&quot;')}" alt="">`:''}
    <div class="audio-tools">
      <button type="button" class="btn ghost speak">🔊 Озвучить</button>
      <button type="button" class="btn ghost gtranslate">🌐 Google</button>
    </div>
  `;
  box.appendChild(d);
}
if(existing.length){
  existing.forEach(x=>{card(x,Number(x.card_number));next=Math.max(next,Number(x.card_number)+1)});
}else{
  card({},1);card({},2);
}
document.getElementById('addCard').onclick=()=>card();

box.addEventListener('click',e=>{
  const item=e.target.closest('.memory-item');
  if(!item) return;
  if(e.target.closest('.remove')){
    item.remove();
    return;
  }
  if(e.target.closest('.speak')){
    const t=item.querySelector('input[name^="card_text"]').value;
    if(t&&'speechSynthesis'in window){speechSynthesis.cancel();const u=new SpeechSynthesisUtterance(t);u.lang='en-US';speechSynthesis.speak(u);}
  }
  if(e.target.closest('.gtranslate')){
    const t=item.querySelector('input[name^="card_text"]').value;
    if(t)window.open('https://translate.google.com/?sl=auto&tl=en&text='+encodeURIComponent(t)+'&op=translate','_blank');
  }
});
</script>
