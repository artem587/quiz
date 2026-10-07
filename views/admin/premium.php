<div class="page-head">
  <div><span class="eyebrow">АДМИНИСТРИРОВАНИЕ</span><h1>⭐ Premium и платежи</h1><p class="muted">Настройки тарифа и автоматическая сверка платежей Donatello.</p></div>
</div>

<section class="card admin-premium-card">
  <div class="section-title"><div><h2>Подключение Donatello</h2><p class="muted">Статус: <?= $donatello_ready ? 'API настроен' : 'проверьте DONATELLO_PAGE_URL, DONATELLO_API_TOKEN и тариф' ?></p></div></div>
  <p class="muted">Сверка использует последние донаты из API Donatello. Код платежа из QuizSpace должен быть указан в сообщении к донату.</p>
  <form method="post" action="<?=url('admin/premium')?>" class="actions">
    <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="task" value="sync_payments">
    <button class="btn primary" type="submit">↻ Синхронизировать платежи</button>
    <span class="muted">Команда доступна также через cron; см. <code>cron/donatello-sync.php</code>.</span>
  </form>
</section>

<section class="card admin-premium-card content-section">
  <h2>Настройки тарифа</h2>
  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <div class="admin-premium-grid">
      <label><span>Цена</span><input type="number" name="price" min="0.01" max="1000000" step="0.01" value="<?=e(number_format((float)$settings['price'],2,'.',''))?>"></label>
      <label><span>Срок, дней</span><input type="number" name="duration_days" min="1" max="3650" step="1" value="<?=e((string)$settings['duration_days'])?>"></label>
      <label><span>Валюта</span><input type="text" name="currency" maxlength="3" pattern="[A-Za-z]{3}" value="<?=e($settings['currency'])?>"></label>
    </div>
    <label class="admin-premium-toggle"><input type="checkbox" name="enabled" value="1" <?=$settings['enabled']?'checked':''?>> <span><b>Тариф включён</b><small>Если выключить, новые платежи нельзя будет создать. Уже активные подписки продолжат действовать.</small></span></label>
    <div class="admin-premium-preview"><b>Тариф:</b> <?=e(number_format((float)$settings['price'],2,'.',''))?> <?=e($settings['currency'])?> / <?=e((string)$settings['duration_days'])?> дней</div>
    <button class="btn primary" type="submit">💾 Сохранить настройки</button>
  </form>
</section>

<section class="content-section">
  <div class="section-title"><div><h2>Последние платежи</h2><p class="muted">До 100 последних записей из базы данных.</p></div></div>
  <?php if (!$payments): ?>
    <div class="card empty">Платежей пока нет.</div>
  <?php else: ?>
    <div class="card table-wrap"><table>
      <thead><tr><th>ID</th><th>Преподаватель</th><th>Код</th><th>Ожидается</th><th>Получено</th><th>Статус</th><th>Создан</th><th>Сверен</th></tr></thead>
      <tbody><?php foreach($payments as $payment): ?>
        <tr>
          <td><?=e((string)$payment['id'])?></td>
          <td><?=e((string)($payment['teacher_name'] ?? 'Удалённый аккаунт'))?><br><small class="muted"><?=e((string)($payment['teacher_email'] ?? ''))?></small></td>
          <td><code><?=e((string)$payment['match_code'])?></code></td>
          <td><?=e(number_format((float)$payment['expected_amount'],2,'.',''))?> <?=e((string)$payment['currency'])?></td>
          <td><?=$payment['paid_amount']===null?'—':e(number_format((float)$payment['paid_amount'],2,'.','').' '.(string)$payment['currency'])?></td>
          <td><span class="pill"><?=e((string)$payment['status'])?></span></td>
          <td><?=e((string)$payment['created_at'])?></td>
          <td><?=e((string)($payment['matched_at'] ?? '—'))?></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</section>
