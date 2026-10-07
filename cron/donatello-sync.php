<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__).'/config/config.php';

$pdo = db();
$lock = (int)$pdo->query("SELECT GET_LOCK('quizspace_donatello_sync', 0)")->fetchColumn();
if ($lock !== 1) {
    fwrite(STDOUT, "Donatello sync skipped: another sync is running.\n");
    exit(0);
}

try {
    $count = donatello_sync_pending_payments(null, 5);
    fwrite(STDOUT, sprintf("Donatello sync complete: %d subscription(s) activated.\n", $count));
} catch (Throwable $e) {
    donatello_log('scheduled sync failed', ['error'=>$e->getMessage()]);
    fwrite(STDERR, "Donatello sync failed: ".$e->getMessage()."\n");
    $exitCode = 1;
} finally {
    $pdo->query("SELECT RELEASE_LOCK('quizspace_donatello_sync')");
}

if (isset($exitCode)) exit($exitCode);
