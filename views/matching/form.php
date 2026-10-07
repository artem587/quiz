<?php $editing=!empty($set); ?>
<div class="page-head"><div><a class="back" href="<?=url('course/'.$courseId)?>">← Курс</a><span class="eyebrow">ІНТЕРАКТИВНА ВПРАВА</span><h1><?=$editing?'Редагувати сопоставлення':'Сопоставлення'?></h1><p class="muted">Створіть пари блоків. Учень перетягує окремий блок відповіді на відповідне питання.</p></div></div>
<form method="post" enctype="multipart/form-data" class="stack" id="matching-form"><input type="hidden" name="csrf" value="<?=csrf()?>">
<div class="card form"><label>Назва<input name="title" required value="<?=e($set['title']??'')?>"></label><label>Опис<textarea name="description"><?=e($set['description']??'')?></textarea></label></div>
<div class="matching-builder-head"><div><h2>Пари</h2><p class="muted">Мінімум 2 пари. Кожна пара — окремі блоки питання та відповіді.</p></div><button type="button" class="btn ghost" id="add-pair">＋ Додати пару</button></div>
<div id="pairs" class="stack"></div>
<div class="actions"><a class="btn ghost" href="<?=url('course/'.$courseId)?>">Скасувати</a><button class="btn primary">💾 <?=$editing?'Зберегти зміни':'Створити вправу'?></button></div></form>
<script>
(()=>{const base=<?=json_encode(base_url().'/')?>;let pi=0;const root=document.getElementById('pairs');const existing=<?=json_encode($pairs??[],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
function esc(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function addPair(data=null){
 const i=pi++,d=document.createElement('div');d.className='card matching-pair-editor pair-new';d.dataset.pair='';
 d.innerHTML=`<div class="section-title"><h3>Пара ${i+1}</h3><button type="button" class="btn danger" data-remove-pair>🗑</button></div>
 <input type="hidden" name="pair_id[${i}]" value="${data?.id||0}">
 <div class="grid2"><div><label>Блок питання<textarea name="prompt[${i}]" required>${esc(data?.prompt)}</textarea></label><label>Зображення питання<input type="file" name="prompt_image_${i}" accept="image/*"></label>${data?.prompt_image_path?`<img class="editor-image small" src="${esc(base+data.prompt_image_path)}">`:''}</div><div><label>Блок відповіді<textarea name="answer[${i}]" required>${esc(data?.answer)}</textarea></label><label>Зображення відповіді<input type="file" name="answer_image_${i}" accept="image/*"></label>${data?.answer_image_path?`<img class="editor-image small" src="${esc(base+data.answer_image_path)}">`:''}</div></div>`;
 root.appendChild(d);d.animate([{opacity:0,transform:'translateY(10px)'},{opacity:1,transform:'none'}],{duration:220,easing:'ease-out'});
}
function renumber(){[...root.children].forEach((x,n)=>x.querySelector('h3').textContent=`Пара ${n+1}`)}
root.addEventListener('click',e=>{const b=e.target.closest('[data-remove-pair]');if(!b)return;const all=root.querySelectorAll('[data-pair]');if(all.length<=2){b.animate([{transform:'translateX(0)'},{transform:'translateX(-5px)'},{transform:'translateX(5px)'},{transform:'translateX(0)'}],{duration:220});return}const row=b.closest('[data-pair]');row.classList.add('pair-removing');setTimeout(()=>{row.remove();renumber()},180)});
document.getElementById('add-pair').onclick=()=>addPair();
if(existing.length) existing.forEach(x=>addPair(x)); else {addPair();addPair();}
document.getElementById('matching-form').addEventListener('submit',e=>{let n=0;root.querySelectorAll('[data-pair]').forEach(x=>{const a=x.querySelectorAll('textarea');if(a[0].value.trim()&&a[1].value.trim())n++;});if(n<2){e.preventDefault();alert('Додайте щонайменше 2 заповнені пари.')}});
})();
</script>