<div class="qs-game-shell">
<div class="qs-game-header"><div><span class="qs-game-type">True / False</span><h1>Новый квиз</h1><p>Создайте один квиз и добавьте в него любое количество утверждений.</p></div><a class="btn ghost" href="<?=url('course/'.$courseId)?>">← Назад</a></div>
<form method="post" class="qs-game-builder" id="truefalse-form">
<input type="hidden" name="csrf" value="<?=csrf()?>">
<input type="hidden" name="builder_id" id="tf-builder-id" value="0">
<div class="qs-item-card"><label>Название квиза<input name="title" placeholder="Например: Grammar — True or False"></label></div>
<div id="tfItems">
<div class="qs-item-card tf-item">
<div class="qs-item-card__head"><div class="qs-item-card__num">1</div></div>
<label>Утверждение<textarea name="statement[]" placeholder="She goes to school every day."></textarea></label>
<label>Правильный ответ<select name="correct[]"><option value="true">True</option><option value="false">False</option></select></label>
</div></div>
<div class="qs-inline-actions"><button type="button" class="btn ghost" id="addTf">＋ Добавить утверждение</button><span id="tf-autosave-status" class="muted" aria-live="polite"></span><button class="btn primary">Создать квиз</button></div>
</form>
<script>
(()=>{
 const form=document.getElementById('truefalse-form'), box=document.getElementById('tfItems'), addBtn=document.getElementById('addTf');
 const status=document.getElementById('tf-autosave-status'), idInput=document.getElementById('tf-builder-id'), title=form.querySelector('[name="title"]');
 let n=box.querySelectorAll('.tf-item').length||1, timer=0,busy=false,pending=false,builderId=0;
 const setStatus=t=>status.textContent=t;
 function addItem(){n++;const d=document.createElement('div');d.className='qs-item-card tf-item';d.innerHTML=`<div class="qs-item-card__head"><div class="qs-item-card__num">${n}</div><button type="button" class="btn danger remove-row">🗑</button></div><label>Утверждение<textarea name="statement[]" placeholder="Введите утверждение"></textarea></label><label>Правильный ответ<select name="correct[]"><option value="true">True</option><option value="false">False</option></select></label>`;box.appendChild(d);schedule(150);}
 addBtn.addEventListener('click',addItem);
 box.addEventListener('click',e=>{if(e.target.closest('.remove-row')){const row=e.target.closest('.tf-item');if(box.children.length<=1)return;row.remove();box.querySelectorAll('.qs-item-card__num').forEach((x,i)=>x.textContent=i+1);schedule(150);}});
 async function save(){
   if(busy){pending=true;return;}
   busy=true;pending=false;setStatus('Сохраняем автоматически…');
   try{
     const fd=new FormData(form);fd.set('builder_mode','truefalse');fd.set('builder_id',String(builderId||0));fd.set('course_id','<?= (int)$courseId ?>');fd.set('autosave','1');
     const r=await fetch('<?=url('builder-autosave')?>',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
     const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'Ошибка');
     builderId=Number(data.builder_id||0);idInput.value=String(builderId);setStatus('✓ Автоматически создано и сохранено '+(data.saved_at||''));
   }catch(e){setStatus('⚠ Не удалось автосохранить');}
   finally{busy=false;if(pending){pending=false;schedule(250);}}
 }
 function schedule(delay=800){clearTimeout(timer);setStatus('Есть несохранённые изменения…');timer=setTimeout(save,delay);}
 form.addEventListener('input',()=>schedule(),true);form.addEventListener('change',()=>schedule(),true);
 form.addEventListener('submit',()=>clearTimeout(timer));
 setStatus('Автосоздание и автосохранение включены');
})();
</script>
