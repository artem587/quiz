</main>
<footer>QuizSpace · навчальна платформа</footer>
<footer>Підтримка: quizspace42@gmail.com</footer>
<div class="confirm-modal" id="confirm-modal" aria-hidden="true"><div class="confirm-backdrop" data-confirm-cancel></div><div class="confirm-box" role="dialog" aria-modal="true" aria-labelledby="confirm-title"><div class="confirm-icon">!</div><h3 id="confirm-title">Підтвердити видалення?</h3><p id="confirm-text">Цю дію не можна скасувати.</p><div class="actions"><button type="button" class="btn ghost" data-confirm-cancel>Скасувати</button><button type="button" class="btn danger" id="confirm-ok">Видалити</button></div></div></div>
<script>
(()=>{const modal=document.getElementById('confirm-modal'),ok=document.getElementById('confirm-ok'),text=document.getElementById('confirm-text');let form=null;
function close(){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');form=null}document.addEventListener('click',e=>{const btn=e.target.closest('[data-confirm]');if(btn){e.preventDefault();form=btn.closest('form');text.textContent=btn.dataset.confirm||'Цю дію не можна скасувати.';modal.classList.add('open');modal.setAttribute('aria-hidden','false');return}if(e.target.closest('[data-confirm-cancel]'))close()});ok.onclick=()=>{if(form){form.removeAttribute('data-confirm');form.submit()}close()};document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});})();
</script>
<script>
/* Universal server-side auto-create + auto-save for every builder mode.
 * Quiz and QA have their own dedicated autosave implementations and are skipped here.
 */
(()=>{
  /* The app uses index.php?url=... routing, so pathname alone is always /index.php. */
  const path=(new URLSearchParams(location.search).get('url')||'').replace(/^\/+|\/+$/g,'');
  const forms=[...document.querySelectorAll('form')].filter(f=>{
    if(f.id==='quiz-form'||f.id==='qa-form') return false;
    return !!f.querySelector('[name="title"],[name="quiz_title"]') &&
      /(?:fill-create|fill-quiz-edit|sentence-create|sentence-quiz-edit|flashcard-quiz-create|flashcard-quiz-edit|memory-create|memory-edit|matching(?:-edit)?|roulette(?:-edit)?|flashcards-legacy)/i.test(path);
  });
  if(!forms.length)return;

  function modeFor(){
    if(/truefalse-create/.test(path))return 'truefalse';
    if(/fill-(?:create|quiz-edit)/.test(path))return 'fill';
    if(/sentence-(?:create|quiz-edit)/.test(path))return 'sentence';
    if(/flashcard-quiz-(?:create|edit)/.test(path))return 'flashcards';
    if(/memory-(?:create|edit)/.test(path))return 'memory';
    if(/matching(?:-edit)?/.test(path))return 'matching';
    if(/roulette(?:-edit)?/.test(path))return 'roulette';
    if(/flashcards-legacy/.test(path))return 'legacy-flashcards';
    return '';
  }
  const mode=modeFor();
  const pathParts=path.replace(/\/$/,'').split('/').filter(Boolean);
  let builderId=0;
  const last=pathParts[pathParts.length-1];
  if(/^\d+$/.test(last) && !/(?:truefalse-create|fill-create|sentence-create|flashcard-quiz-create|memory-create|matching|roulette|flashcards-legacy)$/.test(pathParts[pathParts.length-2]||'')) builderId=Number(last);
  if(/-(?:edit|play)\/\d+$/.test(path)) builderId=Number(last);

  forms.forEach(form=>{
    const title=form.querySelector('[name="title"],[name="quiz_title"]');
    const status=document.createElement('span');status.className='muted qs-autosave-global-status';status.style.marginLeft='10px';
    const actions=form.querySelector('.actions')||form.querySelector('button[type="submit"]')?.parentElement;
    if(actions)actions.appendChild(status);else form.insertBefore(status,form.firstChild);
    let timer=0,busy=false,pending=false;
    const courseIdFromPath=(()=>{const m=path.match(/(?:truefalse-create|fill-create|sentence-create|flashcard-quiz-create|memory-create|matching|roulette|flashcards-legacy)\/(\d+)/);return m?Number(m[1]):0})();
    const schedule=(delay=900)=>{clearTimeout(timer);status.textContent='Несохранённые изменения…';timer=setTimeout(save,delay)};
    async function save(){
      if(!title)return;
      if(busy){pending=true;return;}
      busy=true;pending=false;status.textContent='Сохраняем автоматически…';
      try{
        const fd=new FormData(form);fd.append('builder_mode',mode);fd.append('builder_id',String(builderId||0));fd.append('course_id',String(courseIdFromPath||0));fd.append('autosave','1');
        const r=await fetch('<?=url('builder-autosave')?>',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
        const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'Ошибка');
        builderId=Number(data.builder_id||builderId||0);
        if(data.redirect_url && builderId){
          /* Switch the same page from "create" to "edit" without reload. */
          if(history.replaceState && data.redirect_url!==location.pathname)history.replaceState({},'',data.redirect_url);
          if(mode==='truefalse'){
            /* truefalse has no separate edit route; keep its create endpoint and carry the id. */
            let h=form.querySelector('[name="builder_id"]');if(!h){h=document.createElement('input');h.type='hidden';h.name='builder_id';form.appendChild(h)}h.value=String(builderId);
          }else form.action=data.redirect_url;
        }
        status.textContent='✓ Автоматически создано и сохранено '+(data.saved_at||'');
      }catch(e){status.textContent='⚠ Автосохранение не удалось';}
      finally{busy=false;if(pending){pending=false;schedule(250)}}
    }
    form.addEventListener('input',()=>schedule(),true);
    form.addEventListener('change',()=>schedule(),true);
    form.addEventListener('click',e=>{if(e.target.closest('button')&&!e.target.closest('[type="submit"]'))setTimeout(()=>schedule(120),80)},true);
    form.addEventListener('submit',()=>clearTimeout(timer),true);
    /* A new builder is created as soon as the user enters a title or adds/edits content. */
    if(title.value.trim())status.textContent='Автосоздание и автосохранение включены';
  });
})();
</script>
</body></html>
