<div class="qs-quiz-page">
  <div class="page-head qs-quiz-page__head">
    <div>
      <span class="eyebrow">ВСТАВКА СЛОВА</span>
      <h1><?=e($quiz['title'])?></h1>
      <p class="muted">Весь квиз на одной странице · <?=count($items)?> заданий</p>
    </div>
  </div>

  <div class="qs-quiz-list" id="fillQuiz">
  <?php foreach($items as $i=>$item): $mode=$item['answer_mode']??'choice'; ?>
    <article class="card fill-quiz-card" data-item-id="<?=e($item['id'])?>"
      data-mode="<?=e($mode)?>"
      data-correct="<?=e($item['correct_answer'])?>">
      <div class="fill-quiz-card__top">
        <span class="question-number"><?=($i+1)?></span>
        <?php if(!empty($item['audio_url'])): ?>
          <audio class="inline-audio" controls preload="none" src="<?=e($item['audio_url'])?>"></audio>
        <?php endif; ?>
        <button type="button" class="btn ghost fill-speak">🔊 Озвучить</button>
      </div>

      <div class="fill-sentence" data-template="<?=e($item['sentence_template'])?>"></div>

      <?php if($mode==='choice'): ?>
      <div class="fill-choice-bank">
        <?php foreach($item['options'] as $o): ?>
          <button type="button" class="btn ghost fill-option"
            data-value="<?=e($o['option_text'])?>"><?=e($o['option_text'])?></button>
        <?php endforeach; ?>
      </div>
      <?php elseif($mode==='drag'): ?>
      <div class="fill-drag-bank">
        <span class="muted">Перетащите слово прямо в пропуск</span>
        <div class="fill-drag-options">
          <?php foreach($item['options'] as $o): ?>
            <div class="qs-word fill-draggable" draggable="true"><?=e($o['option_text'])?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="fill-feedback" hidden></div>
    </article>
  <?php endforeach; ?>
  </div>

  <div class="qs-quiz-finish">
    <button type="button" class="btn primary" id="finishFillQuiz">Завершить</button>
    <span class="muted" id="fillScore"></span>
  </div>
</div>

<script>
(function(){
  const cards=[...document.querySelectorAll('.fill-quiz-card')];

  function speak(text){
    if(!window.speechSynthesis) return;
    speechSynthesis.cancel();
    const u=new SpeechSynthesisUtterance(text);
    u.lang='en-US';
    speechSynthesis.speak(u);
  }

  function renderSentence(card){
    const holder=card.querySelector('.fill-sentence');
    const template=holder.dataset.template||'';
    holder.innerHTML='';
    const parts=template.split('___');
    parts.forEach((part,i)=>{
      const span=document.createElement('span');
      span.textContent=part;
      holder.appendChild(span);
      if(i<parts.length-1){
        const gap=document.createElement('span');
        gap.className='fill-inline-gap';
        gap.textContent='_____';
        gap.dataset.empty='1';
        gap.setAttribute('aria-label','Пропуск');
        holder.appendChild(gap);
      }
    });
    return holder;
  }

  cards.forEach(card=>{
    const template=card.querySelector('.fill-sentence').dataset.template||'';
    const holder=renderSentence(card);
    const gap=holder.querySelector('.fill-inline-gap');
    const mode=card.dataset.mode;
    const correct=(card.dataset.correct||'').trim().toLowerCase();
    const feedback=card.querySelector('.fill-feedback');

    function show(value, done=false){
      const answer=(value||'').trim();
      gap.textContent=answer||'_____';
      gap.dataset.empty=answer?'0':'1';
      card.dataset.answer=answer;
      if(done){
        const ok=answer.toLowerCase()===correct;
        feedback.hidden=false;
        feedback.className='fill-feedback '+(ok?'is-correct':'is-wrong');
        feedback.textContent=ok?'✅ Правильно':'❌ Неправильно';
      }
    }

    card.querySelector('.fill-speak')?.addEventListener('click',()=>speak(template.replace('___',card.dataset.answer||'пропуск')));

    card.querySelectorAll('.fill-option').forEach(btn=>{
      btn.addEventListener('click',()=>{
        show(btn.dataset.value,true);
        card.querySelectorAll('.fill-option').forEach(x=>x.classList.remove('is-selected'));
        btn.classList.add('is-selected');
      });
    });

    if(mode==='type'){
      const input=document.createElement('input');
      input.className='fill-inline-input';
      input.placeholder='введите';
      input.setAttribute('aria-label','Введите пропущенное слово');
      gap.replaceWith(input);
      input.addEventListener('input',()=>{card.dataset.answer=input.value;});
      input.addEventListener('change',()=>show(input.value,true));
      card.querySelector('.fill-sentence').dataset.inlineInput='1';
    }

    if(mode==='drag'){
      let dragging=null;
      card.querySelectorAll('.fill-draggable').forEach(word=>{
        word.addEventListener('dragstart',()=>{dragging=word;word.classList.add('dragging')});
        word.addEventListener('dragend',()=>{word.classList.remove('dragging');dragging=null});
      });
      gap.addEventListener('dragover',e=>{e.preventDefault();gap.classList.add('drag-over')});
      gap.addEventListener('dragleave',()=>gap.classList.remove('drag-over'));
      gap.addEventListener('drop',e=>{
        e.preventDefault();gap.classList.remove('drag-over');
        if(!dragging)return;
        show(dragging.textContent,true);
        dragging.remove();
      });
    }

    if(mode==='choice'){
      gap.addEventListener('click',()=>{
        card.scrollIntoView({behavior:'smooth',block:'center'});
      });
    }
  });

  document.getElementById('finishFillQuiz').addEventListener('click',()=>{
    const payload=cards.map(card=>{
      const typed=card.querySelector('.fill-inline-input');
      return {id:Number(card.dataset.itemId),answer:String(card.dataset.answer ?? typed?.value ?? '').trim()};
    });
    const form=document.createElement('form');form.method='post';form.action='<?=url('attempt-save/'.$quiz['id'])?>';
    form.innerHTML='<input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="mode" value="fill"><input type="hidden" name="answers">';
    form.elements.answers.value=JSON.stringify(payload);document.body.appendChild(form);form.submit();
  });
})();
</script>
