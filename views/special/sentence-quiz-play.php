<div class="page-head"><div><span class="eyebrow">СЛОВА В РАЗБРОС</span><h1><?=e($quiz['title'])?></h1><p class="muted">Весь квиз на одной странице · <?=count($items)?> заданий</p></div></div>
<form class="stack" id="sentenceQuiz">
<?php foreach($items as $i=>$item):?><div class="card sq-item" data-item-id="<?=e($item['id'])?>"><div class="question-number"><?=($i+1)?></div><p class="muted"><?=e($item['prompt'])?></p><div class="sq-bank qs-word-zone"><?php foreach($item['words'] as $w):?><div class="qs-word" draggable="true"><?=e($w['word_text'])?></div><?php endforeach;?></div><h3>Ваше предложение</h3><div class="sq-answer qs-word-zone"></div><div class="flash sq-feedback" hidden></div></div><?php endforeach;?>
<div class="qs-inline-actions"><button type="button" class="btn primary" id="finishSentenceQuiz">Завершить</button><span id="sentenceScore" class="muted"></span></div>
</form>
<script>
(()=>{
 document.querySelectorAll('.sq-item').forEach(item=>{
  let drag=null;
  item.querySelectorAll('.qs-word').forEach(w=>{w.addEventListener('dragstart',()=>{drag=w;w.classList.add('dragging')});w.addEventListener('dragend',()=>{w.classList.remove('dragging');drag=null});w.addEventListener('dragover',e=>e.preventDefault());w.addEventListener('drop',e=>{e.preventDefault();if(drag&&drag!==w)w.parentNode.insertBefore(drag,w)});});
  const bank=item.querySelector('.sq-bank'),ans=item.querySelector('.sq-answer');
  // Перемешиваем слова, но не смещаем их transform-ом:
  // transform раньше уводил часть карточек за пределы блока.
  const words=[...bank.querySelectorAll('.qs-word')];
  for(let i=words.length-1;i>0;i--){
    const j=Math.floor(Math.random()*(i+1));
    bank.appendChild(words[j]);
    words.splice(j,1);
  }
  [bank,ans].forEach(z=>z.addEventListener('dragover',e=>e.preventDefault()));
  bank.addEventListener('drop',e=>{e.preventDefault();if(drag)bank.appendChild(drag)});
  ans.addEventListener('drop',e=>{e.preventDefault();if(drag)ans.appendChild(drag)});

  item.dataset.correct=<?=json_encode(preg_split('/\s+/',trim($item['correct_sentence'])),JSON_UNESCAPED_UNICODE)?>;
 });
 document.getElementById('finishSentenceQuiz').onclick=()=>{
  const payload=[...document.querySelectorAll('.sq-item')].map(item=>({
    id:Number(item.dataset.itemId),
    answer:[...item.querySelector('.sq-answer').children].map(x=>x.textContent.trim()).join(' ')
  }));
  const form=document.createElement('form');form.method='post';form.action='<?=url('attempt-save/'.$quiz['id'])?>';
  form.innerHTML='<input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="mode" value="sentence"><input type="hidden" name="answers">';
  form.elements.answers.value=JSON.stringify(payload);document.body.appendChild(form);form.submit();
 };
})();
</script>
