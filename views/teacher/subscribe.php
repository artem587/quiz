<?php
$dc = $donatello ?? donatello_config();
$pending = $pending_payment ?? null;
$payPage = $dc['page_url'] ?? '';
?>
<style>
  .premium-page-card {
    background: var(--card, #ffffff);
    color: var(--ink, #202334);
    border: 1px solid var(--line, #e7e8ef);
  }
  .premium-page-card h2,
  .premium-page-card p,
  .premium-head h1 {
    color: var(--ink, #202334);
  }
  .premium-page-card .muted,
  .premium-head .muted {
    color: var(--muted, #777e94);
  }
  .premium-page-card .btn.primary {
    color: #ffffff !important;
  }
  .donatello-code-box {
    background: rgba(102, 86, 232, 0.08);
    color: var(--accent, #6656e8);
    border: 2px dashed var(--accent, #6656e8);
    font-weight: 800;
    font-size: 22px;
    letter-spacing: 2px;
    padding: 12px;
    border-radius: 12px;
    margin: 14px 0;
    text-align: center;
  }
  .premium-price b {
    color: var(--accent, #6656e8);
    font-size: 32px;
  }
  .premium-status.pending {
    background: #fff8e6;
    color: #b7791f;
    border: 1px solid #f6e05e;
  }
  html[data-theme="dark"] .premium-status.pending {
    background: #342813;
    color: #ecc94b;
    border-color: #744210;
  }
</style>

<div class="page-head live-head premium-head">
  <div>
    <a class="back" href="<?=url('teacher/courses')?>">← Мої курси</a>
    <span class="eyebrow">QUIZSPACE PREMIUM</span>
    <h1>⭐ Premium для вчителя</h1>
    <p class="muted">Безлімітні можливості та зняття обмежень для безкоштовного акаунта.</p>
  </div>
</div>

<section class="card premium-page-card">
  <div class="premium-hero-icon">⭐</div>
  <div class="premium-copy">
    <h2><?=premium_active($me)?'Premium активний':'Розширте можливості QuizSpace'?></h2>

    <?php if (premium_active($me)): ?>
      <p>Підписка активна до <b><?=e(date('d.m.Y H:i', strtotime((string)$me['subscription_expires_at'])))?></b>.</p>
      <span class="premium-status" style="background:#e7f8ee;color:#15803d;">✓ Premium активний</span>

    <?php elseif ($pending): ?>
      <p><b>Крок 1.</b> Відкрийте сторінку Donatello і зробіть донат на <b>50 грн</b>.</p>
      <p><b>Крок 2.</b> У полі <b>«Повідомлення»</b> залиште ваш логін або email та обов'язково вставте цей код:</p>
      <div class="donatello-code-box" id="donatelloCode"><?=e($pending['match_code'])?></div>
      <div class="premium-actions">
        <a class="btn primary premium-pay-btn" href="<?=e($payPage)?>" target="_blank" rel="noopener">💳 Відкрити Donatello</a>
        <button class="btn ghost" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('donatelloCode').textContent);this.textContent='✓ Код скопійовано';">📋 Скопіювати код</button>
      </div>
      <p class="muted small">Після успішної оплати ця сторінка перевірятиме Donatello автоматично. Не створюйте новий платіж, поки попередній має статус очікування.</p>
      <div id="donatelloWait" class="premium-status pending">⏳ Очікуємо підтвердження оплати…</div>

    <?php else: ?>
      <p>Для безкоштовного вчителя діє обмеження: максимум один квіз кожного типу. Premium знімає це обмеження та відкриває всі можливості.</p>
      <div class="premium-price"><b>50 грн</b> / <?=e((string)($dc['duration_days'] ?? 30))?> днів</div>

      <?php if (!($dc['enabled'] ?? true)): ?>
        <div class="flash">Premium тимчасово вимкнено адміністратором.</div>
      <?php elseif (!donatello_ready()): ?>
        <div class="flash">Donatello ще не налаштовано адміністратором. Потрібно вказати DONATELLO_PAGE_URL, DONATELLO_API_TOKEN та DONATELLO_SUB_PRICE у .env.</div>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <button class="btn primary premium-pay-btn">💳 Оплатити через Donatello</button>
        </form>
        <small class="muted premium-payment-note">Після натискання QuizSpace створить унікальний код платежу. Його потрібно вставити в повідомлення Donatello.</small>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php if ($pending && !premium_active($me)): ?>
<script>
(function(){
  const wait = document.getElementById('donatelloWait');
  let busy = false;
  async function sync(){
    if(busy) return;
    busy=true;
    try{
      const r=await fetch('<?=e(url('payment/donatello-sync'))?>',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}});
      const d=await r.json();
      if(d.is_premium){
        wait.textContent='✓ Оплата підтверджена. Premium активовано!';
        setTimeout(()=>location.reload(),700);
        return;
      }
    }catch(e){}
    busy=false;
  }
  sync();
  setInterval(sync,10000);
})();
</script>
<?php endif; ?>