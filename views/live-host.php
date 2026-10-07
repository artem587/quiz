<div class="live-host" data-game="<?=e($game['game_code'])?>" data-id="<?=$game['id']?>">
<div class="live-host-top"><div><span class="eyebrow">LIVE GAME</span><h1><?=e($game['title'])?></h1><div class="live-pin">PIN <b><?=e($game['game_code'])?></b></div><p class="muted">Дайте учасникам QR-код або PIN. Адреса входу: <b><?=e(base_url().'/index.php?url=live-join/'.$game['game_code'])?></b></p></div>
<div class="live-qr"><img alt="QR-код для входу" src="https://quickchart.io/qr?size=260&margin=2&text=<?=rawurlencode(base_url().'/index.php?url=live-join/'.$game['game_code'])?>"><small>Скануйте камерою телефону</small></div></div>
<div class="live-host-actions"><button class="btn primary" id="host-start">▶ Почати гру</button><button class="btn ghost" id="host-next">Далі →</button><button class="btn danger" id="host-finish">Завершити</button><span id="host-status" class="muted"></span></div>
<div class="live-host-grid"><section class="card live-stage"><div id="host-question"><div class="live-wait">Очікуємо учасників…</div></div></section></div>
</div>
<script>
(()=>{
 const root=document.querySelector('.live-host'),id=root.dataset.id,api='<?=url('live-host-api')?>/'+id,q=document.getElementById('host-question'),status=document.getElementById('host-status');
 let last=-99,advancing=false,finishedRendered=false;
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
 const escAttr=s=>esc(s).replace(/`/g,'&#096;');
 const shapeNames=['circle','square','triangle','diamond','hexagon','star'],shapeGlyphs=['●','■','▲','◆','⬡','★'],palette=['red','blue','green','purple','orange','teal'];
 function visual(a,questionId,index=0){const shapeIndex=(Number(index)+Number(questionId||0))%shapeNames.length;return {shape:shapeNames[shapeIndex],glyph:shapeGlyphs[shapeIndex],color:palette[Number(index)%palette.length]};}
 async function call(action){const fd=new FormData();fd.append('csrf','<?=csrf()?>');fd.append('action',action);const r=await fetch(api,{method:'POST',body:fd,credentials:'same-origin'});return r.json();}
 async function render(d){
   if(d.status==='finished'){
     finishedRendered=true;
     q.innerHTML='<div class="live-wait live-wait-full">🏁 Гру завершено. Результати учнів відображаються на їхніх телефонах.</div>';
     status.textContent='Гру завершено';return;
   }
   finishedRendered=false;
   if(d.question){
     if(last!==d.question.id){
       last=d.question.id;
       q.innerHTML=`<div class="live-q-count">ПИТАННЯ ${d.question.number} / ${d.total}</div><h2 class="live-host-prompt">${esc(d.question.prompt)}</h2>${d.question.image_url?`<img class="live-question-image" src="${escAttr(d.question.image_url)}">`:''}<div class="live-answer-preview live-visual-grid">${d.question.answers.map((a,i)=>{const v=visual(a,d.question.id,i);return `<div class="live-visual-host-card live-color-${v.color} live-shape-${v.shape}"><span class="live-shape-glyph">${v.glyph}</span><b>${esc(a.answer_text)}</b></div>`}).join('')}</div><div class="live-timer" id="host-timer"></div>`;
     }
     const rem=Number(d.question.remaining ?? d.question.time_limit),t=document.getElementById('host-timer');if(t)t.textContent=Math.ceil(rem)+' с';
     if(rem<=0&&!advancing){advancing=true;await call('next');last=-99;advancing=false;}
   }else if(d.status==='lobby')q.innerHTML='<div class="live-wait live-wait-full">👥 Учасники приєднуються. Натисніть «Почати гру», коли готові.</div>';
   status.textContent=d.status==='running'?'Гра триває':'Лобі';
 }
 async function poll(){try{const r=await fetch(api,{credentials:'same-origin'});const d=await r.json();await render(d);}catch(e){}}
 document.getElementById('host-start').onclick=async()=>{await call('start');last=-99;poll()};document.getElementById('host-next').onclick=async()=>{await call('next');last=-99;poll()};document.getElementById('host-finish').onclick=async()=>{await call('finish');last=-99;poll()};poll();setInterval(poll,1000);
})();
</script>
