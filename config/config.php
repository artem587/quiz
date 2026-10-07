<?php
declare(strict_types=1);

function env(string $key, string $default=''): string {
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $path = dirname(__DIR__) . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$k,$v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }
    return $vars[$key] ?? $default;
}

if (env('APP_DEBUG','false') === 'true') {
    ini_set('display_errors','1');
    ini_set('display_startup_errors','1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors','0');
    ini_set('display_startup_errors','0');
    error_reporting(E_ALL);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($secure) ini_set('session.cookie_secure', '1');
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3306').';dbname='.env('DB_NAME','quiz').';charset=utf8mb4';
    $pdo = new PDO($dsn, env('DB_USER','root'), env('DB_PASS',''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // InfinityFree/legacy imports can have mixed collations. Force UTF-8
    // for every connection so newly saved Cyrillic/Ukrainian text is preserved.
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $pdo;
}

function base_url(): string {
    $configured = trim(env('BASE_URL',''));
    if (env('AUTO_BASE_URL','false') === 'true' && !empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'];
        $scriptDir = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir !== '/' && $scriptDir !== '\\' && $scriptDir !== '.') $base .= $scriptDir;
        return rtrim($base,'/');
    }
    return rtrim($configured ?: env('APP_URL','http://localhost/quiz'), '/');
}
function url(string $path=''): string {
    return base_url() . ($path ? '/index.php?url='.rawurlencode(trim($path,'/')) : '/');
}
function redirect(string $path): never { header('Location: '.url($path)); exit; }
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('CSRF token mismatch');
    }
}
function flash(?string $msg=null): ?string {
    if ($msg !== null) { $_SESSION['flash']=$msg; return null; }
    $x=$_SESSION['flash']??null; unset($_SESSION['flash']); return $x;
}
function me(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $u = false;
    if ($u === false) {
        $s=db()->prepare('SELECT * FROM users WHERE id=?');
        $s->execute([(int)$_SESSION['user_id']]);
        $u=$s->fetch() ?: null;
    }
    return $u;
}
function require_login(): array { $u=me(); if(!$u) redirect('login'); return $u; }
function require_role(string ...$roles): array {
    $u=require_login();
    if (!in_array($u['role'],$roles,true)) { http_response_code(403); exit('Доступ запрещён'); }
    return $u;
}
function can_manage_course(int $courseId, ?array $u): bool {
    if (!$u || empty($u['id']) || empty($u['role'])) return false;
    if ($u['role']==='admin') return true;
    $s=db()->prepare('SELECT 1 FROM courses WHERE id=? AND teacher_id=?');
    $s->execute([$courseId,$u['id']]);
    return (bool)$s->fetchColumn();
}
function can_access_course(int $courseId, ?array $u): bool {
    if (!$u || empty($u['id']) || empty($u['role'])) return false;
    if ($u['role']==='admin') return true;
    if ($u['role']==='teacher') return can_manage_course($courseId,$u);
    $s=db()->prepare("SELECT 1 FROM course_members WHERE course_id=? AND student_id=? AND status='active'");
    $s->execute([$courseId,$u['id']]);
    return (bool)$s->fetchColumn();
}
function view(string $file, array $data=[]): void {
    $data['me'] = $data['me'] ?? me();
    extract($data, EXTR_SKIP);
    require dirname(__DIR__).'/views/layout/header.php';
    require dirname(__DIR__).'/views/'.$file.'.php';
    require dirname(__DIR__).'/views/layout/footer.php';
}
function upload_image(string $field): ?string {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($_FILES[$field]['size'] > 5*1024*1024) throw new RuntimeException('Изображение слишком большое (максимум 5 МБ).');
    if (!function_exists('finfo_open')) throw new RuntimeException('На сервере не включён Fileinfo.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Разрешены только JPG, PNG, WEBP и GIF.');
    $dir=dirname(__DIR__).'/public/uploads';
    if (!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('Не удалось создать public/uploads.');
    if (!is_writable($dir)) throw new RuntimeException('Папка public/uploads недоступна для записи.');
    $name=bin2hex(random_bytes(12)).'.'.$allowed[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('Не удалось сохранить изображение.');
    return 'public/uploads/'.$name;
}

function upload_audio(string $field): ?string {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($_FILES[$field]['size'] > 15*1024*1024) throw new RuntimeException('Аудиофайл слишком большой (максимум 15 МБ).');
    $mime=function_exists('finfo_open')?(new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']):'';
    $allowed=['audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/ogg'=>'ogg','audio/webm'=>'webm','audio/mp4'=>'m4a','audio/x-m4a'=>'m4a'];
    if(!isset($allowed[$mime])) throw new RuntimeException('Разрешены MP3, WAV, OGG, WEBM и M4A.');
    $dir=dirname(__DIR__).'/public/uploads';
    if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Не удалось создать public/uploads.');
    if(!is_writable($dir))throw new RuntimeException('Папка public/uploads недоступна для записи.');
    $name=bin2hex(random_bytes(12)).'.'.$allowed[$mime];
    if(!move_uploaded_file($_FILES[$field]['tmp_name'],$dir.'/'.$name))throw new RuntimeException('Не удалось сохранить аудиофайл.');
    return 'public/uploads/'.$name;
}

function delete_file(?string $path): void {
    if ($path && str_starts_with($path,'public/uploads/')) {
        $full=dirname(__DIR__).'/'.$path;
        if (is_file($full)) @unlink($full);
    }
}


function ensure_premium_columns(): void {
    static $done = false;
    if ($done) return;
    $pdo = db();
    $columns = [
        'is_premium' => "ADD COLUMN is_premium TINYINT(1) NOT NULL DEFAULT 0",
        'subscription_expires_at' => "ADD COLUMN subscription_expires_at DATETIME NULL",
    ];
    foreach ($columns as $name => $alter) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME=?");
        $q->execute([$name]);
        if (!(int)$q->fetchColumn()) $pdo->exec("ALTER TABLE users {$alter}");
    }
    // DB-backed application settings. Environment variables remain the bootstrap fallback.
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $defaults = [
        'premium_price_uah' => number_format((float)env('DONATELLO_SUB_PRICE','50'), 2, '.', ''),
        'premium_duration_days' => '30',
        'premium_currency' => strtoupper(trim(env('DONATELLO_SUB_CURRENCY','UAH'))),
        'premium_enabled' => '1',
    ];
    $seed = $pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_key=setting_key");
    foreach ($defaults as $k => $v) $seed->execute([$k,$v]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS donatello_payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        match_code VARCHAR(40) NOT NULL UNIQUE,
        expected_amount DECIMAL(12,2) NOT NULL,
        currency VARCHAR(8) NOT NULL DEFAULT 'UAH',
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        donatello_pub_id VARCHAR(120) DEFAULT NULL,
        donor_name VARCHAR(255) DEFAULT NULL,
        message TEXT DEFAULT NULL,
        paid_amount DECIMAL(12,2) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        matched_at DATETIME NULL,
        premium_activated_at DATETIME NULL,
        INDEX(user_id), INDEX(status), INDEX(donatello_pub_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

function app_setting(string $key, ?string $default = null): ?string {
    ensure_premium_columns();
    $q = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1');
    $q->execute([$key]);
    $value = $q->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function set_app_setting(string $key, string $value): void {
    ensure_premium_columns();
    db()->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?)
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
        ->execute([$key,$value]);
}

function premium_price(): float {
    return max(0, (float)app_setting('premium_price_uah', env('DONATELLO_SUB_PRICE','50')));
}
function premium_duration_days(): int { return max(1, min(3650, (int)app_setting('premium_duration_days', '30'))); }
function premium_currency(): string {
    $v = strtoupper(trim((string)app_setting('premium_currency', env('DONATELLO_SUB_CURRENCY','UAH'))));
    return preg_match('/^[A-Z]{3}$/', $v) ? $v : 'UAH';
}
function premium_enabled(): bool { return app_setting('premium_enabled', '1') === '1'; }
function premium_currency_label(?string $currency = null): string { $v = strtoupper(trim($currency ?: premium_currency())); return $v === 'UAH' ? 'грн' : $v; }

function donatello_config(): array {
    return [
        'page_url' => rtrim(trim(env('DONATELLO_PAGE_URL','')), '/'),
        'api_token' => trim(env('DONATELLO_API_TOKEN','')),
        'price' => premium_price(),
        'currency' => premium_currency(),
        'duration_days' => premium_duration_days(),
        'enabled' => premium_enabled(),
    ];
}

function donatello_ready(): bool {
    $c = donatello_config();
    return $c['enabled'] && $c['page_url'] !== '' && $c['api_token'] !== '' && $c['price'] > 0;
}

function donatello_log(string $message, array $context=[]): void {
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir.'/donatello_payments.log', date('Y-m-d H:i:s').' | '.$message.' | '.json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND);
}

function donatello_http_get(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['X-Token: '.$token, 'Accept: application/json', 'User-Agent: QuizSpace/1.0'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false || $err) throw new RuntimeException('Donatello API connection failed: '.$err);
    $json = json_decode($body, true);
    if (!is_array($json)) throw new RuntimeException('Donatello API returned invalid JSON');
    if ($code < 200 || $code >= 300 || (($json['success'] ?? true) === false && $code !== 200)) {
        throw new RuntimeException('Donatello API error HTTP '.$code.': '.($json['message'] ?? 'unknown error'));
    }
    return $json;
}

function donatello_new_payment(int $userId): array {
    ensure_premium_columns();
    $c = donatello_config();
    if (!donatello_ready()) throw new RuntimeException('Donatello ще не налаштовано адміністратором.');
    // Один пользователь — один активный платёж. Это дополнительная защита
    // от повторной отправки формы и случайного обновления страницы.
    $existing = db()->prepare("SELECT * FROM donatello_payments WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
    $existing->execute([$userId]);
    $row = $existing->fetch();
    if ($row) {
        return [
            'id'=>(int)$row['id'],
            'code'=>$row['match_code'],
            'amount'=>(float)$row['expected_amount'],
            'currency'=>$row['currency'],
            'page_url'=>$c['page_url'],
        ];
    }
    $code = 'QS-' . $userId . '-' . strtoupper(bin2hex(random_bytes(3)));
    db()->prepare('INSERT INTO donatello_payments(user_id,match_code,expected_amount,currency,status) VALUES(?,?,?,?,?)')
        ->execute([$userId,$code,$c['price'],$c['currency'],'pending']);
    return ['code'=>$code,'amount'=>$c['price'],'currency'=>$c['currency'],'page_url'=>$c['page_url']];
}

function donatello_extract_donates(array $response): array {
    $items = $response['content'] ?? $response['donates'] ?? [];
    return is_array($items) ? $items : [];
}

/**
 * Donatello is a creator-page matching flow: the user pays on their page and
 * puts our unique code into the donation message. We reconcile it against the
 * read-only /api/v1/donates API instead of trusting a browser redirect.
 */
function donatello_sync_pending_payments(?int $userId = null, int $maxPages = 5): int {
    ensure_premium_columns();
    $c = donatello_config();
    if (($userId !== null && $userId <= 0) || $c['api_token'] === '') return 0;
    $sql = "SELECT p.*,u.role FROM donatello_payments p JOIN users u ON u.id=p.user_id WHERE p.status='pending' AND u.role='teacher'";
    $params = [];
    if ($userId !== null) { $sql .= ' AND p.user_id=?'; $params[] = $userId; }
    $sql .= ' ORDER BY p.id DESC LIMIT 200';
    $q = db()->prepare($sql);
    $q->execute($params);
    $pending = $q->fetchAll();
    if (!$pending) return 0;
    $byCode = [];
    foreach ($pending as $payment) $byCode[strtoupper($payment['match_code'])] = $payment;
    $donates = [];
    $maxPages = max(1, min(10, $maxPages));
    try {
        for ($page = 0; $page < $maxPages; $page++) {
            if ($page > 0) usleep(1000000); // Donatello throttles frequent API requests.
            $resp = donatello_http_get('https://donatello.to/api/v1/donates?page='.$page.'&size=20', $c['api_token']);
            $batch = donatello_extract_donates($resp);
            $donates = array_merge($donates, $batch);
            if (count($batch) < 20) break;
        }
    } catch (Throwable $e) {
        donatello_log('sync failed', ['user_id'=>$userId,'error'=>$e->getMessage()]);
        throw $e;
    }
    $activated = 0;
    foreach ($donates as $donate) {
        $message = trim((string)($donate['message'] ?? ''));
        if ($message === '') continue;
        $payment = null;
        foreach ($byCode as $code => $candidate) {
            if (preg_match('/(?<![A-Z0-9])'.preg_quote($code,'/').'(?![A-Z0-9])/i', $message)) { $payment = $candidate; break; }
        }
        if (!$payment) continue;
        $pubId = trim((string)($donate['pubId'] ?? $donate['id'] ?? ''));
        $amount = (float)str_replace(',', '.', (string)($donate['amount'] ?? 0));
        $currency = strtoupper((string)($donate['currency'] ?? $c['currency']));
        if ($amount + 0.0001 < (float)$payment['expected_amount'] || $currency !== strtoupper((string)$payment['currency'])) continue;
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT * FROM donatello_payments WHERE id=? AND status='pending' FOR UPDATE");
            $lock->execute([(int)$payment['id']]);
            $locked = $lock->fetch();
            if (!$locked) { $pdo->rollBack(); continue; }
            if ($pubId !== '') {
                $dupe = $pdo->prepare('SELECT id FROM donatello_payments WHERE donatello_pub_id=? LIMIT 1');
                $dupe->execute([$pubId]);
                $existingId = $dupe->fetchColumn();
                if ($existingId && (int)$existingId !== (int)$payment['id']) { $pdo->rollBack(); continue; }
            }
            $pdo->prepare('UPDATE donatello_payments SET status="paid",donatello_pub_id=?,donor_name=?,message=?,paid_amount=?,matched_at=NOW() WHERE id=?')
                ->execute([$pubId ?: null,(string)($donate['clientName'] ?? ''),$message,$amount,(int)$payment['id']]);
            $days = premium_duration_days();
            $pdo->prepare("UPDATE users SET is_premium=1, subscription_expires_at=DATE_ADD(GREATEST(COALESCE(subscription_expires_at,NOW()),NOW()), INTERVAL {$days} DAY) WHERE id=?")
                ->execute([(int)$payment['user_id']]);
            $pdo->prepare('UPDATE donatello_payments SET premium_activated_at=NOW() WHERE id=?')->execute([(int)$payment['id']]);
            $pdo->commit();
            $activated++;
            unset($byCode[strtoupper($payment['match_code'])]);
            donatello_log('premium activated', ['user_id'=>$payment['user_id'],'payment_id'=>$payment['id'],'pub_id'=>$pubId,'amount'=>$amount,'code'=>$payment['match_code']]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            donatello_log('activation failed', ['user_id'=>$payment['user_id'],'payment_id'=>$payment['id'],'error'=>$e->getMessage()]);
        }
    }
    return $activated;
}

function donatello_sync_user(int $userId): int {
    try { return donatello_sync_pending_payments($userId, 2); }
    catch (Throwable $e) { return 0; }
}

function premium_active(?array $user): bool {
    if (!$user) return false;
    if (!array_key_exists('is_premium',$user) || !array_key_exists('subscription_expires_at',$user)) {
        ensure_premium_columns();
        $s=db()->prepare('SELECT is_premium,subscription_expires_at FROM users WHERE id=?');
        $s->execute([(int)$user['id']]);
        $fresh=$s->fetch();
        if ($fresh) { $user=array_merge($user,$fresh); }
    }
    $expires = $user['subscription_expires_at'] ?? null;
    if (!$expires) return (int)($user['is_premium'] ?? 0) === 1;
    $expiry = strtotime((string)$expires);
    if ($expiry === false || $expiry <= time()) return false;

    // The expiration date is authoritative. Repair the legacy/incorrect flag
    // so a valid paid-through date cannot leave Premium inaccessible.
    if ((int)($user['is_premium'] ?? 0) !== 1) {
        ensure_premium_columns();
        db()->prepare('UPDATE users SET is_premium=1 WHERE id=? AND subscription_expires_at=?')
            ->execute([(int)$user['id'], (string)$expires]);
    }
    return true;
}

function quiz_type_limit_key(string $type): string {
    $type = strtolower(trim($type));
    return match ($type) {
        'normal', 'quiz' => 'normal',
        'truefalse', 'true_false' => 'true_false',
        'fill' => 'fill',
        'sentence' => 'sentence',
        'flashcards', 'legacy-flashcards', 'flashcard' => 'flashcards',
        'qa' => 'qa',
        'matching' => 'matching',
        'roulette' => 'roulette',
        'memory' => 'memory',
        default => $type,
    };
}

/**
 * Free teacher: one saved quiz of each kind across all owned courses.
 * Premium/admin: no creation limit.
 * Existing items can always be edited; only creation is blocked.
 */
function require_quiz_type_capacity(array $user, int $courseId, string $type, int $excludeId = 0): void {
    if (($user['role'] ?? '') === 'admin') return;
    if (premium_active($user)) return;
    if (!$courseId || !can_manage_course($courseId, $user)) {
        throw new RuntimeException('Forbidden');
    }

    $key = quiz_type_limit_key($type);
    $labels = ['normal'=>'звичайного тесту','true_false'=>'True / False','fill'=>'вставки слова','sentence'=>'порядку слів','flashcards'=>'карток','qa'=>'Питання → Відповідь','matching'=>'сопоставлення','roulette'=>'рулетки','memory'=>'закритих карток'];
    $label = $labels[$key] ?? $key;
    $params = [(int)$user['id']];
    $whereExclude = '';
    if ($excludeId > 0) {
        $whereExclude = ' AND q.id <> ?';
        $params[] = $excludeId;
    }

    $sql = match ($key) {
        'normal' => "SELECT COUNT(*) FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE c.teacher_id=? AND COALESCE(q.quiz_type,'normal')='normal'{$whereExclude}",
        'true_false', 'fill', 'sentence', 'flashcards', 'qa' => "SELECT COUNT(*) FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE c.teacher_id=? AND q.quiz_type=?{$whereExclude}",
        'matching' => "SELECT COUNT(*) FROM matching_sets m JOIN courses c ON c.id=m.course_id WHERE c.teacher_id=?",
        'roulette' => "SELECT COUNT(*) FROM roulette_sets r JOIN courses c ON c.id=r.course_id WHERE c.teacher_id=?",
        'memory' => "SELECT COUNT(*) FROM memory_sets m JOIN courses c ON c.id=m.course_id WHERE c.teacher_id=?",
        default => null,
    };
    if ($sql === null) return;

    if (in_array($key, ['true_false','fill','sentence','flashcards','qa'], true)) {
        array_splice($params, 1, 0, [$key]);
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    if ((int)$st->fetchColumn() >= 1) {
        throw new RuntimeException("Без Premium можна створити лише один квиз типу «{$label}». Видаліть існуючий або оформіть Premium.");
    }
}

function active_live_room_count(int $teacherId): int {
    $s = db()->prepare("SELECT COUNT(*) FROM live_sessions s JOIN live_quizzes q ON q.id=s.quiz_id WHERE q.creator_id=? AND s.status IN ('lobby','running')");
    $s->execute([$teacherId]);
    return (int)$s->fetchColumn();
}

function require_live_room_capacity(array $user): void {
    if (($user['role'] ?? '') === 'admin') return;
    ensure_premium_columns();
    if (premium_active($user)) return;
    if (active_live_room_count((int)$user['id']) >= 1) {
        flash('Без Premium можна мати лише одну активну Live-гру. Завершіть попередню гру або оформіть Premium.');
        redirect('subscribe');
    }
}


function ensure_live_tables(): void {
    ensure_premium_columns();
    static $done = false;
    if ($done) return;
    $pdo = db();
    /* Saved Live quizzes: no PIN/QR is stored here. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_quizzes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        course_id INT UNSIGNED NOT NULL,
        creator_id INT UNSIGNED NOT NULL,
        title VARCHAR(180) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(course_id), INDEX(creator_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_quiz_questions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT UNSIGNED NOT NULL,
        prompt TEXT NOT NULL,
        image_url VARCHAR(1000) DEFAULT NULL,
        time_limit INT UNSIGNED NOT NULL DEFAULT 20,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX(quiz_id,sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_quiz_answers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        question_id INT UNSIGNED NOT NULL,
        answer_text TEXT NOT NULL,
        is_correct TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX(question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    /* A session is one concrete launch. PIN/QR belong ONLY here. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_sessions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT UNSIGNED NOT NULL,
        game_code VARCHAR(12) NOT NULL UNIQUE,
        status ENUM('lobby','running','finished') NOT NULL DEFAULT 'lobby',
        current_question INT NOT NULL DEFAULT -1,
        question_started_at TIMESTAMP(6) NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(quiz_id), INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_session_players (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        session_id INT UNSIGNED NOT NULL,
        nickname VARCHAR(80) NOT NULL,
        avatar VARCHAR(20) NOT NULL DEFAULT '🦊',
        player_token CHAR(64) NOT NULL UNIQUE,
        score INT NOT NULL DEFAULT 0,
        joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_session_responses (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        session_id INT UNSIGNED NOT NULL,
        question_id INT UNSIGNED NOT NULL,
        player_id INT UNSIGNED NOT NULL,
        answer_id INT UNSIGNED NOT NULL,
        is_correct TINYINT(1) NOT NULL DEFAULT 0,
        points INT NOT NULL DEFAULT 0,
        responded_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY one_answer(session_id,question_id,player_id),
        INDEX(session_id,question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}
function live_new_code(): string {
    $chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code='';
        for($i=0;$i<6;$i++) $code.=$chars[random_int(0,strlen($chars)-1)];
        $s=db()->prepare('SELECT id FROM live_sessions WHERE game_code=? LIMIT 1'); $s->execute([$code]);
    } while($s->fetch());
    return $code;
}

function smtp_read_response($fp): string {
    $data='';
    while (!feof($fp)) {
        $line=fgets($fp,4096);
        if ($line===false) break;
        $data.=$line;
        if (strlen($line)>=4 && $line[3]===' ') break;
    }
    return $data;
}
function smtp_log(string $message): void {
    $line='['.date('Y-m-d H:i:s').'] '.$message."\n";
    @file_put_contents(dirname(__DIR__).'/storage/mail.log',$line,FILE_APPEND|LOCK_EX);
}
function smtp_send(string $to, string $subject, string $body): bool {
    $host=trim(env('SMTP_HOST','smtp.gmail.com'));
    $port=(int)env('SMTP_PORT','587');
    $user=trim(env('SMTP_USER',''));
    $pass=preg_replace('/\s+/','', (string)env('SMTP_PASS',''));
    $from=trim(env('SMTP_FROM',$user));
    $name=trim(env('SMTP_FROM_NAME','QuizSpace'));

    if(!$to || !filter_var($to,FILTER_VALIDATE_EMAIL) || !$user || !$pass){
        smtp_log('SMTP CONFIG ERROR: SMTP_USER/SMTP_PASS/recipient missing or invalid');
        return false;
    }

    // 465 = implicit TLS, 587 = STARTTLS.
    $transport = $port === 465 ? 'ssl://' : 'tcp://';
    $context=stream_context_create([
        'ssl'=>[
            'verify_peer'=>true,
            'verify_peer_name'=>true,
            'allow_self_signed'=>false,
            'SNI_enabled'=>true,
            'peer_name'=>$host
        ]
    ]);

    $fp=@stream_socket_client(
        "{$transport}{$host}:{$port}",
        $errno,$errstr,20,STREAM_CLIENT_CONNECT,$context
    );
    if(!$fp){
        smtp_log("CONNECT {$host}:{$port} failed: {$errno} {$errstr}");
        return false;
    }
    stream_set_timeout($fp,20);

    $r=smtp_read_response($fp);
    if(strpos($r,'220')!==0){
        smtp_log('SERVER GREETING: '.trim($r));
        fclose($fp);
        return false;
    }

    $write=function(string $cmd)use($fp): void {
        fwrite($fp,$cmd."\r\n");
    };

    $write("EHLO quizspace.local");
    $r=smtp_read_response($fp);
    if(strpos($r,'250')===false){
        smtp_log('EHLO: '.trim($r));
        fclose($fp);
        return false;
    }

    if($port !== 465){
        $write("STARTTLS");
        $r=smtp_read_response($fp);
        if(strpos($r,'220')!==0){
            smtp_log('STARTTLS: '.trim($r));
            fclose($fp);
            return false;
        }

        $crypto=stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if($crypto!==true){
            smtp_log('TLS HANDSHAKE FAILED');
            fclose($fp);
            return false;
        }

        $write("EHLO quizspace.local");
        $r=smtp_read_response($fp);
        if(strpos($r,'250')===false){
            smtp_log('EHLO AFTER TLS: '.trim($r));
            fclose($fp);
            return false;
        }
    }

    // Gmail supports AUTH PLAIN with an App Password.
    $write("AUTH PLAIN");
    $r=smtp_read_response($fp);
    if(strpos($r,'334')===false){
        smtp_log('AUTH PLAIN rejected: '.trim($r));
        fclose($fp);
        return false;
    }

    $write(base64_encode("\0".$user."\0".$pass));
    $r=smtp_read_response($fp);
    if(strpos($r,'235')===false){
        smtp_log('AUTH FAILED: '.trim($r));
        fclose($fp);
        return false;
    }

    $write("MAIL FROM:<{$from}>");
    $r=smtp_read_response($fp);
    if(strpos($r,'250')===false){
        smtp_log('MAIL FROM: '.trim($r));
        fclose($fp);
        return false;
    }

    $write("RCPT TO:<{$to}>");
    $r=smtp_read_response($fp);
    if(strpos($r,'250')===false && strpos($r,'251')===false){
        smtp_log('RCPT TO: '.trim($r));
        fclose($fp);
        return false;
    }

    $write("DATA");
    $r=smtp_read_response($fp);
    if(strpos($r,'354')===false){
        smtp_log('DATA: '.trim($r));
        fclose($fp);
        return false;
    }

    $safeSubject=str_replace(["\r","\n"],'',$subject);
    $subjectHeader='=?UTF-8?B?'.base64_encode($safeSubject).'?=';
    $headers="From: {$name} <{$from}>\r\n".
             "To: <{$to}>\r\n".
             "Subject: {$subjectHeader}\r\n".
             "MIME-Version: 1.0\r\n".
             "Content-Type: text/plain; charset=UTF-8\r\n".
             "X-Mailer: QuizSpace\r\n";

    $body=str_replace(["\r\n","\r"],"\n",$body);
    $body=str_replace("\n.","\n..",$body);
    fwrite($fp,$headers."\r\n".$body."\r\n.\r\n");

    $r=smtp_read_response($fp);
    $ok=strpos($r,'250')!==false;
    smtp_log($ok ? "MAIL SENT to {$to}" : 'MESSAGE REJECTED: '.trim($r));

    $write("QUIT");
    @smtp_read_response($fp);
    fclose($fp);
    return $ok;
}

function send_reset_code(string $email, string $code): bool {
    $subject='QuizSpace — код восстановления пароля';
    $message="Ваш код восстановления: {$code}\nКод действует 10 минут.";
    $from=env('MAIL_FROM','no-reply@quizspace.local');
    $headers="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: {$from}\r\n";
    return smtp_send($email,$subject,$message);
}


function client_ip(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}
function ip_hash(): string {
    return hash('sha256', client_ip() . '|' . env('APP_KEY','quizspace'));
}
function recent_login_failures(?int $userId=null): int {
    $where = $userId ? 'user_id=?' : 'ip_hash=?';
    $value = $userId ? $userId : ip_hash();
    $q=db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE {$where} AND success=0 AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
    $q->execute([$value]); return (int)$q->fetchColumn();
}
function record_login_attempt(?int $userId, bool $success): void {
    db()->prepare('INSERT INTO login_attempts(user_id,ip_hash,success) VALUES(?,?,?)')->execute([$userId,ip_hash(),$success?1:0]);
}
function login_locked(?int $userId=null): bool {
    return recent_login_failures($userId) >= 5;
}
function send_login_code(string $email, string $code): bool {
    $subject='QuizSpace — подтверждение нового устройства';
    $message="Ваш код входа: {$code}\nКод действует 10 минут.\nЕсли это были не вы, измените пароль.";
    $from=env('MAIL_FROM','no-reply@quizspace.local');
    $headers="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: {$from}\r\n";
    return smtp_send($email,$subject,$message);
}
function remember_device(int $userId): void {
    $raw=bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO user_devices(user_id,device_token_hash,device_name,ip_address,expires_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 180 DAY))')
      ->execute([$userId,hash('sha256',$raw),substr($_SERVER['HTTP_USER_AGENT']??'device',0,255),client_ip()]);
    setcookie('qs_device',$raw,['expires'=>time()+180*86400,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax']);
}
function known_device(int $userId): bool {
    $raw=$_COOKIE['qs_device']??'';
    if (!$raw) return false;
    $q=db()->prepare('SELECT 1 FROM user_devices WHERE user_id=? AND device_token_hash=? AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1');
    $q->execute([$userId,hash('sha256',$raw)]); return (bool)$q->fetchColumn();
}
function assignment_allows(int $contentId, string $contentType, array $u): bool {
    if ($u['role']!=='student') return true;
    $q=db()->prepare("SELECT a.id FROM assignments a
      JOIN assignment_students ast ON ast.assignment_id=a.id
      WHERE a.content_id=? AND a.content_type=? AND ast.student_id=?
      AND a.status='active'
      AND (a.available_from IS NULL OR a.available_from<=NOW())
      AND (a.available_until IS NULL OR a.available_until>=NOW())
      LIMIT 1");
    $q->execute([$contentId,$contentType,$u['id']]);
    if ($q->fetchColumn()) return true;
    $q=db()->prepare("SELECT COUNT(*) FROM assignments WHERE content_id=? AND content_type=? AND status='active'");
    $q->execute([$contentId,$contentType]);
    return (int)$q->fetchColumn()===0;
}
