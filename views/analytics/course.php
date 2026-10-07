<?php
$course = (isset($course) && is_array($course)) ? $course : null;
$stats = (isset($stats) && is_array($stats)) ? $stats : ['attempts'=>0,'avg_score'=>0];
$students = (isset($students) && is_array($students)) ? $students : [];
$hard = (isset($hard) && is_array($hard)) ? $hard : [];

if (!$course) {
    http_response_code(404);
    ?>
    <section class="card empty">
        <div class="icon">⚠️</div>
        <h2>Курс не знайдено</h2>
        <p class="muted">Курс був видалений, або у вас немає доступу до цієї аналітики.</p>
        <a class="btn primary" href="<?=e(url($me['role']==='admin' ? 'admin/teachers' : 'teacher/courses'))?>">← На головну</a>
    </section>
    <?php
    return;
}
?>
<div class="page-head">
  <div>
    <a class="back" href="<?=e(url('course/'.$course['id']))?>">← До курсу</a>
    <span class="eyebrow">АНАЛІТИКА</span>
    <h1><?=e($course['title'])?></h1>
  </div>
</div>

<div class="stats">
  <div class="stat"><b><?=e($stats['attempts'] ?? 0)?></b><span>спроб</span></div>
  <div class="stat"><b><?=round((float)($stats['avg_score'] ?? 0))?>%</b><span>середній результат</span></div>
  <div class="stat"><b><?=count($students)?></b><span>учнів</span></div>
</div>

<section class="card">
  <h2>Учні</h2>
  <?php if(!$students): ?>
    <div class="empty">Поки немає учнів або результатів.</div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Учень</th><th>Спроби</th><th>Середній</th></tr></thead><tbody>
    <?php foreach($students as $s): ?>
      <tr><td><?=e($s['name'] ?? '')?></td><td><?=e($s['attempts'] ?? 0)?></td><td><?=round((float)($s['avg_score'] ?? 0))?>%</td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Найскладніші питання</h2>
  <?php if(!$hard): ?>
    <div class="empty">Ще немає достатньо результатів для статистики.</div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Питання</th><th>Успішність</th></tr></thead><tbody>
    <?php foreach($hard as $h): ?>
      <tr><td><?=e($h['prompt'] ?? '')?></td><td><?=round((float)($h['success'] ?? 0))?>%</td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
