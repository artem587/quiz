<?php
require __DIR__.'/config/config.php';

$required=[
'users'=>['id','name','email','password_hash','role'],
'courses'=>['id','teacher_id','title','description'],
'course_members'=>['id','course_id','student_id','status'],
'quizzes'=>['id','course_id','title','description'],
'questions'=>['id','quiz_id','question_text','image_path','sort_order'],
'quiz_answers'=>['id','question_id','answer_text','is_correct'],
'bank_questions'=>['id','course_id','question_text','correct_answer','topic','image_path'],
'flashcards'=>['id','course_id','front_text','back_text','front_image_path','back_image_path'],
'matching_sets'=>['id','course_id','title','description'],
'matching_pairs'=>['id','set_id','prompt','answer','prompt_image_path','answer_image_path'],
'roulette_sets'=>['id','course_id','title','description'],
'roulette_segments'=>['id','roulette_id','label','image_path','weight','sort_order'],
'attempts'=>['id','student_id','course_id','type','material_id','score','total'],
'result_answers'=>['id','attempt_id','question_id','bank_question_id','prompt','given_answer','correct_answer','is_correct'],
'password_resets'=>['id','user_id','code_hash','expires_at','used_at']
];

$errors=[];$dbOk=false;
try { db()->query('SELECT 1'); $dbOk=true; } catch(Throwable $e) { $errors[]='MySQL: '.$e->getMessage(); }

if($dbOk){
    foreach($required as $t=>$cols){
        try {
            $have=array_column(db()->query("SHOW COLUMNS FROM `$t`")->fetchAll(),'Field');
            $miss=array_diff($cols,$have);
            if($miss) $errors[]="$t: missing ".implode(', ',$miss);
        } catch(Throwable $e) { $errors[]="$t: table is missing"; }
    }
}
$smtpConfigured = (bool)(env('SMTP_HOST') && env('SMTP_PORT') && env('SMTP_USER') && env('SMTP_PASS'));
$uploadDir=__DIR__.'/public/uploads';
$uploadExists=is_dir($uploadDir);
$uploadWritable=$uploadExists && is_writable($uploadDir);
if(!$uploadExists) $errors[]='public/uploads: directory is missing';
elseif(!$uploadWritable) $errors[]='public/uploads: directory is not writable';

$teacher=null;
if($dbOk){
    try {
        $s=db()->prepare("SELECT id,name,email FROM users WHERE email='teacher@quizspace.local' LIMIT 1");
        $s->execute(); $teacher=$s->fetch() ?: null;
    } catch(Throwable $e){}
}
?>
<!doctype html><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="<?=e(base_url())?>/public/css/style.css?v=33">
<main class="container"><section class="card">
<span class="eyebrow">DIAGNOSTICS · INFINITYFREE</span><h1>Проверка QuizSpace</h1>
<div class="table-wrap"><table>
<tr><th>Проверка</th><th>Статус</th></tr>
<tr><td>PHP</td><td>✅ <?=e(PHP_VERSION)?></td></tr>
<tr><td>PDO MySQL</td><td><?=$dbOk?'✅ OK':'❌ ERROR'?></td></tr>
<tr><td>База</td><td><?=e(env('DB_NAME'))?></td></tr>
<tr><td>Хост MySQL</td><td><?=e(env('DB_HOST'))?></td></tr>
<tr><td>uploads</td><td><?=$uploadExists?'✅ существует':'❌ нет'?> / <?=$uploadWritable?'запись OK':'❌ запись запрещена'?></td></tr>
<tr><td>teacher@quizspace.local</td><td><?=$teacher?'✅ найден':'⚠ не найден — выполни migration_infinityfree.sql'?></td></tr>
<tr><td>SMTP</td><td><?=$smtpConfigured?'✅ параметры заполнены':'❌ не заполнены'?></td></tr>
</table></div>
<?php if($errors): ?><div class="flash"><b>Есть проблемы:</b><br><?=e(implode("\n",$errors))?></div>
<?php else: ?><div class="notice">✅ Сервер и схема совместимы с QuizSpace 3.3.1.</div><?php endif; ?>
<p class="actions"><a class="btn primary" href="<?=url('login')?>">Перейти в QuizSpace</a></p>
<p class="muted">После успешной проверки удали <b>check.php</b> с хостинга.</p>
</section></main>
