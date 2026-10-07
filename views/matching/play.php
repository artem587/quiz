<div class="page-head"><div><span class="eyebrow">ІНТЕРАКТИВНА ВПРАВА</span><h1><?=e($set['title'])?></h1><p class="muted"><?=e($set['description'])?></p></div></div>
<div class="notice">Перетягніть <b>сам блок відповіді</b> на потрібний блок питання. Підтримується миша, touch і натискання.</div>
<div class="matching-game" id="matching-game">
<div class="matching-column"><div class="matching-column-title">Питання</div>
<?php foreach($pairs as $p): ?><div class="match-card question-block" data-id="<?=$p['id']?>"><span class="match-grip">⋮⋮</span><span><?php if($p['prompt_image_path']): ?><img class="match-image" src="<?=e(base_url().'/'.$p['prompt_image_path'])?>" alt=""><?php endif; ?><?=e($p['prompt'])?></span></div><?php endforeach; ?>
</div>
<div class="matching-column"><div class="matching-column-title">Відповіді</div>
<?php $answers=$pairs;shuffle($answers);foreach($answers as $p): ?><div class="match-card answer-block" draggable="false" data-id="<?=$p['id']?>"><span class="match-grip">⠿</span><span><?php if($p['answer_image_path']): ?><img class="match-image" src="<?=e(base_url().'/'.$p['answer_image_path'])?>" alt=""><?php endif; ?><?=e($p['answer'])?></span></div><?php endforeach; ?>
</div></div>
<div class="matching-progress"><span id="match-count">0</span> / <?=count($pairs)?> пар правильно</div>
<div class="qs-inline-actions" style="margin-top:20px"><button type="button" class="btn primary" id="finishMatching">Завершить</button><a class="btn ghost" href="<?=url('course/'.$set['course_id'])?>">← Вернуться к курсу</a></div>
<script>
(()=> {
 const game=document.getElementById('matching-game');let selected=null,done=0,drag=null,ghost=null,offsetX=0,offsetY=0;
 const qEls=()=>game.querySelectorAll('.question-block');
 function selectQuestion(el){if(el.classList.contains('matched'))return;qEls().forEach(x=>x.classList.remove('selected'));el.classList.add('selected');selected=el.dataset.id;}
 function finish(q,a,answerEl){
   if(!q||q.classList.contains('matched')||!answerEl)return;
   if(q.dataset.id===a){
     q.classList.remove('selected','drop-target');q.classList.add('matched','match-success');
     answerEl.classList.add('matched','match-success');done++;document.getElementById('match-count').textContent=done;
     selected=null;setTimeout(()=>{q.classList.remove('match-success');answerEl.classList.remove('match-success')},550);
   }else{
     q.classList.add('match-wrong');answerEl.classList.add('match-wrong');
     setTimeout(()=>{q.classList.remove('match-wrong');answerEl.classList.remove('match-wrong')},450);
   }
 }
 function cleanup(){if(ghost){ghost.remove();ghost=null}if(drag){drag.el.classList.remove('dragging');drag=null}qEls().forEach(x=>x.classList.remove('drop-target'));document.body.style.userSelect='';}
 function move(e){
   if(!drag)return;e.preventDefault();
   ghost.style.left=(e.clientX-offsetX)+'px';ghost.style.top=(e.clientY-offsetY)+'px';
   qEls().forEach(x=>x.classList.remove('drop-target'));
   const under=document.elementFromPoint(e.clientX,e.clientY)?.closest('.question-block');
   if(under&&!under.classList.contains('matched'))under.classList.add('drop-target');
 }
 function start(e,el){
   if(el.classList.contains('matched'))return;
   e.preventDefault();const r=el.getBoundingClientRect();offsetX=e.clientX-r.left;offsetY=e.clientY-r.top;
   drag={el};ghost=el.cloneNode(true);ghost.classList.add('drag-ghost');ghost.style.width=r.width+'px';ghost.style.height=r.height+'px';ghost.style.left=r.left+'px';ghost.style.top=r.top+'px';document.body.appendChild(ghost);el.classList.add('dragging');document.body.style.userSelect='none';
   window.addEventListener('pointermove',move,{passive:false});window.addEventListener('pointerup',end,{once:true});
 }
 function end(e){
   if(!drag){cleanup();return}
   const under=document.elementFromPoint(e.clientX,e.clientY)?.closest('.question-block');
   if(under&&!under.classList.contains('matched'))finish(under,drag.el.dataset.id,drag.el);
   cleanup();window.removeEventListener('pointermove',move);
 }
 game.addEventListener('pointerdown',e=>{const a=e.target.closest('.answer-block');if(a){start(e,a);return}const q=e.target.closest('.question-block');if(q)selectQuestion(q)});
 game.addEventListener('click',e=>{const a=e.target.closest('.answer-block');if(a&&selected&&!a.classList.contains('matched')){finish(document.querySelector(`.question-block[data-id="${selected}"]`),a.dataset.id,a);selected=null;qEls().forEach(x=>x.classList.remove('selected'))}});
 document.getElementById('finishMatching')?.addEventListener('click',()=>{
   const payload=[...game.querySelectorAll('.question-block')].map(q=>{
     const aid=q.dataset.id;
     const a=game.querySelector('.answer-block.matched[data-id="'+aid+'"]');
     return {id:Number(aid),answer:a?[...a.querySelectorAll('span')].map(x=>x.textContent.trim()).filter(Boolean).pop()||'':''};
   });
   const form=document.createElement('form');form.method='post';form.action='<?=url('attempt-save/'.$set['id'])?>';
   form.innerHTML='<input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="mode" value="matching"><input type="hidden" name="answers">';
   form.elements.answers.value=JSON.stringify(payload);document.body.appendChild(form);form.submit();
 });
})();
</script>