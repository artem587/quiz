<div class="section-title">
  <div>
    <span class="eyebrow">СЛОВА В РАЗБРОС</span>
    <h1><?=e($item['quiz_title'] ?? 'Составление предложения')?></h1>
    <p class="muted"><?=e($item['prompt'])?></p>
  </div>
</div>

<div class="card game-play-card">
  <div class="audio-tools">
    <?php if(!empty($item['audio_url'])): ?>
      <audio controls preload="none" src="<?=e(base_url().'/'.$item['audio_url'])?>"></audio>
    <?php endif; ?>
    <button type="button" class="btn ghost" id="speakSentence">🔊 Озвучить</button>
    <button type="button" class="btn ghost" id="googleSentence">🌐 Google</button>
  </div>

  <p class="muted">Перетягивайте слова между блоками и меняйте их порядок.</p>

  <h3>Доступные слова</h3>
  <div id="wordBank" class="word-drop-zone"></div>

  <h3>Ваше предложение</h3>
  <div id="wordAnswer" class="word-drop-zone answer-zone"></div>

  <div class="actions">
    <button type="button" id="checkSentence" class="btn primary">Проверить</button>
    <button type="button" id="resetSentence" class="btn ghost">Сбросить</button>
  </div>
  <div id="sentenceResult" class="flash" hidden></div>
</div>

<script>
(() => {
  const words = <?=json_encode(array_column($words,'word_text'),JSON_UNESCAPED_UNICODE)?>;
  const correct = <?=json_encode(preg_split('/\s+/', trim($item['correct_sentence'])),JSON_UNESCAPED_UNICODE)?>;
  const bank=document.getElementById('wordBank');
  const answer=document.getElementById('wordAnswer');
  let dragging=null, tokenSeq=0;

  function makeWord(text){
    const el=document.createElement('div');
    el.className='word-chip';
    el.textContent=text;
    el.draggable=true;
    el.dataset.token=String(tokenSeq++);
    el.addEventListener('dragstart',()=>{dragging=el;el.classList.add('dragging')});
    el.addEventListener('dragend',()=>{el.classList.remove('dragging');dragging=null});
    el.addEventListener('dragover',e=>e.preventDefault());
    el.addEventListener('drop',e=>{
      e.preventDefault();
      if(!dragging || dragging===el) return;
      el.parentNode.insertBefore(dragging,el);
    });
    return el;
  }

  words.forEach(w=>bank.appendChild(makeWord(w)));

  [bank,answer].forEach(zone=>{
    zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('drag-over')});
    zone.addEventListener('dragleave',()=>zone.classList.remove('drag-over'));
    zone.addEventListener('drop',e=>{
      e.preventDefault();
      zone.classList.remove('drag-over');
      if(dragging) zone.appendChild(dragging);
    });
  });

  document.getElementById('checkSentence').onclick=()=>{
    const got=[...answer.children].map(x=>x.textContent.trim());
    const ok=got.length===correct.length && got.every((x,i)=>x===correct[i]);
    const r=document.getElementById('sentenceResult');
    r.hidden=false;
    r.textContent=ok?'✅ Правильно!':'❌ Порядок слов пока неправильный.';
  };

  document.getElementById('resetSentence').onclick=()=>{
    [...answer.children].forEach(x=>bank.appendChild(x));
    const r=document.getElementById('sentenceResult'); r.hidden=true;
  };

  function speak(text){
    if(!text || !('speechSynthesis' in window)) return;
    speechSynthesis.cancel();
    const u=new SpeechSynthesisUtterance(text);
    u.lang='en-US';
    speechSynthesis.speak(u);
  }
  document.getElementById('speakSentence').onclick=()=>speak(<?=json_encode($item['correct_sentence'])?>);
  document.getElementById('googleSentence').onclick=()=>window.open(
    'https://translate.google.com/?sl=auto&tl=en&text='+encodeURIComponent(<?=json_encode($item['correct_sentence'])?>)+'&op=translate',
    '_blank'
  );
})();
</script>
