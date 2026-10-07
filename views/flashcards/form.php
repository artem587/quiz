<div class="page-head"><div><a class="back" href="<?=url('course/'.$courseId)?>">← Курс</a><span class="eyebrow">КАРТКИ</span><h1>Навчальні картки</h1><p class="muted">Додавайте кілька карток за один раз. Зображення можна додати до обох сторін.</p></div></div>
<form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="card"><label>Быстро добавить из текста<p class="muted" style="margin:4px 0 8px">Одна строка — одна карточка: <b>лицо - обратная сторона</b></p><textarea id="cardsImport" rows="6" placeholder="cat - кіт
dog - собака
book - книга"></textarea></label><button type="button" class="btn ghost" id="importCards">＋ Создать карточки из текста</button></div><div id="cards" class="stack"></div><div class="actions"><button type="button" class="btn ghost" onclick="addCard()">＋ Додати картку</button><button class="btn primary">💾 Зберегти</button></div></form>
<script>
let ci=0;
function addCard(){
 const i=ci++,d=document.createElement('div');d.className='card grid2 card-builder';
 d.innerHTML=`<div class="stack"><label>Лицьова сторона<textarea name="front[${i}]" required></textarea></label><label>Зображення лицьової<input type="file" name="front_image_${i}" accept="image/*"></label></div><div class="stack"><label>Зворотна сторона<textarea name="back[${i}]" required></textarea></label><label>Зображення зворотної<input type="file" name="back_image_${i}" accept="image/*"></label></div><button type="button" class="icon-btn card-remove" onclick="this.closest('.card').remove()">×</button>`;
 document.getElementById('cards').appendChild(d);d.querySelector('textarea').focus();
}
addCard();
document.getElementById('importCards').addEventListener('click',()=>{
 const area=document.getElementById('cardsImport');
 const lines=area.value.split(/\r?\n/).map(x=>x.trim()).filter(Boolean);let added=0;
 lines.forEach(line=>{const m=line.match(/^(.*?)\s+-\s+(.*)$/);if(!m)return;addCard();const cards=[...document.querySelectorAll('#cards .card-builder')];const card=cards[cards.length-1];const ta=card.querySelectorAll('textarea');ta[0].value=m[1].trim();ta[1].value=m[2].trim();added++;});
 if(added){area.value='';area.focus();}else if(lines.length)alert('Не найден формат «лицо - обратная сторона».');
});
</script>