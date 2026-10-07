<?php
require __DIR__.'/config/config.php';

// Поточний користувач доступний усім маршрутам. Для неавторизованих значення буде null.
$u = me();

$path=trim($_GET['url']??'','/');
$parts=$path===''?[]:explode('/',$path);
$action=$parts[0]??'home';
$id=isset($parts[1])&&ctype_digit($parts[1])?(int)$parts[1]:null;
$sub=$parts[2]??null;
$isDonatelloSync = ($path === 'payment/donatello-sync');
// Routes with a nested resource id, e.g. admin/teacher/2.
$nestedId=isset($parts[2])&&ctype_digit($parts[2])?(int)$parts[2]:null;

try {
    /* ---------- Donatello payment reconciliation ---------- */
    if ($isDonatelloSync && $_SERVER['REQUEST_METHOD']==='POST') {
        $u=require_login();
        if (($u['role'] ?? '') !== 'teacher') { http_response_code(403); exit('Forbidden'); }
        ensure_premium_columns();
        $activated=donatello_sync_user((int)$u['id']);
        $fresh=db()->prepare('SELECT is_premium,subscription_expires_at FROM users WHERE id=? LIMIT 1');
        $fresh->execute([(int)$u['id']]);
        $state=$fresh->fetch() ?: [];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true,'activated'=>$activated,'is_premium'=>(int)($state['is_premium']??0),'subscription_expires_at'=>$state['subscription_expires_at']??null],JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action==='logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        redirect('login');
    }

    if (in_array($action,['login','register','forgot','reset'],true)) {
        if ($action==='login') {
            if ($_SERVER['REQUEST_METHOD']==='POST') {
                check_csrf();
                $s=db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
                $s->execute([strtolower(trim($_POST['email']??''))]);
                $u=$s->fetch();
                if ($u && login_locked((int)$u['id'])) {
                    flash('Слишком много попыток. Попробуйте снова через 15 минут.');
                } elseif (!$u || !password_verify($_POST['password']??'',$u['password_hash'])) {
                    record_login_attempt($u['id']??null,false);
                    flash('Неверный email или пароль.');
                } else {
                    record_login_attempt((int)$u['id'],true);
                    if (known_device((int)$u['id'])) {
                        session_regenerate_id(true); $_SESSION['user_id']=$u['id']; redirect('home');
                    }
                    $code=(string)random_int(100000,999999);
                    db()->prepare('INSERT INTO login_codes(user_id,code_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')->execute([$u['id'],password_hash($code,PASSWORD_DEFAULT)]);
                    $_SESSION['pending_2fa_user']=(int)$u['id'];
                    $sent=send_login_code($u['email'],$code);
                    $_SESSION['dev_2fa_code']=null;
                    redirect('verify-device');
                }
            }
            view('auth/login'); exit;
        }
        if ($action==='register') {
            if ($_SERVER['REQUEST_METHOD']==='POST') {
                check_csrf();
                $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??'')); $pass=$_POST['password']??'';
                if (!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pass)<6) flash('Введите имя, корректный email и пароль от 6 символов.');
                else {
                    try {
                        db()->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)')->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),'student']);
                        flash('Регистрация завершена. Теперь войдите.'); redirect('login');
                    } catch(Throwable $e) { flash('Этот email уже зарегистрирован.'); }
                }
            }
            view('auth/register'); exit;
        }
        if ($action==='forgot') {
            if ($_SERVER['REQUEST_METHOD']==='POST') {
                check_csrf();
                $email=strtolower(trim($_POST['email']??''));
                $s=db()->prepare('SELECT id,email FROM users WHERE email=? LIMIT 1'); $s->execute([$email]); $u=$s->fetch();
                if ($u) {
                    db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$u['id']]);
                    $code=(string)random_int(100000,999999);
                    db()->prepare('INSERT INTO password_resets(user_id,code_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')->execute([$u['id'],password_hash($code,PASSWORD_DEFAULT)]);
                    $_SESSION['reset_email']=$u['email'];
                    $sent=send_reset_code($u['email'],$code);
                    $_SESSION['dev_reset_code']=null;
                    flash($sent?'Код отправлен на email.':'Не удалось отправить письмо. Проверьте SMTP-настройки.');
                    redirect('reset');
                }
                flash('Если такой email существует, инструкция будет отправлена.');
            }
            view('auth/forgot'); exit;
        }
        if ($action==='reset') {
            if ($_SERVER['REQUEST_METHOD']==='POST') {
                check_csrf();
                $email=$_SESSION['reset_email']??'';
                $s=db()->prepare('SELECT u.id,pr.* FROM users u JOIN password_resets pr ON pr.user_id=u.id WHERE u.email=? AND pr.used_at IS NULL AND pr.expires_at>NOW() ORDER BY pr.id DESC LIMIT 1');
                $s->execute([$email]); $r=$s->fetch();
                if ($r && password_verify($_POST['code']??'',$r['code_hash']) && strlen($_POST['password']??'')>=6) {
                    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$r['id']]);
                    db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$r['id']]);
                    unset($_SESSION['reset_email'],$_SESSION['dev_reset_code']);
                    flash('Пароль изменён.'); redirect('login');
                }
                flash('Неверный или просроченный код.');
            }
            view('auth/reset',['dev_code'=>$_SESSION['dev_reset_code']??null]); exit;
        }
    }


    if ($action==='verify-device') {
        $pending=(int)($_SESSION['pending_2fa_user']??0);
        if (!$pending) redirect('login');
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            check_csrf();
            $q=db()->prepare('SELECT * FROM login_codes WHERE user_id=? AND used_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1');
            $q->execute([$pending]); $r=$q->fetch();
            if ($r && password_verify($_POST['code']??'',$r['code_hash'])) {
                db()->prepare('UPDATE login_codes SET used_at=NOW() WHERE id=?')->execute([$r['id']]);
                remember_device($pending);
                session_regenerate_id(true); $_SESSION['user_id']=$pending; unset($_SESSION['pending_2fa_user'],$_SESSION['dev_2fa_code']);
                flash('Новое устройство подтверждено.'); redirect('home');
            }
            flash('Неверный или просроченный код.');
        }
        view('auth/verify-device',['dev_code'=>$_SESSION['dev_2fa_code']??null]); exit;
    }

    if ($action==='home' && empty($_SESSION['user_id'])) {
        view('public/home',['me'=>null]); exit;
    }

    /* ---------- QuizSpace Live: saved quizzes + launch sessions ---------- */
    if (str_starts_with($action,'live-join') || str_starts_with($action,'live-player') || str_starts_with($action,'live-host') || str_starts_with($action,'live-quiz') || $action==='live-create' || $action==='live-launch') {
        ensure_live_tables();
    }

    if ($action==='live-join' && $id===null && isset($parts[1])) {
        $code=strtoupper(trim($parts[1]));
        $s=db()->prepare("SELECT s.*,q.title FROM live_sessions s JOIN live_quizzes q ON q.id=s.quiz_id WHERE s.game_code=? LIMIT 1"); $s->execute([$code]); $session=$s->fetch();
        if(!$session) { http_response_code(404); exit('Гру не знайдено'); }
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf(); $nickname=trim((string)($_POST['nickname']??'')); $avatar=(string)($_POST['avatar']??'🦊');
            $validAvatars=['🦊','🐼','🐸','🐱','🐯','🦄','🐙','🐨','🐵','🦁','🐧','🐲'];
            if($nickname==='') flash('Введіть імʼя.');
            elseif(!in_array($avatar,$validAvatars,true)) flash('Оберіть фігурку.');
            elseif($session['status']==='finished') flash('Ця гра вже завершена.');
            else {
                $token=bin2hex(random_bytes(32));
                db()->prepare('INSERT INTO live_session_players(session_id,nickname,avatar,player_token) VALUES(?,?,?,?)')->execute([(int)$session['id'],mb_substr($nickname,0,30),$avatar,$token]);
                $_SESSION['live_player_id']=(int)db()->lastInsertId(); $_SESSION['live_player_token']=$token;
                redirect('live-player/'.$session['id']);
            }
        }
        view('live-join',['game'=>$session,'me'=>null]); exit;
    }

    if ($action==='live-player' && $id) {
        ensure_live_tables();
        $s=db()->prepare("SELECT s.*,q.title FROM live_sessions s JOIN live_quizzes q ON q.id=s.quiz_id WHERE s.id=?"); $s->execute([$id]); $session=$s->fetch();
        $pid=(int)($_SESSION['live_player_id']??0); $token=(string)($_SESSION['live_player_token']??'');
        $s=db()->prepare("SELECT * FROM live_session_players WHERE id=? AND session_id=? AND player_token=?");$s->execute([$pid,$id,$token]);$player=$s->fetch();
        if(!$session||!$player){http_response_code(403);exit('Спочатку приєднайтеся до гри.');}
        view('live-player',['game'=>$session,'player'=>$player,'me'=>null]); exit;
    }

    if ($action==='live-player-api' && $id) {
        ensure_live_tables(); header('Content-Type: application/json; charset=utf-8');
        $s=db()->prepare("SELECT * FROM live_sessions WHERE id=?");$s->execute([$id]);$session=$s->fetch();
        $pid=(int)($_SESSION['live_player_id']??0);$token=(string)($_SESSION['live_player_token']??'');
        $s=db()->prepare("SELECT * FROM live_session_players WHERE id=? AND session_id=? AND player_token=?");$s->execute([$pid,$id,$token]);$player=$s->fetch();
        if(!$session||!$player){http_response_code(403);echo json_encode(['error'=>'not_joined']);exit;}
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();
            $qi=(int)$session['current_question']; $answerId=(int)($_POST['answer_id']??0);
            $awardedPoints=0; $answerCorrect=false; $newScore=(int)$player['score'];
            if($session['status']==='running' && $qi>=0){
                $qs=db()->prepare("SELECT * FROM live_quiz_questions WHERE quiz_id=? ORDER BY sort_order,id LIMIT 1 OFFSET ?");$qs->execute([(int)$session['quiz_id'],$qi]);$question=$qs->fetch();
                if($question){
                    $as=db()->prepare('SELECT * FROM live_quiz_answers WHERE id=? AND question_id=?');$as->execute([$answerId,$question['id']]);$answer=$as->fetch();
                    if($answer){
                        $exists=db()->prepare('SELECT id FROM live_session_responses WHERE session_id=? AND question_id=? AND player_id=?');$exists->execute([$id,$question['id'],$pid]);
                        if(!$exists->fetch()){
                            $remaining=(int)db()->query("SELECT GREATEST(0, {$question['time_limit']} - FLOOR(TIMESTAMPDIFF(MICROSECOND, '{$session['question_started_at']}', CURRENT_TIMESTAMP(6))/1000000))")->fetchColumn();
                            if($remaining<=0){ echo json_encode(['ok'=>false,'expired'=>true,'points'=>0,'score'=>(int)$player['score']]); exit; }
                            $answerCorrect=(int)$answer['is_correct']===1;
                            $awardedPoints=$answerCorrect ? max(100,(int)round(1000*($remaining/max(1,(int)$question['time_limit'])))) : 0;
                            db()->prepare('INSERT INTO live_session_responses(session_id,question_id,player_id,answer_id,is_correct,points) VALUES(?,?,?,?,?,?)')->execute([$id,$question['id'],$pid,$answerId,$answerCorrect?1:0,$awardedPoints]);
                            db()->prepare('UPDATE live_session_players SET score=score+? WHERE id=?')->execute([$awardedPoints,$pid]);
                            $newScore+=(int)$awardedPoints;
                        }
                    }
                }
            }
            $rankQ=db()->prepare('SELECT nickname,avatar,score FROM live_session_players WHERE session_id=? ORDER BY score DESC,joined_at');
            $rankQ->execute([$id]); $answerRanking=$rankQ->fetchAll(); $answerRank=1;
            foreach($answerRanking as $ri=>$rr){ if((int)$rr['score']===$newScore){$answerRank=$ri+1;break;} }
            echo json_encode(['ok'=>true,'points'=>(int)$awardedPoints,'correct'=>$answerCorrect,'score'=>$newScore,'rank'=>$answerRank,'ranking'=>array_slice($answerRanking,0,5)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
        }
        $questions=db()->prepare("SELECT * FROM live_quiz_questions WHERE quiz_id=? ORDER BY sort_order,id");$questions->execute([(int)$session['quiz_id']]);$qs=$questions->fetchAll();
        $current=(int)$session['current_question'];$question=null;$answered=false;$remaining=0;
        if($session['status']==='running' && isset($qs[$current])){
            $question=$qs[$current];$aa=db()->prepare('SELECT id,answer_text FROM live_quiz_answers WHERE question_id=? ORDER BY sort_order,id');$aa->execute([$question['id']]);$question['answers']=$aa->fetchAll();$question['number']=$current+1;
            $remaining=(int)db()->query("SELECT GREATEST(0, {$question['time_limit']} - FLOOR(TIMESTAMPDIFF(MICROSECOND, '{$session['question_started_at']}', CURRENT_TIMESTAMP(6))/1000000))")->fetchColumn();
            $rr=db()->prepare('SELECT id FROM live_session_responses WHERE session_id=? AND question_id=? AND player_id=?');$rr->execute([$id,$question['id'],$pid]);$answered=(bool)$rr->fetch();
        }
        $rankQ=db()->prepare('SELECT nickname,avatar,score FROM live_session_players WHERE session_id=? ORDER BY score DESC,joined_at');
        $rankQ->execute([$id]);
        $ranking=$rankQ->fetchAll();
        $rank=1; foreach($ranking as $i=>$row){ if((int)$row['score'] === (int)$player['score']){ $rank=$i+1; break; } }
        echo json_encode(['status'=>$session['status'],'score'=>(int)$player['score'],'rank'=>$rank,'ranking'=>array_slice($ranking,0,5),'total'=>count($qs),'question'=>$question,'remaining'=>$remaining,'answered'=>$answered,'players'=>$ranking],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }

    if($action==='live-create' && $id){
        require_role('teacher','admin'); ensure_live_tables(); $courseId=$id;
        if(!can_manage_course($courseId,$u))exit('Forbidden');
        $submittedQuestions=null; $submittedTitle=null;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();
            $title=trim((string)($_POST['title']??'')); $prompts=$_POST['prompt']??[]; $images=$_POST['image_url']??[];
            $times=$_POST['time_limit']??[]; $answers=$_POST['answer']??[]; $correct=$_POST['correct']??[];
            $submittedTitle=$title;
            $submittedQuestions=[];
            foreach((array)$prompts as $i=>$prompt){
                $rawAnswers=is_array($answers[$i]??null)?$answers[$i]:[];
                $correctIndex=(int)($correct[$i]??0);
                $submittedQuestions[]=['prompt'=>(string)$prompt,'image_url'=>(string)($images[$i]??''),'time_limit'=>(int)($times[$i]??20),'answers'=>array_map(static fn($text,$j)=>['answer_text'=>(string)$text,'is_correct'=>(int)$j===$correctIndex],$rawAnswers,array_keys($rawAnswers))];
            }
            if($title==='')$title='QuizSpace Live'; if(!is_array($prompts))$prompts=[$prompts]; $valid=[];
            foreach($prompts as $i=>$p){
                $p=trim((string)$p); $rawAnswers=is_array($answers[$i]??null)?$answers[$i]:[]; $aa=[]; $ci=null; $postedCorrect=(int)($correct[$i]??0);
                foreach($rawAnswers as $answerIndex=>$answerText){$answerText=trim((string)$answerText);if($answerText==='')continue;if((int)$answerIndex===$postedCorrect)$ci=count($aa);$aa[]=$answerText;}
                if($p!==''&&count($aa)>=2){
                    if($ci===null)$ci=0;
                    $valid[]=[$p,trim((string)($images[$i]??'')),max(5,min(120,(int)($times[$i]??20))),$aa,$ci];
                }
            }
            if(!$valid) flash('Додайте хоча б одне питання з двома відповідями.');
            else{
                db()->beginTransaction();
                try{
                    $qz=db()->prepare('INSERT INTO live_quizzes(course_id,creator_id,title) VALUES(?,?,?)');
                    $qz->execute([$courseId,$u['id'],mb_substr($title,0,180)]); $qid=(int)db()->lastInsertId();
                    $iq=db()->prepare('INSERT INTO live_quiz_questions(quiz_id,prompt,image_url,time_limit,sort_order) VALUES(?,?,?,?,?)');
                    $ia=db()->prepare('INSERT INTO live_quiz_answers(question_id,answer_text,is_correct,sort_order) VALUES(?,?,?,?)');
                    foreach($valid as $n=>$item){
                        $iq->execute([$qid,$item[0],$item[1]!==''?$item[1]:null,$item[2],$n]); $qq=(int)db()->lastInsertId();
                        foreach($item[3] as $j=>$text)$ia->execute([$qq,$text,$j===$item[4]?1:0,$j]);
                    }
                    db()->commit(); redirect('live-quiz/'.$qid);
                }catch(Throwable $e){
                    if(db()->inTransaction())db()->rollBack(); flash('Не вдалося зберегти квіз: '.$e->getMessage());
                }
            }
        }
        $formQuiz=$submittedTitle!==null?['title'=>$submittedTitle]:null;
        view('live-create',['courseId'=>$courseId,'me'=>$u,'edit'=>false,'quiz'=>$formQuiz,'questions'=>$submittedQuestions??[]]);exit;
    }

    if($action==='live-edit' && $id){
        require_role('teacher','admin'); ensure_live_tables();
        $s=db()->prepare('SELECT * FROM live_quizzes WHERE id=?'); $s->execute([$id]); $quiz=$s->fetch();
        if(!$quiz||!can_manage_course((int)$quiz['course_id'],$u))exit('Forbidden');
        $qs=db()->prepare('SELECT * FROM live_quiz_questions WHERE quiz_id=? ORDER BY sort_order,id'); $qs->execute([$id]); $questions=$qs->fetchAll();
        foreach($questions as &$qq){
            $a=db()->prepare('SELECT * FROM live_quiz_answers WHERE question_id=? ORDER BY sort_order,id'); $a->execute([$qq['id']]); $qq['answers']=$a->fetchAll();
        } unset($qq);
        $submittedQuestions=null; $submittedTitle=null;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();
            $active=db()->prepare("SELECT COUNT(*) FROM live_sessions WHERE quiz_id=? AND status<>'finished'"); $active->execute([$id]);
            if((int)$active->fetchColumn()>0){ flash('Цей Live-квіз зараз використовується в активній грі. Завершіть гру перед редагуванням.'); redirect('live-quiz/'.$id); }
            $title=trim((string)($_POST['title']??'')); $prompts=$_POST['prompt']??[]; $images=$_POST['image_url']??[];
            $times=$_POST['time_limit']??[]; $answers=$_POST['answer']??[]; $correct=$_POST['correct']??[];
            $submittedTitle=$title;
            $submittedQuestions=[];
            foreach((array)$prompts as $i=>$prompt){
                $rawAnswers=is_array($answers[$i]??null)?$answers[$i]:[];
                $correctIndex=(int)($correct[$i]??0);
                $submittedQuestions[]=['prompt'=>(string)$prompt,'image_url'=>(string)($images[$i]??''),'time_limit'=>(int)($times[$i]??20),'answers'=>array_map(static fn($text,$j)=>['answer_text'=>(string)$text,'is_correct'=>(int)$j===$correctIndex],$rawAnswers,array_keys($rawAnswers))];
            }
            if($title==='')$title='QuizSpace Live'; if(!is_array($prompts))$prompts=[$prompts]; $valid=[];
            foreach($prompts as $i=>$p){
                $p=trim((string)$p); $rawAnswers=is_array($answers[$i]??null)?$answers[$i]:[]; $aa=[]; $ci=null; $postedCorrect=(int)($correct[$i]??0);
                foreach($rawAnswers as $answerIndex=>$answerText){$answerText=trim((string)$answerText);if($answerText==='')continue;if((int)$answerIndex===$postedCorrect)$ci=count($aa);$aa[]=$answerText;}
                if($p!==''&&count($aa)>=2){
                    if($ci===null)$ci=0;
                    $valid[]=[$p,trim((string)($images[$i]??'')),max(5,min(120,(int)($times[$i]??20))),$aa,$ci];
                }
            }
            if(!$valid) flash('Додайте хоча б одне питання з двома відповідями.');
            else{
                db()->beginTransaction();
                try{
                    db()->prepare('UPDATE live_quizzes SET title=? WHERE id=?')->execute([mb_substr($title,0,180),$id]);
                    /* Existing sessions keep their historical snapshot; the saved quiz itself is replaced. */
                    $oldQ=db()->prepare('SELECT id FROM live_quiz_questions WHERE quiz_id=?'); $oldQ->execute([$id]);
                    $oldIds=array_column($oldQ->fetchAll(),'id');
                    if($oldIds){
                        $in=implode(',',array_fill(0,count($oldIds),'?'));
                        db()->prepare("DELETE FROM live_quiz_answers WHERE question_id IN ($in)")->execute($oldIds);
                    }
                    db()->prepare('DELETE FROM live_quiz_questions WHERE quiz_id=?')->execute([$id]);
                    $iq=db()->prepare('INSERT INTO live_quiz_questions(quiz_id,prompt,image_url,time_limit,sort_order) VALUES(?,?,?,?,?)');
                    $ia=db()->prepare('INSERT INTO live_quiz_answers(question_id,answer_text,is_correct,sort_order) VALUES(?,?,?,?)');
                    foreach($valid as $n=>$item){
                        $iq->execute([$id,$item[0],$item[1]!==''?$item[1]:null,$item[2],$n]); $qq=(int)db()->lastInsertId();
                        foreach($item[3] as $j=>$text)$ia->execute([$qq,$text,$j===$item[4]?1:0,$j]);
                    }
                    db()->commit(); flash('Live-квіз оновлено.'); redirect('live-quiz/'.$id);
                }catch(Throwable $e){
                    if(db()->inTransaction())db()->rollBack(); flash('Не вдалося оновити квіз: '.$e->getMessage());
                }
            }
        }
        if($submittedTitle!==null)$quiz['title']=$submittedTitle;
        view('live-create',['courseId'=>(int)$quiz['course_id'],'me'=>$u,'edit'=>true,'quiz'=>$quiz,'questions'=>$submittedQuestions??$questions]);exit;
    }

    if($action==='live-delete' && $id && $_SERVER['REQUEST_METHOD']==='POST'){
        require_role('teacher','admin'); ensure_live_tables(); check_csrf();
        $s=db()->prepare('SELECT * FROM live_quizzes WHERE id=?'); $s->execute([$id]); $quiz=$s->fetch();
        if(!$quiz||!can_manage_course((int)$quiz['course_id'],$u))exit('Forbidden');
        db()->beginTransaction();
        try{
            $sessions=db()->prepare('SELECT id FROM live_sessions WHERE quiz_id=?'); $sessions->execute([$id]); $sessionIds=array_column($sessions->fetchAll(),'id');
            if($sessionIds){
                $in=implode(',',array_fill(0,count($sessionIds),'?'));
                db()->prepare("DELETE FROM live_session_responses WHERE session_id IN ($in)")->execute($sessionIds);
                db()->prepare("DELETE FROM live_session_players WHERE session_id IN ($in)")->execute($sessionIds);
                db()->prepare("DELETE FROM live_sessions WHERE id IN ($in)")->execute($sessionIds);
            }
            $questions=db()->prepare('SELECT id FROM live_quiz_questions WHERE quiz_id=?'); $questions->execute([$id]); $qids=array_column($questions->fetchAll(),'id');
            if($qids){
                $in=implode(',',array_fill(0,count($qids),'?'));
                db()->prepare("DELETE FROM live_quiz_answers WHERE question_id IN ($in)")->execute($qids);
                db()->prepare("DELETE FROM live_quiz_questions WHERE id IN ($in)")->execute($qids);
            }
            db()->prepare('DELETE FROM live_quizzes WHERE id=?')->execute([$id]); db()->commit();
            flash('Live-квіз видалено.'); redirect('course/'.(int)$quiz['course_id']);
        }catch(Throwable $e){
            if(db()->inTransaction())db()->rollBack(); flash('Не вдалося видалити квіз: '.$e->getMessage()); redirect('course/'.(int)$quiz['course_id']);
        }
    }

    if($action==='live-quiz' && $id){
        require_role('teacher','admin');ensure_live_tables();$s=db()->prepare('SELECT * FROM live_quizzes WHERE id=?');$s->execute([$id]);$quiz=$s->fetch();if(!$quiz||!can_manage_course((int)$quiz['course_id'],$u))exit('Forbidden');
        $qs=db()->prepare('SELECT * FROM live_quiz_questions WHERE quiz_id=? ORDER BY sort_order,id');$qs->execute([$id]);$questions=$qs->fetchAll();view('live-quiz',['quiz'=>$quiz,'questions'=>$questions,'me'=>$u]);exit;
    }

    if($action==='live-launch' && $id && $_SERVER['REQUEST_METHOD']==='POST'){
        require_role('teacher','admin');ensure_live_tables();check_csrf();$s=db()->prepare('SELECT * FROM live_quizzes WHERE id=?');$s->execute([$id]);$quiz=$s->fetch();if(!$quiz||!can_manage_course((int)$quiz['course_id'],$u))exit('Forbidden');
        require_live_room_capacity($u);
        $code=live_new_code();db()->prepare('INSERT INTO live_sessions(quiz_id,game_code) VALUES(?,?)')->execute([$id,$code]);$sid=(int)db()->lastInsertId();redirect('live-host/'.$sid);exit;
    }

    if($action==='live-host' && $id){
        require_role('teacher','admin');ensure_live_tables();$s=db()->prepare('SELECT s.*,q.title,q.course_id FROM live_sessions s JOIN live_quizzes q ON q.id=s.quiz_id WHERE s.id=?');$s->execute([$id]);$session=$s->fetch();if(!$session||!can_manage_course((int)$session['course_id'],$u))exit('Forbidden');view('live-host',['game'=>$session,'me'=>$u]);exit;
    }
    if($action==='live-host-api' && $id){
        require_role('teacher','admin');ensure_live_tables();header('Content-Type: application/json; charset=utf-8');$s=db()->prepare('SELECT s.*,q.title,q.course_id FROM live_sessions s JOIN live_quizzes q ON q.id=s.quiz_id WHERE s.id=?');$s->execute([$id]);$session=$s->fetch();if(!$session||!can_manage_course((int)$session['course_id'],$u)){http_response_code(403);echo json_encode(['error'=>'forbidden']);exit;}
        $qs=db()->prepare('SELECT * FROM live_quiz_questions WHERE quiz_id=? ORDER BY sort_order,id');$qs->execute([(int)$session['quiz_id']]);$questions=$qs->fetchAll();
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$act=$_POST['action']??'';if($act==='start'&&$session['status']==='lobby'&&$questions)db()->prepare("UPDATE live_sessions SET status='running',current_question=0,question_started_at=CURRENT_TIMESTAMP(6) WHERE id=?")->execute([$id]);elseif($act==='next'&&$session['status']==='running'){$next=(int)$session['current_question']+1;if(isset($questions[$next]))db()->prepare("UPDATE live_sessions SET current_question=?,question_started_at=CURRENT_TIMESTAMP(6) WHERE id=?")->execute([$next,$id]);else db()->prepare("UPDATE live_sessions SET status='finished',question_started_at=NULL WHERE id=?")->execute([$id]);}elseif($act==='finish')db()->prepare("UPDATE live_sessions SET status='finished',question_started_at=NULL WHERE id=?")->execute([$id]);$s->execute([$id]);$session=$s->fetch();}
        $players=db()->prepare('SELECT id,nickname,avatar,score FROM live_session_players WHERE session_id=? ORDER BY score DESC,joined_at');$players->execute([$id]);$question=null;$current=(int)$session['current_question'];if($session['status']==='running'&&isset($questions[$current])){$question=$questions[$current];$aa=db()->prepare('SELECT id,answer_text,is_correct FROM live_quiz_answers WHERE question_id=? ORDER BY sort_order,id');$aa->execute([$question['id']]);$question['answers']=$aa->fetchAll();$question['number']=$current+1;$question['remaining']=(int)db()->query("SELECT GREATEST(0, {$question['time_limit']} - FLOOR(TIMESTAMPDIFF(MICROSECOND, '{$session['question_started_at']}', CURRENT_TIMESTAMP(6))/1000000))")->fetchColumn();}
        echo json_encode(['status'=>$session['status'],'players'=>$players->fetchAll(),'question'=>$question,'total'=>count($questions)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }

    /* ---------- Donatello Premium ---------- */
    if ($action==='subscribe') {
        $u=require_login(); ensure_premium_columns();
        // Reconcile immediately on every visit, so a completed donation activates Premium even
        // when the user closed the payment tab before returning to QuizSpace.
        donatello_sync_user((int)$u['id']);
        $fresh=db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $fresh->execute([(int)$u['id']]);
        $u=$fresh->fetch() ?: $u;

        if ($_SERVER['REQUEST_METHOD']==='POST') {
            check_csrf();
            if (($u['role'] ?? '') !== 'teacher') { http_response_code(403); exit('Premium доступний лише вчителям.'); }
            if (!premium_enabled()) { flash('Premium тимчасово вимкнено адміністратором.'); redirect('subscribe'); }
            if (premium_active($u)) { flash('Premium уже активний до '.date('d.m.Y H:i', strtotime((string)$u['subscription_expires_at']))); redirect('subscribe'); }
            try {
                // Не создаём новый код, если у пользователя уже есть ожидающий платёж.
                $existingPending = db()->prepare("SELECT id FROM donatello_payments WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
                $existingPending->execute([(int)$u['id']]);
                if (!$existingPending->fetchColumn()) {
                    donatello_new_payment((int)$u['id']);
                }
                // POST -> Redirect -> GET: обновление страницы больше не повторяет POST.
                redirect('subscribe');
            } catch (Throwable $e) {
                flash('Не вдалося створити платіж: '.$e->getMessage());
                redirect('subscribe');
            }
        }

        $pending=null;
        $q=db()->prepare("SELECT * FROM donatello_payments WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
        $q->execute([(int)$u['id']]);
        $pending=$q->fetch() ?: null;
        view('teacher/subscribe',['me'=>$u,'premium_active'=>premium_active($u),'donatello'=>donatello_config(),'pending_payment'=>$pending]); exit;
    }

    /* ---------- Unified attempt save/result for every interactive mode ---------- */
    if($action==='attempt-save' && $id && $_SERVER['REQUEST_METHOD']==='POST'){
        $u=require_login();
        check_csrf();
        $mode=trim((string)($_POST['mode']??'quiz'));
        $raw=$_POST['answers']??'[]';
        $answers=json_decode((string)$raw,true);
        if(!is_array($answers))$answers=[];

        $courseId=0;$title='';$total=0;$correctMap=[];$promptMap=[];$answerKind='text';

        if(in_array($mode,['quiz','true_false'],true)){
            $s=db()->prepare("SELECT id,course_id,title FROM quizzes WHERE id=?");$s->execute([$id]);$material=$s->fetch();
            if(!$material)exit('Квиз не найден');
            $courseId=(int)$material['course_id'];$title=$material['title'];
            $s=db()->prepare("SELECT q.id,q.question_text,qa.answer_text,qa.is_correct FROM questions q JOIN quiz_answers qa ON qa.question_id=q.id WHERE q.quiz_id=? ORDER BY q.sort_order,q.id,qa.id");$s->execute([$id]);
            foreach($s->fetchAll() as $r){$qid=(int)$r['id'];$promptMap[$qid]=$r['question_text'];if(!isset($correctMap[$qid]))$correctMap[$qid]=[];if((int)$r['is_correct'])$correctMap[$qid]=$r['answer_text'];}
            $total=count($correctMap);
        }elseif($mode==='qa'){
            $s=db()->prepare("SELECT id,course_id,title FROM quizzes WHERE id=? AND quiz_type='qa'");$s->execute([$id]);$material=$s->fetch();if(!$material)exit('Квиз не найден');
            $courseId=(int)$material['course_id'];$title=$material['title'];
            $s=db()->prepare("SELECT id,question_text,correct_answer FROM bank_questions WHERE quiz_id=? ORDER BY id");$s->execute([$id]);
            foreach($s->fetchAll() as $r){$qid=(int)$r['id'];$promptMap[$qid]=$r['question_text'];$correctMap[$qid]=$r['correct_answer'];}
            $total=count($correctMap);
        }elseif($mode==='fill'){
            $s=db()->prepare("SELECT i.id,i.quiz_id,i.sentence_template,i.correct_answer,q.course_id,q.title FROM fill_blank_items i JOIN quizzes q ON q.id=i.quiz_id WHERE i.quiz_id=? ORDER BY i.sort_order,i.id");$s->execute([$id]);$rows=$s->fetchAll();if(!$rows)exit('Квиз не найден');
            $courseId=(int)$rows[0]['course_id'];$title=$rows[0]['title'];
            foreach($rows as $r){$qid=(int)$r['id'];$promptMap[$qid]=$r['sentence_template'];$correctMap[$qid]=$r['correct_answer'];}
            $total=count($correctMap);
        }elseif($mode==='sentence'){
            $s=db()->prepare("SELECT i.id,i.quiz_id,i.prompt,i.correct_sentence,q.course_id,q.title FROM sentence_builder_items i JOIN quizzes q ON q.id=i.quiz_id WHERE i.quiz_id=? ORDER BY i.sort_order,i.id");$s->execute([$id]);$rows=$s->fetchAll();if(!$rows)exit('Квиз не найден');
            $courseId=(int)$rows[0]['course_id'];$title=$rows[0]['title'];
            foreach($rows as $r){$qid=(int)$r['id'];$promptMap[$qid]=$r['prompt'];$correctMap[$qid]=$r['correct_sentence'];}
            $total=count($correctMap);
        }elseif($mode==='matching'){
            $s=db()->prepare("SELECT id,course_id,title FROM matching_sets WHERE id=?");$s->execute([$id]);$material=$s->fetch();if(!$material)exit('Вправа не знайдена');
            $courseId=(int)$material['course_id'];$title=$material['title'];
            $s=db()->prepare("SELECT id,prompt,answer FROM matching_pairs WHERE set_id=? ORDER BY id");$s->execute([$id]);
            foreach($s->fetchAll() as $r){$qid=(int)$r['id'];$promptMap[$qid]=$r['prompt'];$correctMap[$qid]=$r['answer'];}
            $total=count($correctMap);
        }elseif($mode==='flashcards'){
            $s=db()->prepare("SELECT id,course_id,title FROM quizzes WHERE id=? AND quiz_type='flashcards'");$s->execute([$id]);$material=$s->fetch();if(!$material)exit('Квиз не найден');
            $courseId=(int)$material['course_id'];$title=$material['title'];
            $s=db()->prepare("SELECT id,front_text,back_text FROM flashcards WHERE quiz_id=? ORDER BY id");$s->execute([$id]);
            foreach($s->fetchAll() as $r){$qid=(int)$r['id'];$promptMap[$qid]=$r['front_text'];$correctMap[$qid]=$r['back_text'];}
            $total=count($correctMap);$answerKind='view';
        }elseif($mode==='memory'){
            $s=db()->prepare("SELECT id,course_id,title FROM memory_sets WHERE id=?");$s->execute([$id]);$material=$s->fetch();if(!$material)exit('Набір не знайдено');
            $courseId=(int)$material['course_id'];$title=$material['title'];
            $s=db()->prepare("SELECT id,card_number,text_content FROM memory_cards WHERE set_id=? ORDER BY card_number");$s->execute([$id]);
            foreach($s->fetchAll() as $r){$qid=(int)$r['id'];$promptMap[$qid]='Карточка #'.$r['card_number'];$correctMap[$qid]=$r['text_content'];}
            $total=count($correctMap);$answerKind='view';
        }else{exit('Неизвестный режим');}

        if(!$courseId || !can_access_course($courseId,$u))exit('Forbidden');

        $attemptType=$mode;
        $st=db()->prepare('INSERT INTO attempts(student_id,course_id,type,material_id,total) VALUES(?,?,?,?,?)');
        $st->execute([$u['id'],$courseId,$attemptType,$id,$total]);$aid=(int)db()->lastInsertId();
        $score=0;
        $ins=$answerKind==='view'
            ? db()->prepare('INSERT INTO result_answers(attempt_id,prompt,given_answer,correct_answer,is_correct) VALUES(?,?,?,?,?)')
            : db()->prepare('INSERT INTO result_answers(attempt_id,prompt,given_answer,correct_answer,is_correct) VALUES(?,?,?,?,?)');

        foreach($correctMap as $qid=>$correct){
            $given='';
            foreach($answers as $a){
                if((int)($a['id']??0)===$qid || (int)($a['question_id']??0)===$qid || (int)($a['item_id']??0)===$qid || (int)($a['pair_id']??0)===$qid || (int)($a['card_id']??0)===$qid){$given=(string)($a['answer']??$a['value']??'');break;}
            }
            if($mode==='sentence'){
                $ok=strtolower(trim(preg_replace('/\s+/',' ',$given)))===strtolower(trim(preg_replace('/\s+/',' ',$correct)));
            }elseif($mode==='matching'){
                $ok=strtolower(trim($given))===strtolower(trim($correct));
            }elseif($answerKind==='view'){
                $ok=(int)$given===1;
            }else{
                $ok=strtolower(trim($given))===strtolower(trim((string)$correct));
            }
            if($ok)$score++;
            $ins->execute([$aid,$promptMap[$qid]??'', $answerKind==='view' ? ($given==='1'?'Просмотрено':'Не просмотрено') : $given, $correct, $ok?1:0]);
        }
        db()->prepare('UPDATE attempts SET score=? WHERE id=?')->execute([$score,$aid]);
        redirect('attempt-result/'.$aid);
    }

    if($action==='attempt-result' && $id){
        $s=db()->prepare("SELECT a.*,COALESCE(q.title,m.title,ms.title,mem.title,'Результат') material_title
            FROM attempts a
            LEFT JOIN quizzes q ON q.id=a.material_id AND a.type IN ('quiz','true_false','qa','fill','sentence','flashcards')
            LEFT JOIN matching_sets m ON m.id=a.material_id AND a.type='matching'
            LEFT JOIN memory_sets ms ON ms.id=a.material_id AND a.type='memory'
            LEFT JOIN roulette_sets mem ON mem.id=a.material_id AND a.type='roulette'
            WHERE a.id=? AND a.student_id=?");$s->execute([$id,$u['id']]);$attempt=$s->fetch();
        if(!$attempt)exit('Результат не найден');
        $s=db()->prepare('SELECT * FROM result_answers WHERE attempt_id=? ORDER BY id');$s->execute([$id]);$results=$s->fetchAll();
        view('quiz/result',['attempt'=>$attempt,'results'=>$results,'me'=>$u]);exit;
    }


    if ($action==='home') {
        if($u['role']==='admin') redirect('admin/teachers');
        if($u['role']==='teacher') redirect('teacher/courses');
        $s=db()->prepare("SELECT c.*,u.name teacher_name FROM courses c JOIN course_members cm ON cm.course_id=c.id JOIN users u ON u.id=c.teacher_id WHERE cm.student_id=? AND cm.status='active' ORDER BY c.id DESC");
        $s->execute([$u['id']]); view('student/home',['courses'=>$s->fetchAll(),'me'=>$u]); exit;
    }

    if ($action==='admin' && ($parts[1]??'')==='premium') {
        require_role('admin'); ensure_premium_columns();
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            check_csrf();
            if (($_POST['task'] ?? '') === 'sync_payments') {
                try {
                    $count = donatello_sync_pending_payments(null, 5);
                    flash("Синхронизация Donatello завершена. Активировано подписок: {$count}.");
                } catch (Throwable $e) {
                    flash('Не удалось синхронизировать платежи Donatello: '.$e->getMessage());
                }
                redirect('admin/premium');
            }
            $price=max(0.01,min(1000000,(float)str_replace(',','.',(string)($_POST['price']??50))));
            $days=max(1,min(3650,(int)($_POST['duration_days']??30)));
            $currency=strtoupper(trim((string)($_POST['currency']??'UAH')));
            $enabled=isset($_POST['enabled'])?'1':'0';
            if(!preg_match('/^[A-Z]{3}$/',$currency)) $currency='UAH';
            set_app_setting('premium_price_uah',number_format($price,2,'.',''));
            set_app_setting('premium_duration_days',(string)$days);
            set_app_setting('premium_currency',$currency);
            set_app_setting('premium_enabled',$enabled);
            flash('Настройки Premium сохранены.'); redirect('admin/premium');
        }
        $payments=db()->query("SELECT p.id,p.user_id,p.match_code,p.expected_amount,p.currency,p.status,p.donor_name,p.paid_amount,p.created_at,p.matched_at,u.name teacher_name,u.email teacher_email FROM donatello_payments p LEFT JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 100")->fetchAll();
        view('admin/premium',['me'=>$u,'settings'=>['price'=>premium_price(),'duration_days'=>premium_duration_days(),'currency'=>premium_currency(),'enabled'=>premium_enabled()],'payments'=>$payments,'donatello_ready'=>donatello_ready()]); exit;
    }

    if ($action==='admin' && ($parts[1]??'')==='teachers') {
        require_role('admin');
        $teachers=db()->query("SELECT u.id,u.name,u.email,u.created_at,COUNT(c.id) course_count FROM users u LEFT JOIN courses c ON c.teacher_id=u.id WHERE u.role='teacher' GROUP BY u.id ORDER BY u.name")->fetchAll();
        view('admin/teachers',['teachers'=>$teachers,'me'=>$u]); exit;
    }
    if ($action==='admin' && ($parts[1]??'')==='teacher' && $nestedId) {
        require_role('admin');
        $id=$nestedId;
        $s=db()->prepare("SELECT id,name,email FROM users WHERE id=? AND role='teacher'"); $s->execute([$id]); $teacher=$s->fetch(); if(!$teacher) exit('Учитель не найден');
        $s=db()->prepare("SELECT c.*,
(SELECT COUNT(*) FROM quizzes q WHERE q.course_id=c.id AND COALESCE(q.quiz_type,'normal')='normal') normal_quiz_count,
(SELECT COUNT(*) FROM questions qq JOIN quizzes q2 ON q2.id=qq.quiz_id WHERE q2.course_id=c.id) question_count,
(SELECT COUNT(*) FROM quizzes qqa WHERE qqa.course_id=c.id AND qqa.quiz_type='qa') qa_quiz_count,
(SELECT COUNT(*) FROM flashcards f WHERE f.course_id=c.id AND f.quiz_id IS NULL) legacy_card_count,
(SELECT COUNT(*) FROM quizzes qg WHERE qg.course_id=c.id AND qg.quiz_type IN ('sentence','fill','flashcards','true_false','qa')) interactive_quiz_count,
(SELECT COUNT(*) FROM matching_sets m WHERE m.course_id=c.id) matching_count
FROM courses c WHERE c.teacher_id=? ORDER BY c.id DESC"); $s->execute([$id]);
        view('admin/teacher',['teacher'=>$teacher,'courses'=>$s->fetchAll(),'me'=>$u]); exit;
    }

    if ($action==='teacher' && ($parts[1]??'')==='courses') {
        require_role('teacher','admin');
        if($u['role']==='admin') $courses=db()->query("SELECT c.*,u.name teacher_name,(SELECT COUNT(*) FROM course_members cm WHERE cm.course_id=c.id AND cm.status='active') students,
(SELECT COUNT(*) FROM quizzes q WHERE q.course_id=c.id AND COALESCE(q.quiz_type,'normal')='normal') normal_quiz_count,
(SELECT COUNT(*) FROM questions qq JOIN quizzes q2 ON q2.id=qq.quiz_id WHERE q2.course_id=c.id) question_count,
(SELECT COUNT(*) FROM quizzes qqa WHERE qqa.course_id=c.id AND qqa.quiz_type='qa') qa_quiz_count,
(SELECT COUNT(*) FROM quizzes qg WHERE qg.course_id=c.id AND qg.quiz_type IN ('sentence','fill','flashcards','true_false','qa')) interactive_quiz_count,
(SELECT COUNT(*) FROM matching_sets m WHERE m.course_id=c.id) matching_count
FROM courses c JOIN users u ON u.id=c.teacher_id ORDER BY c.id DESC")->fetchAll();
        else { $s=db()->prepare("SELECT c.*,u.name teacher_name,(SELECT COUNT(*) FROM course_members cm WHERE cm.course_id=c.id AND cm.status='active') students,
(SELECT COUNT(*) FROM quizzes q WHERE q.course_id=c.id AND COALESCE(q.quiz_type,'normal')='normal') normal_quiz_count,
(SELECT COUNT(*) FROM questions qq JOIN quizzes q2 ON q2.id=qq.quiz_id WHERE q2.course_id=c.id) question_count,
(SELECT COUNT(*) FROM quizzes qqa WHERE qqa.course_id=c.id AND qqa.quiz_type='qa') qa_quiz_count,
(SELECT COUNT(*) FROM quizzes qg WHERE qg.course_id=c.id AND qg.quiz_type IN ('sentence','fill','flashcards','true_false','qa')) interactive_quiz_count,
(SELECT COUNT(*) FROM matching_sets m WHERE m.course_id=c.id) matching_count
FROM courses c JOIN users u ON u.id=c.teacher_id WHERE c.teacher_id=? ORDER BY c.id DESC");$s->execute([$u['id']]);$courses=$s->fetchAll(); }
        view('teacher/courses',['courses'=>$courses,'me'=>$u]); exit;
    }

    if ($action==='course' && $id) {
        if(!can_access_course($id,$u)){http_response_code(403);exit('Нет доступа к курсу');}
        $s=db()->prepare('SELECT c.*,u.name teacher_name,u.email teacher_email FROM courses c JOIN users u ON u.id=c.teacher_id WHERE c.id=?');$s->execute([$id]);$course=$s->fetch();if(!$course)exit('Курс не найден');

        $s=db()->prepare("SELECT q.*,COUNT(qq.id) question_count FROM quizzes q LEFT JOIN questions qq ON qq.quiz_id=q.id WHERE q.course_id=? AND COALESCE(q.quiz_type,'normal')='normal' GROUP BY q.id ORDER BY q.id DESC");$s->execute([$id]);$quizzes=$s->fetchAll();
        $s=db()->prepare("SELECT q.id,q.title,q.quiz_type,
(SELECT COUNT(*) FROM sentence_builder_items s WHERE s.quiz_id=q.id) sentence_count,
(SELECT COUNT(*) FROM fill_blank_items f WHERE f.quiz_id=q.id) fill_count,
(SELECT COUNT(*) FROM flashcards fc WHERE fc.quiz_id=q.id) card_count,
(SELECT COUNT(*) FROM bank_questions bq WHERE bq.quiz_id=q.id) qa_count,
(SELECT COUNT(*) FROM questions qq WHERE qq.quiz_id=q.id) question_count
FROM quizzes q WHERE q.course_id=? AND q.quiz_type IN ('sentence','fill','flashcards','qa','true_false') ORDER BY q.id DESC");$s->execute([$id]);$gameQuizzes=$s->fetchAll();
        $s=db()->prepare('SELECT * FROM bank_questions WHERE course_id=? ORDER BY id DESC');$s->execute([$id]);$bank=$s->fetchAll();
        $s=db()->prepare('SELECT * FROM flashcards WHERE course_id=? ORDER BY id DESC');$s->execute([$id]);$cards=$s->fetchAll();
        $s=db()->prepare('SELECT ms.*,COUNT(mp.id) pair_count FROM matching_sets ms LEFT JOIN matching_pairs mp ON mp.set_id=ms.id WHERE ms.course_id=? GROUP BY ms.id ORDER BY ms.id DESC');$s->execute([$id]);$matching=$s->fetchAll();
        $s=db()->prepare('SELECT rs.*,COUNT(rg.id) segment_count FROM roulette_sets rs LEFT JOIN roulette_segments rg ON rg.roulette_id=rs.id WHERE rs.course_id=? GROUP BY rs.id ORDER BY rs.id DESC');$s->execute([$id]);$roulettes=$s->fetchAll();
        $s=db()->prepare('SELECT ms.*,COUNT(mc.id) card_count FROM memory_sets ms LEFT JOIN memory_cards mc ON mc.set_id=ms.id WHERE ms.course_id=? GROUP BY ms.id ORDER BY ms.id DESC');$s->execute([$id]);$memorySets=$s->fetchAll();
        $s=db()->prepare("SELECT a.*,
            CASE
              WHEN a.content_type='quiz' THEN (SELECT q.title FROM quizzes q WHERE q.id=a.content_id)
              WHEN a.content_type='memory' THEN (SELECT m.title FROM memory_sets m WHERE m.id=a.content_id)
              WHEN a.content_type='matching' THEN (SELECT m.title FROM matching_sets m WHERE m.id=a.content_id)
              WHEN a.content_type='roulette' THEN (SELECT r.title FROM roulette_sets r WHERE r.id=a.content_id)
              WHEN a.content_type='bank' THEN 'Банк Питання → Відповідь'
              WHEN a.content_type='flashcards' THEN 'Навчальні картки'
              ELSE a.content_type END AS content_title,
            (SELECT COUNT(*) FROM assignment_students ast WHERE ast.assignment_id=a.id) student_count
            FROM assignments a WHERE a.course_id=? ORDER BY a.id DESC");
        $s->execute([$id]);$assignments=$s->fetchAll();
        $liveGames=[];
        if($u['role']!=='student'){
            ensure_live_tables();
            $s=db()->prepare('SELECT id,title,created_at FROM live_quizzes WHERE course_id=? ORDER BY id DESC LIMIT 50');
            $s->execute([$id]);$liveGames=$s->fetchAll();
        }

        view('course/show',['course'=>$course,'quizzes'=>$quizzes,'gameQuizzes'=>$gameQuizzes,'bank'=>$bank,'cards'=>$cards,'matching'=>$matching,'roulettes'=>$roulettes,'memorySets'=>$memorySets,'assignments'=>$assignments,'liveGames'=>$liveGames,'me'=>$u]);exit;
    }

    if ($action==='course-create') {
        require_role('teacher');
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$title=trim($_POST['title']??'');if(!$title)flash('Введите название курса.');else{db()->prepare('INSERT INTO courses(teacher_id,title,description) VALUES(?,?,?)')->execute([$u['id'],$title,trim($_POST['description']??'')]);flash('Курс создан.');redirect('teacher/courses');}}
        view('course/form',['course'=>null,'me'=>$u]); exit;
    }
    if ($action==='course-edit' && $id) {
        require_role('teacher','admin');if(!can_manage_course($id,$u))exit('Forbidden');
        $s=db()->prepare('SELECT * FROM courses WHERE id=?');$s->execute([$id]);$course=$s->fetch();if(!$course)exit('Курс не найден');
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();db()->prepare('UPDATE courses SET title=?,description=? WHERE id=?')->execute([trim($_POST['title']),trim($_POST['description']),$id]);flash('Курс обновлён.');redirect('course/'.$id);}
        view('course/form',['course'=>$course,'me'=>$u]); exit;
    }

    if ($action==='delete' && $id) {
        require_role('teacher','admin');check_csrf();$type=$sub;$courseId=null;
        if($type==='assignment'){
            $q=db()->prepare('SELECT course_id FROM assignments WHERE id=?');$q->execute([$id]);$courseId=$q->fetchColumn();if(!$courseId||!can_manage_course((int)$courseId,$u))exit('Forbidden');
            db()->prepare('DELETE FROM assignment_students WHERE assignment_id=?')->execute([$id]);db()->prepare('DELETE FROM assignments WHERE id=?')->execute([$id]);flash('Назначение удалено.');redirect('course/'.$courseId);
        }
        if($type==='memory'){
            $q=db()->prepare('SELECT course_id FROM memory_sets WHERE id=?');$q->execute([$id]);$courseId=$q->fetchColumn();if(!$courseId||!can_manage_course((int)$courseId,$u))exit('Forbidden');
            $q=db()->prepare('SELECT image_path,audio_url FROM memory_cards WHERE set_id=?');$q->execute([$id]);foreach($q->fetchAll() as $r){delete_file($r['image_path']??null);delete_file($r['audio_url']??null);}
            db()->prepare('DELETE FROM memory_sets WHERE id=?')->execute([$id]);flash('Набор карточек удалён.');redirect('course/'.$courseId);
        }
        if($type==='gamequiz'){
            $q=db()->prepare("SELECT course_id,quiz_type FROM quizzes WHERE id=? AND quiz_type IN ('sentence','fill','flashcards','qa','true_false')");$q->execute([$id]);$g=$q->fetch();if(!$g||!can_manage_course((int)$g['course_id'],$u))exit('Forbidden');$courseId=(int)$g['course_id'];
            if($g['quiz_type']==='sentence'){ $q=db()->prepare('SELECT audio_url FROM sentence_builder_items WHERE quiz_id=?');$q->execute([$id]);foreach($q->fetchAll() as $r)delete_file($r['audio_url']??null);db()->prepare('DELETE FROM sentence_builder_words WHERE item_id IN (SELECT id FROM sentence_builder_items WHERE quiz_id=?)')->execute([$id]);db()->prepare('DELETE FROM sentence_builder_items WHERE quiz_id=?')->execute([$id]);}
            elseif($g['quiz_type']==='fill'){ $q=db()->prepare('SELECT audio_url FROM fill_blank_items WHERE quiz_id=?');$q->execute([$id]);foreach($q->fetchAll() as $r)delete_file($r['audio_url']??null);db()->prepare('DELETE FROM fill_blank_options WHERE item_id IN (SELECT id FROM fill_blank_items WHERE quiz_id=?)')->execute([$id]);db()->prepare('DELETE FROM fill_blank_items WHERE quiz_id=?')->execute([$id]);}
            elseif($g['quiz_type']==='flashcards'){ $q=db()->prepare('SELECT front_image_path,back_image_path,front_audio_path,back_audio_path FROM flashcards WHERE quiz_id=?');$q->execute([$id]);foreach($q->fetchAll() as $r){delete_file($r['front_image_path']??null);delete_file($r['back_image_path']??null);delete_file($r['front_audio_path']??null);delete_file($r['back_audio_path']??null);}db()->prepare('DELETE FROM flashcards WHERE quiz_id=?')->execute([$id]);}
            elseif($g['quiz_type']==='qa'){db()->prepare('DELETE FROM bank_questions WHERE quiz_id=?')->execute([$id]);}
            elseif($g['quiz_type']==='true_false'){db()->prepare('DELETE FROM quiz_answers WHERE question_id IN (SELECT id FROM questions WHERE quiz_id=?)')->execute([$id]);db()->prepare('DELETE FROM questions WHERE quiz_id=?')->execute([$id]);}
            db()->prepare('DELETE FROM quizzes WHERE id=?')->execute([$id]);flash('Квиз удалён.');redirect('course/'.$courseId);
        }

        $map=['course'=>['courses','id'],'quiz'=>['quizzes','id'],'question'=>['bank_questions','id'],'flashcard'=>['flashcards','id'],'matching'=>['matching_sets','id'],'matching-pair'=>['matching_pairs','id'],'roulette'=>['roulette_sets','id']];
        if(!isset($map[$type]))exit('Неизвестный тип удаления');[$table,$pk]=$map[$type];

        if($type==='course'){$q=db()->prepare('SELECT id FROM courses WHERE id=?');$q->execute([$id]);$courseId=$q->fetchColumn();$ok=$courseId&&can_manage_course($id,$u);}
        else{
            $qm=['quiz'=>'SELECT c.teacher_id,c.id course_id FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.id=?','question'=>'SELECT c.teacher_id,c.id course_id FROM bank_questions b JOIN courses c ON c.id=b.course_id WHERE b.id=?','flashcard'=>'SELECT c.teacher_id,c.id course_id FROM flashcards f JOIN courses c ON c.id=f.course_id WHERE f.id=?','matching'=>'SELECT c.teacher_id,c.id course_id FROM matching_sets m JOIN courses c ON c.id=m.course_id WHERE m.id=?','matching-pair'=>'SELECT c.teacher_id,c.id course_id FROM matching_pairs p JOIN matching_sets m ON m.id=p.set_id JOIN courses c ON c.id=m.course_id WHERE p.id=?','roulette'=>'SELECT c.teacher_id,c.id course_id FROM roulette_sets r JOIN courses c ON c.id=r.course_id WHERE r.id=?'];
            $q=db()->prepare($qm[$type]);$q->execute([$id]);$r=$q->fetch();$courseId=$r['course_id']??null;$ok=$r&&($u['role']==='admin'||(int)$r['teacher_id']===(int)$u['id']);
        }
        if(!$ok)exit('Forbidden');
        if($type==='matching'){$q=db()->prepare('SELECT prompt_image_path,answer_image_path FROM matching_pairs WHERE set_id=?');$q->execute([$id]);foreach($q->fetchAll() as $r)foreach($r as $v)delete_file($v);}
        if($type==='roulette'){$q=db()->prepare('SELECT image_path FROM roulette_segments WHERE roulette_id=?');$q->execute([$id]);foreach($q->fetchAll() as $r)delete_file($r['image_path']);}
        if($type==='quiz'){$q=db()->prepare('SELECT image_path FROM questions WHERE quiz_id=?');$q->execute([$id]);foreach($q->fetchAll() as $r)delete_file($r['image_path']);}
        db()->prepare("DELETE FROM {$table} WHERE {$pk}=?")->execute([$id]);flash('Удалено.');if($type==='course'){redirect('teacher/courses');}redirect('course/'.(int)$courseId);
    }


    if($action==='truefalse-create'){
        require_role('teacher','admin');$courseId=$id;
        if(!can_manage_course($courseId,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();
            $title=trim($_POST['title']??'');
            $statements=$_POST['statement']??[];
            $answers=$_POST['correct']??[];
            if(!is_array($statements))$statements=[$statements];
            if(!is_array($answers))$answers=[$answers];
            if($title===''){flash('Введите название квиза.');redirect('truefalse-create/'.$courseId);}
            $valid=[];
            foreach($statements as $i=>$statement){
                $statement=trim((string)$statement);
                if($statement==='') continue;
                $correct=(($answers[$i]??'true')==='false')?'false':'true';
                $valid[]=[$statement,$correct];
            }
            if(!$valid){flash('Добавьте хотя бы одно утверждение.');redirect('truefalse-create/'.$courseId);}
            $builderId=(int)($_POST['builder_id']??0);
            db()->beginTransaction();
            try{
                if($builderId){
                    $q0=db()->prepare("SELECT id,course_id FROM quizzes WHERE id=? AND quiz_type='true_false'");$q0->execute([$builderId]);$existingQ=$q0->fetch();
                    if(!$existingQ) throw new RuntimeException('Квиз не найден.');
                    $courseId=(int)$existingQ['course_id'];
                    if(!can_manage_course($courseId,$u)) throw new RuntimeException('Forbidden');
                    db()->prepare('UPDATE quizzes SET title=? WHERE id=?')->execute([$title,$builderId]);
                    db()->prepare('DELETE qa FROM quiz_answers qa JOIN questions q ON q.id=qa.question_id WHERE q.quiz_id=?')->execute([$builderId]);
                    db()->prepare('DELETE FROM questions WHERE quiz_id=?')->execute([$builderId]);
                    $qid=$builderId;
                } else {
                    require_quiz_type_capacity($u,$courseId,'true_false');
                    db()->prepare("INSERT INTO quizzes(course_id,title,description,quiz_type) VALUES(?,?,?,'true_false')")->execute([$courseId,$title,'True / False']);
                    $qid=(int)db()->lastInsertId();
                }
                foreach($valid as $i=>$row){
                    db()->prepare('INSERT INTO questions(quiz_id,question_text,sort_order) VALUES(?,?,?)')->execute([$qid,$row[0],$i]);
                    $questionId=(int)db()->lastInsertId();
                    $correct=$row[1]==='true'?1:0;
                    db()->prepare('INSERT INTO quiz_answers(question_id,answer_text,is_correct) VALUES(?,?,?)')->execute([$questionId,'True',$correct]);
                    db()->prepare('INSERT INTO quiz_answers(question_id,answer_text,is_correct) VALUES(?,?,?)')->execute([$questionId,'False',1-$correct]);
                }
                db()->commit();
            }catch(Throwable $e){ if(db()->inTransaction())db()->rollBack(); throw $e; }
            flash('Квиз True / False создан.');
            redirect('course/'.$courseId);
        }
        view('special/truefalse-form',['courseId'=>$courseId,'me'=>$u]);exit;
    }

    /* ---------- Universal builder auto-create + autosave ---------- */
    if ($action==='builder-autosave') {
        require_role('teacher','admin');
        if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }
        check_csrf(); header('Content-Type: application/json; charset=utf-8');
        $mode=trim((string)($_POST['builder_mode']??''));
        $entityId=(int)($_POST['builder_id']??0);
        $courseId=(int)($_POST['course_id']??0);
        $title=trim((string)($_POST['title']??$_POST['quiz_title']??''));
        $now=date('H:i:s');
        $defaultTitles=['truefalse'=>'Новый True / False','fill'=>'Новая вставка слова','sentence'=>'Новый порядок слов','flashcards'=>'Новые карточки','memory'=>'Новый набор карточек','matching'=>'Новое сопоставление','roulette'=>'Новая рулетка','legacy-flashcards'=>'Новые карточки'];
        if($title==='') $title=$defaultTitles[$mode]??'Новый материал';
        try {
            $newUrl=null;
            if(in_array($mode,['truefalse','fill','sentence','flashcards'],true)) {
                if($entityId){$q=db()->prepare('SELECT * FROM quizzes WHERE id=?');$q->execute([$entityId]);$qz=$q->fetch();if(!$qz)throw new RuntimeException('Квиз не найден');$courseId=(int)$qz['course_id'];}
                if(!$courseId||!can_manage_course($courseId,$u))throw new RuntimeException('Forbidden');
                if(!$entityId){$type=['truefalse'=>'true_false','fill'=>'fill','sentence'=>'sentence','flashcards'=>'flashcards'][$mode];require_quiz_type_capacity($u,$courseId,$type);$desc='Автоматически созданный материал';db()->prepare('INSERT INTO quizzes(course_id,title,description,quiz_type) VALUES(?,?,?,?)')->execute([$courseId,$title,$desc,$type]);$entityId=(int)db()->lastInsertId();}
                else db()->prepare('UPDATE quizzes SET title=? WHERE id=?')->execute([$title,$entityId]);
                if($mode==='truefalse'){
                    $st=$_POST['statement']??[];$cor=$_POST['correct']??[];$keep=[];
                    if(!is_array($st))$st=[$st];if(!is_array($cor))$cor=[$cor];
                    foreach($st as $i=>$text){$text=trim((string)$text);if($text==='')continue;$qid=(int)($_POST['item_id'][$i]??0);$correct=strtolower((string)($cor[$i]??'true'))==='true'?1:0;
                        if($qid){db()->prepare('UPDATE questions SET question_text=?,sort_order=? WHERE id=? AND quiz_id=?')->execute([$text,$i,$qid,$entityId]);}
                        else{db()->prepare('INSERT INTO questions(quiz_id,question_text,sort_order) VALUES(?,?,?)')->execute([$entityId,$text,$i]);$qid=(int)db()->lastInsertId();}
                        db()->prepare('DELETE FROM quiz_answers WHERE question_id=?')->execute([$qid]);db()->prepare('INSERT INTO quiz_answers(question_id,answer_text,is_correct) VALUES(?,?,?)')->execute([$qid,'True',$correct]);db()->prepare('INSERT INTO quiz_answers(question_id,answer_text,is_correct) VALUES(?,?,?)')->execute([$qid,'False',1-$correct]);$keep[]=$qid;
                    }
                    if($keep){$marks=implode(',',array_fill(0,count($keep),'?'));db()->prepare("DELETE FROM questions WHERE quiz_id=? AND id NOT IN ($marks)")->execute(array_merge([$entityId],$keep));}
                    $newUrl=url('truefalse-create/'.$courseId);
                } elseif($mode==='fill') {
                    db()->prepare('DELETE FROM fill_blank_items WHERE quiz_id=?')->execute([$entityId]);
                    $items=$_POST['items']??null;
                    if(is_array($items)){
                        foreach($items as $i=>$it){$st=trim((string)($it['sentence_template']??''));$ca=trim((string)($it['correct_answer']??''));$am=in_array(($it['answer_mode']??'choice'),['choice','drag','type'],true)?$it['answer_mode']:'choice';$ops=$it['options']??[];if($st===''||$ca===''||!is_array($ops))continue;$ops=array_values(array_filter(array_map('trim',$ops),fn($x)=>$x!==''));if(!$ops)continue;db()->prepare('INSERT INTO fill_blank_items(quiz_id,sentence_template,correct_answer,answer_mode,sort_order) VALUES(?,?,?,?,?)')->execute([$entityId,$st,$ca,$am,$i]);$fid=(int)db()->lastInsertId();foreach($ops as $j=>$op)db()->prepare('INSERT INTO fill_blank_options(item_id,option_text,is_correct,sort_order) VALUES(?,?,?,?)')->execute([$fid,$op,strcasecmp($op,$ca)===0?1:0,$j]);}
                    } else { $templates=$_POST['sentence_template']??[];$corrects=$_POST['correct_answer']??[];$opts=$_POST['options']??[];$modes=$_POST['answer_mode']??[];foreach((array)$templates as $i=>$raw){$st=trim((string)$raw);$ca=trim((string)($corrects[$i]??''));$oo=$opts[$i]??[];$oo=is_array($oo)?array_values(array_filter(array_map('trim',$oo),fn($x)=>$x!=='')):[];$am=in_array(($modes[$i]??'choice'),['choice','drag','type'],true)?$modes[$i]:'choice';if($st===''||$ca===''||!$oo)continue;db()->prepare('INSERT INTO fill_blank_items(quiz_id,sentence_template,correct_answer,answer_mode,sort_order) VALUES(?,?,?,?,?)')->execute([$entityId,$st,$ca,$am,$i]);$fid=(int)db()->lastInsertId();foreach($oo as $j=>$op)db()->prepare('INSERT INTO fill_blank_options(item_id,option_text,is_correct,sort_order) VALUES(?,?,?,?)')->execute([$fid,$op,strcasecmp($op,$ca)===0?1:0,$j]);}}
                    $newUrl=url('fill-quiz-edit/'.$entityId);
                } elseif($mode==='sentence') {
                    db()->prepare('DELETE FROM sentence_builder_items WHERE quiz_id=?')->execute([$entityId]);
                    $prompts=$_POST['prompt']??[];$corrects=$_POST['correct_sentence']??[];foreach((array)$prompts as $i=>$pr){$pr=trim((string)$pr);$cs=trim((string)($corrects[$i]??''));if($pr===''||$cs==='')continue;db()->prepare('INSERT INTO sentence_builder_items(quiz_id,prompt,correct_sentence,sort_order) VALUES(?,?,?,?)')->execute([$entityId,$pr,$cs,$i]);$sid=(int)db()->lastInsertId();$wi=0;foreach(preg_split('/\s+/',trim($cs)) as $word)if($word!=='')db()->prepare('INSERT INTO sentence_builder_words(item_id,word_text,word_order) VALUES(?,?,?)')->execute([$sid,$word,$wi++]);}
                    $newUrl=url('sentence-quiz-edit/'.$entityId);
                } else {
                    db()->prepare('DELETE FROM flashcards WHERE quiz_id=?')->execute([$entityId]);$front=$_POST['front']??[];$back=$_POST['back']??[];$keys=$_POST['card_key']??[];$fa=$_POST['front_audio_url']??[];$ba=$_POST['back_audio_url']??[];$n=max(count((array)$front),count((array)$back));
                    for($i=0;$i<$n;$i++){ $k=(int)($keys[$i]??$i);$a=trim((string)($front[$i]??''));$b=trim((string)($back[$i]??''));$fi=upload_image('front_image_'.$k);$bi=upload_image('back_image_'.$k);$fau=upload_audio('front_audio_'.$k)?:trim((string)($fa[$i]??''));$bau=upload_audio('back_audio_'.$k)?:trim((string)($ba[$i]??''));if($a===''&&$b===''&&!$fi&&!$bi&&!$fau&&!$bau)continue;db()->prepare('INSERT INTO flashcards(course_id,quiz_id,front_text,back_text,front_image_path,back_image_path,front_audio_path,back_audio_path) VALUES(?,?,?,?,?,?,?,?)')->execute([$courseId,$entityId,$a,$b,$fi,$bi,$fau?:null,$bau?:null]);}
                    $newUrl=url('flashcard-quiz-edit/'.$entityId);
                }
            } elseif($mode==='matching') {
                if($entityId){$q=db()->prepare('SELECT * FROM matching_sets WHERE id=?');$q->execute([$entityId]);$set=$q->fetch();if(!$set)throw new RuntimeException('Вправа не знайдена');$courseId=(int)$set['course_id'];}else{if(!$courseId)throw new RuntimeException('Course missing');if(!can_manage_course($courseId,$u))throw new RuntimeException('Forbidden');require_quiz_type_capacity($u,$courseId,'matching');db()->prepare('INSERT INTO matching_sets(course_id,title,description) VALUES(?,?,?)')->execute([$courseId,$title,trim((string)($_POST['description']??''))]);$entityId=(int)db()->lastInsertId();}
                if(!can_manage_course($courseId,$u))throw new RuntimeException('Forbidden');db()->prepare('UPDATE matching_sets SET title=?,description=? WHERE id=?')->execute([$title,trim((string)($_POST['description']??'')),$entityId]);db()->prepare('DELETE FROM matching_pairs WHERE set_id=?')->execute([$entityId]);$prompts=$_POST['prompt']??[];$answers=$_POST['answer']??[];foreach((array)$prompts as $i=>$pr){$pr=trim((string)$pr);$an=trim((string)($answers[$i]??''));if($pr===''||$an==='')continue;$pi=upload_image('prompt_image_'.$i);$ai=upload_image('answer_image_'.$i);db()->prepare('INSERT INTO matching_pairs(set_id,prompt,answer,prompt_image_path,answer_image_path) VALUES(?,?,?,?,?)')->execute([$entityId,$pr,$an,$pi,$ai]);}$newUrl=url('matching-edit/'.$entityId);
            } elseif($mode==='roulette') {
                if($entityId){$q=db()->prepare('SELECT * FROM roulette_sets WHERE id=?');$q->execute([$entityId]);$r=$q->fetch();if(!$r)throw new RuntimeException('Рулетка не найдена');$courseId=(int)$r['course_id'];}else{if(!$courseId)throw new RuntimeException('Course missing');if(!can_manage_course($courseId,$u))throw new RuntimeException('Forbidden');db()->prepare('INSERT INTO roulette_sets(course_id,title,description) VALUES(?,?,?)')->execute([$courseId,$title,trim((string)($_POST['description']??''))]);$entityId=(int)db()->lastInsertId();}
                if(!can_manage_course($courseId,$u))throw new RuntimeException('Forbidden');db()->prepare('UPDATE roulette_sets SET title=?,description=? WHERE id=?')->execute([$title,trim((string)($_POST['description']??'')),$entityId]);db()->prepare('DELETE FROM roulette_segments WHERE roulette_id=?')->execute([$entityId]);foreach((array)($_POST['label']??[]) as $i=>$lab){$lab=trim((string)$lab);if($lab==='')continue;$img=upload_image('segment_image_'.$i);$w=max(.01,(float)($_POST['weight'][$i]??1));db()->prepare('INSERT INTO roulette_segments(roulette_id,label,image_path,weight,sort_order) VALUES(?,?,?,?,?)')->execute([$entityId,$lab,$img,$w,$i]);}$newUrl=url('roulette-edit/'.$entityId);
            } elseif($mode==='memory') {
                if($entityId){$q=db()->prepare('SELECT * FROM memory_sets WHERE id=?');$q->execute([$entityId]);$set=$q->fetch();if(!$set)throw new RuntimeException('Набор не найден');$courseId=(int)$set['course_id'];}else{if(!$courseId||!can_manage_course($courseId,$u))throw new RuntimeException('Forbidden');db()->prepare('INSERT INTO memory_sets(course_id,title,card_count,created_by) VALUES(?,?,?,?)')->execute([$courseId,$title,0,$u['id']]);$entityId=(int)db()->lastInsertId();}
                if(!can_manage_course($courseId,$u))throw new RuntimeException('Forbidden');db()->prepare('UPDATE memory_sets SET title=? WHERE id=?')->execute([$title,$entityId]);$slots=array_values(array_unique(array_filter(array_map('intval',$_POST['card_slot']??[]),fn($x)=>$x>0)));foreach($slots as $slot){$text=trim((string)($_POST['card_text'][$slot]??''));$aud=trim((string)($_POST['card_audio'][$slot]??''));$img=upload_image('card_image_'.$slot);$af=upload_audio('card_audio_file_'.$slot);$af=$af?:($aud?:null);$ex=db()->prepare('SELECT id,image_path,audio_url FROM memory_cards WHERE set_id=? AND card_number=?');$ex->execute([$entityId,$slot]);$old=$ex->fetch();if($old){$img=$img?:$old['image_path'];$af=$af?:$old['audio_url'];db()->prepare('UPDATE memory_cards SET text_content=?,image_path=?,audio_url=? WHERE id=?')->execute([$text,$img,$af,$old['id']]);}else db()->prepare('INSERT INTO memory_cards(set_id,card_number,text_content,image_path,audio_url) VALUES(?,?,?,?,?)')->execute([$entityId,$slot,$text,$img,$af]);}db()->prepare('UPDATE memory_sets SET card_count=? WHERE id=?')->execute([count($slots),$entityId]);$newUrl=url('memory-edit/'.$entityId);
            } elseif($mode==='legacy-flashcards') {
                if(!$courseId||!can_manage_course($courseId,$u))throw new RuntimeException('Forbidden');
                if($entityId){$q=db()->prepare('SELECT course_id FROM flashcards WHERE id=?');$q->execute([$entityId]);$courseId=(int)$q->fetchColumn();}
                require_quiz_type_capacity($u,$courseId,'legacy-flashcards');$front=$_POST['front']??[];$back=$_POST['back']??[];$first=null;foreach((array)$front as $i=>$f){$f=trim((string)$f);$b=trim((string)($back[$i]??''));if($f===''&&$b==='')continue;if(!$first){db()->prepare('INSERT INTO flashcards(course_id,front_text,back_text) VALUES(?,?,?)')->execute([$courseId,$f,$b]);$first=(int)db()->lastInsertId();}else db()->prepare('INSERT INTO flashcards(course_id,front_text,back_text) VALUES(?,?,?)')->execute([$courseId,$f,$b]);}
                $entityId=$first?:$entityId;$newUrl=url('flashcards-legacy/'.$courseId);
            } else throw new RuntimeException('Unknown builder mode');
            echo json_encode(['ok'=>true,'saved'=>true,'builder_id'=>$entityId,'course_id'=>$courseId,'redirect_url'=>$newUrl,'saved_at'=>$now],JSON_UNESCAPED_UNICODE);exit;
        } catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()==='Forbidden'?'Forbidden':'Не удалось автосохранить']);exit;}
    }

    /* ---------- Quiz autosave ---------- */
    if ($action==='quiz-autosave') {
        require_role('teacher','admin');
        if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }
        check_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $quizId=(int)($_POST['quiz_id']??0);
        $courseId=(int)($_POST['course_id']??0);
        $title=trim($_POST['title']??'');
        $description=trim($_POST['description']??'');

        if ($title==='') { echo json_encode(['ok'=>false,'saved'=>false,'error'=>'Название не заполнено']); exit; }

        if ($quizId) {
            $s=db()->prepare('SELECT * FROM quizzes WHERE id=?');
            $s->execute([$quizId]);
            $existing=$s->fetch();
            if (!$existing) { echo json_encode(['ok'=>false,'error'=>'Квиз не найден']); exit; }
            $courseId=(int)$existing['course_id'];
        } else {
            if (!$courseId || !can_manage_course($courseId,$u)) { echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
        }
        if (!can_manage_course($courseId,$u)) { echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }

        try {
            if (!$quizId) {
                require_quiz_type_capacity($u,$courseId,'normal');
                db()->prepare('INSERT INTO quizzes(course_id,title,description) VALUES(?,?,?)')->execute([$courseId,$title,$description]);
                $quizId=(int)db()->lastInsertId();
            } else {
                db()->prepare('UPDATE quizzes SET title=?,description=? WHERE id=?')->execute([$title,$description,$quizId]);
            }

            /*
             * Autosave is deliberately tolerant: an unfinished question is not
             * written until it has a question text, at least 2 answers and a
             * selected correct answer. This prevents the normal "minimum 2
             * answers" validation from interrupting autosave while typing.
             */
            $savedQuestions=0;
            $questionIds=$_POST['question_id']??[];
            $questionTexts=$_POST['question_text']??[];
            $answersAll=$_POST['answers']??[];
            $correctAll=$_POST['correct']??[];

            if (is_array($questionTexts)) {
                foreach ($questionTexts as $i=>$rawText) {
                    $qt=trim((string)$rawText);
                    $answers=is_array($answersAll[$i]??null)?$answersAll[$i]:[];
                    $correct=(string)($correctAll[$i]??'');
                    $validAnswers=[];
                    foreach ($answers as $ai=>$a) {
                        $a=trim((string)$a);
                        if ($a!=='') $validAnswers[(string)$ai]=$a;
                    }
                    if ($qt==='' || count($validAnswers)<2 || !array_key_exists($correct,$validAnswers)) continue;

                    $oldId=(int)($questionIds[$i]??0);
                    if ($oldId) {
                        $s=db()->prepare('SELECT id FROM questions WHERE id=? AND quiz_id=?');
                        $s->execute([$oldId,$quizId]);
                        $exists=(bool)$s->fetchColumn();
                    } else $exists=false;

                    if ($exists) {
                        $dbq=db();
                        $dbq->prepare('UPDATE questions SET question_text=?,sort_order=? WHERE id=? AND quiz_id=?')->execute([$qt,(int)$i,$oldId,$quizId]);
                        $questionId=$oldId;
                    } else {
                        db()->prepare('INSERT INTO questions(quiz_id,question_text,sort_order) VALUES(?,?,?)')->execute([$quizId,$qt,(int)$i]);
                        $questionId=(int)db()->lastInsertId();
                    }

                    db()->prepare('DELETE FROM quiz_answers WHERE question_id=?')->execute([$questionId]);
                    foreach ($validAnswers as $ai=>$a) {
                        db()->prepare('INSERT INTO quiz_answers(question_id,answer_text,is_correct) VALUES(?,?,?)')
                            ->execute([$questionId,$a,((string)$ai===$correct)?1:0]);
                    }
                    $savedQuestions++;
                }
            }

            echo json_encode([
                'ok'=>true,
                'saved'=>true,
                'quiz_id'=>$quizId,
                'course_id'=>$courseId,
                'saved_questions'=>$savedQuestions,
                'saved_at'=>date('H:i:s')
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'Не удалось выполнить автосохранение']);
        }
        exit;
    }

    if ($action==='quiz-create' || $action==='quiz-edit') {
        require_role('teacher','admin'); $quiz=null;$courseId=$id;
        if($action==='quiz-edit'){ $s=db()->prepare('SELECT * FROM quizzes WHERE id=?');$s->execute([$id]);$quiz=$s->fetch();if(!$quiz)exit('Тест не найден');$courseId=(int)$quiz['course_id'];}
        if(!can_manage_course($courseId,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['title']??'');if(!$title){flash('Введите название теста.');redirect($action==='quiz-edit'?'quiz-edit/'.$id:'quiz-create/'.$courseId);}
            if($quiz){db()->prepare('UPDATE quizzes SET title=?,description=? WHERE id=?')->execute([$title,trim($_POST['description']??''),$id]);$qid=$id;}
            else{require_quiz_type_capacity($u,$courseId,'normal');db()->prepare('INSERT INTO quizzes(course_id,title,description) VALUES(?,?,?)')->execute([$courseId,$title,trim($_POST['description']??'')]);$qid=(int)db()->lastInsertId();}
            $keep=[];
            foreach(($_POST['question_id']??[]) as $i=>$oldId){
                $qt=trim($_POST['question_text'][$i]??''); if($qt==='')continue;
                $oldId=(int)$oldId;
                if($oldId){db()->prepare('UPDATE questions SET question_text=?,sort_order=? WHERE id=? AND quiz_id=?')->execute([$qt,$i,$oldId,$qid]);$questionId=$oldId;}
                else{db()->prepare('INSERT INTO questions(quiz_id,question_text,sort_order) VALUES(?,?,?)')->execute([$qid,$qt,$i]);$questionId=(int)db()->lastInsertId();}
                $keep[]=$questionId;
                $old=db()->prepare('SELECT image_path FROM questions WHERE id=?');$old->execute([$questionId]);$oldImg=$old->fetchColumn();
                $img=upload_image('question_image_'.$i);
                if($img){db()->prepare('UPDATE questions SET image_path=? WHERE id=?')->execute([$img,$questionId]);delete_file($oldImg);}
                db()->prepare('DELETE FROM quiz_answers WHERE question_id=?')->execute([$questionId]);
                $answers=$_POST['answers'][$i]??[];$correct=(string)($_POST['correct'][$i]??'');$valid=0;$correctInserted=false;
                foreach($answers as $ai=>$a){$a=trim($a);if($a!==''){$valid++;$isCorrect=((string)$ai===$correct)?1:0;$correctInserted=$correctInserted||!!$isCorrect;db()->prepare('INSERT INTO quiz_answers(question_id,answer_text,is_correct) VALUES(?,?,?)')->execute([$questionId,$a,$isCorrect]);}}
                if($valid<2)throw new RuntimeException('У каждого вопроса должно быть минимум 2 заполненные варианта ответа.');
                if(!$correctInserted)throw new RuntimeException('Для каждого вопроса выберите правильный вариант ответа.');
            }
            if($quiz){
                $params=[$qid];$in=$keep?implode(',',array_fill(0,count($keep),'?')):'0';$params=array_merge([$qid],$keep);
                db()->prepare("DELETE FROM questions WHERE quiz_id=? AND id NOT IN ($in)")->execute($params);
            }
            flash('Тест сохранён.');redirect('course/'.$courseId);
        }
        $questions=[];
        if($quiz){$s=db()->prepare('SELECT * FROM questions WHERE quiz_id=? ORDER BY sort_order,id');$s->execute([$quiz['id']]);$questions=$s->fetchAll();foreach($questions as &$q){$a=db()->prepare('SELECT * FROM quiz_answers WHERE question_id=? ORDER BY id');$a->execute([$q['id']]);$q['answers']=$a->fetchAll();}}
        view('quiz/form',['quiz'=>$quiz,'questions'=>$questions,'courseId'=>$courseId,'me'=>$u]);exit;
    }

    if($action==='quiz-play'&&$id){
        $s=db()->prepare('SELECT q.*,qu.course_id,qu.title FROM quizzes qu JOIN questions q ON q.quiz_id=qu.id WHERE qu.id=? ORDER BY q.sort_order,q.id');$s->execute([$id]);$questions=$s->fetchAll();if(!$questions)exit('Тест пуст');$courseId=(int)$questions[0]['course_id'];if(!can_access_course($courseId,$u))exit('Forbidden');if(!assignment_allows($id,'quiz',$u))exit('Это задание назначено другому ученику.');
        foreach($questions as &$q){$a=db()->prepare('SELECT * FROM quiz_answers WHERE question_id=? ORDER BY RAND()');$a->execute([$q['id']]);$q['answers']=$a->fetchAll();}        
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$score=0;$attempt=db()->prepare('INSERT INTO attempts(student_id,course_id,type,material_id,total) VALUES(?,?,?,?,?)');
            $attempt->execute([$u['id'],$courseId,'quiz',$id,count($questions)]);$aid=(int)db()->lastInsertId();
            foreach($questions as $q){
                $given=$_POST['q'][$q['id']]??'';$correct='';
                foreach($q['answers'] as $a)if((int)$a['is_correct']){$correct=$a['answer_text'];break;}
                $ok=hash_equals($correct,(string)$given);if($ok)$score++;
                db()->prepare('INSERT INTO result_answers(attempt_id,question_id,prompt,given_answer,correct_answer,is_correct) VALUES(?,?,?,?,?,?)')
                    ->execute([$aid,$q['id'],$q['question_text'],$given,$correct,$ok?1:0]);
            }
            db()->prepare('UPDATE attempts SET score=? WHERE id=?')->execute([$score,$aid]);
            redirect('attempt-result/'.$aid);
        }
        view('quiz/play',['quizTitle'=>$questions[0]['title'],'questions'=>$questions,'me'=>$u]);exit;
    }


    /* ---------- QA quiz autosave ---------- */
    if ($action==='qa-quiz-autosave') {
        require_role('teacher','admin');
        if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }
        check_csrf(); header('Content-Type: application/json; charset=utf-8');
        $quizId=(int)($_POST['quiz_id']??0); $courseId=(int)($_POST['course_id']??0); $title=trim($_POST['title']??'');
        $optionCount=max(2,min(4,(int)($_POST['option_count']??4)));
        if($title===''){echo json_encode(['ok'=>false,'error'=>'Название не заполнено']);exit;}
        if($quizId){$s=db()->prepare("SELECT * FROM quizzes WHERE id=? AND quiz_type='qa'");$s->execute([$quizId]);$existing=$s->fetch();if(!$existing){echo json_encode(['ok'=>false,'error'=>'Квиз не найден']);exit;}$courseId=(int)$existing['course_id'];}
        if(!$courseId||!can_manage_course($courseId,$u)){echo json_encode(['ok'=>false,'error'=>'Forbidden']);exit;}
        try{
            if(!$quizId){require_quiz_type_capacity($u,$courseId,'qa');db()->prepare("INSERT INTO quizzes(course_id,title,description,quiz_type,qa_option_count) VALUES(?,?,?,?,?)")->execute([$courseId,$title,'Питання → Відповідь','qa',$optionCount]);$quizId=(int)db()->lastInsertId();}
            else db()->prepare("UPDATE quizzes SET title=?,qa_option_count=? WHERE id=? AND quiz_type='qa'")->execute([$title,$optionCount,$quizId]);
            $ids=$_POST['item_id']??[];$qs=$_POST['question_text']??[];$ans=$_POST['correct_answer']??[];
            foreach(['ids','qs','ans'] as $x)if(!is_array($$x))$$x=[$$x];
            $keep=[];$n=max(count($qs),count($ans));
            for($i=0;$i<$n;$i++){
                $q=trim((string)($qs[$i]??''));$a=trim((string)($ans[$i]??''));if($q===''||$a==='')continue;
                $bi=(int)($ids[$i]??0);$exists=false;
                if($bi){$st=db()->prepare('SELECT id FROM bank_questions WHERE id=? AND quiz_id=?');$st->execute([$bi,$quizId]);$exists=(bool)$st->fetchColumn();}
                if($exists){db()->prepare('UPDATE bank_questions SET question_text=?,correct_answer=? WHERE id=? AND quiz_id=?')->execute([$q,$a,$bi,$quizId]);$keep[]=$bi;}
                else{db()->prepare('INSERT INTO bank_questions(course_id,quiz_id,question_text,correct_answer,topic,image_path) VALUES(?,?,?,?,?,?)')->execute([$courseId,$quizId,$q,$a,'',null]);$keep[]=(int)db()->lastInsertId();}
            }
            // Не удаляем незаполненные строки во время набора текста; удаляем только
            // ранее сохранённые элементы, которые пользователь действительно убрал.
            if($keep){$marks=implode(',',array_fill(0,count($keep),'?'));$params=array_merge([$quizId],$keep);db()->prepare("DELETE FROM bank_questions WHERE quiz_id=? AND id NOT IN ($marks)")->execute($params);}
            echo json_encode(['ok'=>true,'quiz_id'=>$quizId,'course_id'=>$courseId,'saved_at'=>date('H:i:s')]);
        }catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Не удалось выполнить автосохранение']);}
        exit;
    }

    /* ---------- QA quiz: named Question → Answer quiz ---------- */
    if($action==='qa-quiz-create'&&$id){
        require_role('teacher','admin');$courseId=$id;if(!can_manage_course($courseId,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['title']??'');$optionCount=max(2,min(4,(int)($_POST['option_count']??4)));$qs=$_POST['question_text']??[];$ans=$_POST['correct_answer']??[];
            if(!is_array($qs))$qs=[$qs];if(!is_array($ans))$ans=[$ans];
            if($title===''){flash('Введите название квиза.');redirect('qa-quiz-create/'.$courseId);}
            $valid=[];$n=max(count($qs),count($ans));
            for($i=0;$i<$n;$i++){ $q=trim((string)($qs[$i]??''));$a=trim((string)($ans[$i]??''));if($q!==''&&$a!=='')$valid[]=[$q,$a]; }
            if(count($valid)<2){flash('Добавьте минимум 2 вопроса, чтобы приложение могло подмешать ответы.');redirect('qa-quiz-create/'.$courseId);}
            db()->beginTransaction();
            try{
                require_quiz_type_capacity($u,$courseId,'qa');
                db()->prepare("INSERT INTO quizzes(course_id,title,description,quiz_type,qa_option_count) VALUES(?,?,?,?,?)")->execute([$courseId,$title,'Питання → Відповідь','qa',$optionCount]);
                $qid=(int)db()->lastInsertId();
                foreach($valid as $i=>$r) db()->prepare('INSERT INTO bank_questions(course_id,quiz_id,question_text,correct_answer,topic,image_path) VALUES(?,?,?,?,?,?)')->execute([$courseId,$qid,$r[0],$r[1],'',null]);
                db()->commit();flash('Квиз Питання → Відповідь создан.');redirect('course/'.$courseId);
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        }
        view('special/qa-quiz-edit',['quiz'=>['course_id'=>$courseId,'title'=>''],'items'=>[],'courseId'=>$courseId,'createMode'=>true,'me'=>$u]);exit;
    }
    if($action==='qa-quiz-edit'&&$id){
        require_role('teacher','admin');$s=db()->prepare("SELECT * FROM quizzes WHERE id=? AND quiz_type='qa'");$s->execute([$id]);$quiz=$s->fetch();if(!$quiz)exit('Квиз не найден');
        if(!can_manage_course((int)$quiz['course_id'],$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['title']??'');$optionCount=max(2,min(4,(int)($_POST['option_count']??($quiz['qa_option_count']??4))));$qs=$_POST['question_text']??[];$ans=$_POST['correct_answer']??[];$ids=$_POST['item_id']??[];
            foreach(['qs','ans','ids'] as $x)if(!is_array($$x))$$x=[$$x];
            if($title===''){flash('Введите название квиза.');redirect('qa-quiz-edit/'.$id);}
            $keep=[];db()->beginTransaction();
            try{
                db()->prepare('UPDATE quizzes SET title=?,qa_option_count=? WHERE id=?')->execute([$title,$optionCount,$id]);
                $n=max(count($qs),count($ans));
                for($i=0;$i<$n;$i++){
                    $q=trim((string)($qs[$i]??''));$a=trim((string)($ans[$i]??''));if($q===''||$a==='')continue;
                    $bi=(int)($ids[$i]??0);
                    if($bi>0)db()->prepare('UPDATE bank_questions SET question_text=?,correct_answer=? WHERE id=? AND quiz_id=?')->execute([$q,$a,$bi,$id]);
                    else{db()->prepare('INSERT INTO bank_questions(course_id,quiz_id,question_text,correct_answer,topic,image_path) VALUES(?,?,?,?,?,?)')->execute([$quiz['course_id'],$id,$q,$a,'',null]);$bi=(int)db()->lastInsertId();}
                    $keep[]=$bi;
                }
                $marks=$keep?implode(',',array_fill(0,count($keep),'?')):'0';
                db()->prepare("DELETE FROM bank_questions WHERE quiz_id=? AND id NOT IN ($marks)")->execute(array_merge([$id],$keep));
                db()->commit();flash('Квиз обновлён.');redirect('course/'.$quiz['course_id']);
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        }
        $s=db()->prepare('SELECT * FROM bank_questions WHERE quiz_id=? ORDER BY id');$s->execute([$id]);$items=$s->fetchAll();
        view('special/qa-quiz-edit',['quiz'=>$quiz,'items'=>$items,'courseId'=>(int)$quiz['course_id'],'createMode'=>false,'me'=>$u]);exit;
    }
    if($action==='qa-quiz-play'&&$id){
        $s=db()->prepare("SELECT q.*,c.title course_title FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.id=? AND q.quiz_type='qa'");$s->execute([$id]);$quiz=$s->fetch();if(!$quiz)exit('Квиз не найден');
        if(!can_access_course((int)$quiz['course_id'],$u)||!assignment_allows($id,'quiz',$u))exit('Forbidden');
        $s=db()->prepare('SELECT * FROM bank_questions WHERE quiz_id=? ORDER BY id');$s->execute([$id]);$items=$s->fetchAll();
        $answers=array_values(array_unique(array_filter(array_map(fn($x)=>trim((string)$x['correct_answer']),$items),fn($x)=>$x!=='')));
        $wanted=max(2,min(4,(int)($quiz['qa_option_count']??4)));
        foreach($items as &$it){
            $correct=trim((string)$it['correct_answer']);
            $others=array_values(array_filter($answers,fn($a)=>strcasecmp($a,$correct)!==0));
            shuffle($others);
            $opts=array_merge([$correct],array_slice($others,0,$wanted-1));
            shuffle($opts);$it['options']=$opts;
        }
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$payload=[];
            foreach($items as $it){$payload[]=['id'=>(int)$it['id'],'answer'=>(string)($_POST['qa'][$it['id']]??'')];}
            $_POST['mode']='qa';$_POST['answers']=json_encode($payload,JSON_UNESCAPED_UNICODE);
            // Re-enter the common saver without relying on a second browser request.
            $mode='qa';$answers=$payload;
            $courseId=(int)$quiz['course_id'];$title=$quiz['title'];$total=count($items);
            $attempt=db()->prepare('INSERT INTO attempts(student_id,course_id,type,material_id,total) VALUES(?,?,?,?,?)');$attempt->execute([$u['id'],$courseId,'qa',$id,$total]);$aid=(int)db()->lastInsertId();$score=0;
            $ins=db()->prepare('INSERT INTO result_answers(attempt_id,bank_question_id,prompt,given_answer,correct_answer,is_correct) VALUES(?,?,?,?,?,?)');
            foreach($items as $it){$given=(string)($_POST['qa'][$it['id']]??'');$correct=(string)$it['correct_answer'];$ok=strtolower(trim($given))===strtolower(trim($correct));if($ok)$score++;$ins->execute([$aid,$it['id'],$it['question_text'],$given,$correct,$ok?1:0]);}
            db()->prepare('UPDATE attempts SET score=? WHERE id=?')->execute([$score,$aid]);redirect('attempt-result/'.$aid);
        }
        view('special/qa-quiz-play',['quiz'=>$quiz,'items'=>$items,'me'=>$u]);exit;
    }

    if($action==='bank'&&$id){
        require_role('teacher','admin');if(!can_manage_course($id,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();foreach($_POST['question_text']??[] as $i=>$t){$t=trim($t);$ans=trim($_POST['correct_answer'][$i]??'');if($t===''||$ans==='')continue;$img=upload_image('image_'.$i);db()->prepare('INSERT INTO bank_questions(course_id,question_text,correct_answer,topic,image_path) VALUES(?,?,?,?,?)')->execute([$id,$t,$ans,trim($_POST['topic'][$i]??''),$img]);}flash('Питання додані.');redirect('course/'.$id);}
        view('bank/form',['courseId'=>$id,'me'=>$u]);exit;
    }
    if($action==='bank-edit'&&$id){
        require_role('teacher','admin');$s=db()->prepare('SELECT b.*,c.teacher_id FROM bank_questions b JOIN courses c ON c.id=b.course_id WHERE b.id=?');$s->execute([$id]);$q=$s->fetch();if(!$q||!can_manage_course((int)$q['course_id'],$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$old=$q['image_path'];$img=upload_image('image');$path=$img?:$old;db()->prepare('UPDATE bank_questions SET question_text=?,correct_answer=?,topic=?,image_path=? WHERE id=?')->execute([trim($_POST['question_text']),trim($_POST['correct_answer']),trim($_POST['topic']),$path,$id]);if($img)delete_file($old);flash('Питання оновлено.');redirect('course/'.$q['course_id']);}
        view('bank/edit',['q'=>$q,'me'=>$u]);exit;
    }

    if($action==='bank-play'&&$id){
        $s=db()->prepare('SELECT * FROM bank_questions WHERE course_id=? ORDER BY RAND()');$s->execute([$id]);$bank=$s->fetchAll();if(!$bank)exit('Банк пуст');if(!can_access_course($id,$u))exit('Forbidden');
        $bank=array_slice($bank,0,min(20,count($bank)));
        foreach($bank as &$q){$others=$bank;$wrong=[];shuffle($others);foreach($others as $o){if($o['id']!=$q['id'] && $o['correct_answer']!==$q['correct_answer'] && !in_array($o['correct_answer'],$wrong,true)){$wrong[]=$o['correct_answer'];if(count($wrong)===3)break;}}$opts=array_merge([$q['correct_answer']],$wrong);shuffle($opts);$q['options']=$opts;}
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$score=0;$attempt=db()->prepare('INSERT INTO attempts(student_id,course_id,type,material_id,total) VALUES(?,?,?,?,?)');$attempt->execute([$u['id'],$id,'bank',$id,count($bank)]);$aid=(int)db()->lastInsertId();foreach($bank as $q){$given=$_POST['q'][$q['id']]??'';$ok=hash_equals($q['correct_answer'],$given);if($ok)$score++;db()->prepare('INSERT INTO result_answers(attempt_id,bank_question_id,prompt,given_answer,correct_answer,is_correct) VALUES(?,?,?,?,?,?)')->execute([$aid,$q['id'],$q['question_text'],$given,$q['correct_answer'],$ok]);}db()->prepare('UPDATE attempts SET score=? WHERE id=?')->execute([$score,$aid]);redirect('attempt-result/'.$aid);}
        view('bank/play',['courseId'=>$id,'questions'=>$bank,'me'=>$u]);exit;
    }

    
    if($action==='flashcard-quiz-create'&&$id){
        require_role('teacher','admin');$courseId=$id;if(!can_manage_course($courseId,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['title']??'');$front=$_POST['front']??[];$back=$_POST['back']??[];
            if($title===''){flash('Введите название квиза.');redirect('flashcard-quiz-create/'.$courseId);}
            $keys=$_POST['card_key']??[];$fUrls=$_POST['front_audio_url']??[];$bUrls=$_POST['back_audio_url']??[];$valid=[];$n=max(count((array)$front),count((array)$back));
            for($i=0;$i<$n;$i++){
                $key=(int)($keys[$i]??$i);$a=trim((string)($front[$i]??''));$b=trim((string)($back[$i]??''));
                $fi=upload_image('front_image_'.$key);$bi=upload_image('back_image_'.$key);$fa=upload_audio('front_audio_'.$key);$ba=upload_audio('back_audio_'.$key);
                $fa=$fa?:trim((string)($fUrls[$i]??''));$ba=$ba?:trim((string)($bUrls[$i]??''));
                if($a!==''||$b!==''||$fi||$bi||$fa||$ba)$valid[]=[$a,$b,$fi,$bi,$fa,$ba,$key];
            }
            if(count($valid)<2){flash('Добавьте минимум 2 карточки.');redirect('flashcard-quiz-create/'.$courseId);}
            db()->beginTransaction();
            try{
                require_quiz_type_capacity($u,$courseId,'flashcards');
                db()->prepare("INSERT INTO quizzes(course_id,title,description,quiz_type) VALUES(?,?,?,'flashcards')")->execute([$courseId,$title,'Интерактивные обычные карточки']);
                $qid=(int)db()->lastInsertId();
                foreach($valid as $r){
                    // Current schema has image columns; audio is optional and may not exist yet.
                    $st=db()->prepare('INSERT INTO flashcards(course_id,quiz_id,front_text,back_text,front_image_path,back_image_path,front_audio_path,back_audio_path) VALUES(?,?,?,?,?,?,?,?)');
                    $st->execute([$courseId,$qid,$r[0],$r[1],$r[2],$r[3],$r[4],$r[5]]);
                }
                db()->commit();flash('Квиз с карточками создан.');redirect('course/'.$courseId);
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        }
        view('special/flashcard-quiz-form',['courseId'=>$courseId,'me'=>$u]);exit;
    }

    if($action==='flashcards-legacy'&&$id){
        require_role('teacher','admin');if(!can_manage_course($id,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();
            foreach($_POST['front']??[] as $i=>$front){
                $front=trim($front);$back=trim($_POST['back'][$i]??'');
                if($front==='')continue;
                $fi=upload_image('front_image_'.$i);$bi=upload_image('back_image_'.$i);
                require_quiz_type_capacity($u,$id,'legacy-flashcards');
                db()->prepare('INSERT INTO flashcards(course_id,front_text,back_text,front_image_path,back_image_path) VALUES(?,?,?,?,?)')
                  ->execute([$id,$front,$back,$fi,$bi]);
            }
            flash('Картки додані.');redirect('course/'.$id);
        }
        view('flashcards/form',['courseId'=>$id,'me'=>$u]);exit;
    }
    if($action==='flashcard-edit-legacy'&&$id){
        require_role('teacher','admin');
        $s=db()->prepare('SELECT f.*,c.teacher_id,c.title course_title FROM flashcards f JOIN courses c ON c.id=f.course_id WHERE f.id=?');
        $s->execute([$id]);$card=$s->fetch();
        if(!$card||!can_manage_course((int)$card['course_id'],$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();
            $front=trim($_POST['front_text']??'');$back=trim($_POST['back_text']??'');
            if($front===''){flash('Заповніть лицьову сторону.');redirect('flashcard-edit/'.$id);}
            $fi=upload_image('front_image');$bi=upload_image('back_image');
            if($fi){delete_file($card['front_image_path']);$card['front_image_path']=$fi;}
            if($bi){delete_file($card['back_image_path']);$card['back_image_path']=$bi;}
            db()->prepare('UPDATE flashcards SET front_text=?,back_text=?,front_image_path=?,back_image_path=? WHERE id=?')
              ->execute([$front,$back,$card['front_image_path'],$card['back_image_path'],$id]);
            flash('Картку оновлено.');redirect('course/'.$card['course_id']);
        }
        view('flashcards/edit',['card'=>$card,'me'=>$u]);exit;
    }
    if($action==='matching'&&$id){
        require_role('teacher','admin');if(!can_manage_course($id,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['title']??'');
            if(!$title)throw new RuntimeException('Введіть назву.');
            require_quiz_type_capacity($u,$id,'matching');
            db()->prepare('INSERT INTO matching_sets(course_id,title,description) VALUES(?,?,?)')->execute([$id,$title,trim($_POST['description']??'')]);
            $sid=(int)db()->lastInsertId();
            foreach($_POST['prompt']??[] as $i=>$p){
                $p=trim($p);$a=trim($_POST['answer'][$i]??'');
                if($p!==''&&$a!=='')db()->prepare('INSERT INTO matching_pairs(set_id,prompt,answer) VALUES(?,?,?)')->execute([$sid,$p,$a]);
            }
            flash('Сопоставлення створено.');redirect('course/'.$id);
        }
        view('matching/form',['courseId'=>$id,'me'=>$u,'set'=>null,'pairs'=>[]]);exit;
    }
    if($action==='matching-edit'&&$id){
        require_role('teacher','admin');
        $s=db()->prepare('SELECT ms.*,c.teacher_id,c.title course_title FROM matching_sets ms JOIN courses c ON c.id=ms.course_id WHERE ms.id=?');
        $s->execute([$id]);$set=$s->fetch();
        if(!$set||!can_manage_course((int)$set['course_id'],$u))exit('Forbidden');
        $p=db()->prepare('SELECT * FROM matching_pairs WHERE set_id=? ORDER BY id');$p->execute([$id]);$pairs=$p->fetchAll();
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['title']??'');$prompts=$_POST['prompt']??[];$answers=$_POST['answer']??[];$ids=$_POST['pair_id']??[];
            if(!$title){flash('Введіть назву.');redirect('matching-edit/'.$id);}
            $valid=0;$keep=[];
            db()->prepare('UPDATE matching_sets SET title=?,description=? WHERE id=?')->execute([$title,trim($_POST['description']??''),$id]);
            foreach($prompts as $i=>$p){
                $p=trim($p);$a=trim($answers[$i]??'');if($p===''||$a==='')continue;
                $pid=(int)($ids[$i]??0); $pi=upload_image('prompt_image_'.$i); $ai=upload_image('answer_image_'.$i);
                if($pid){
                    $oldp=db()->prepare('SELECT prompt_image_path,answer_image_path FROM matching_pairs WHERE id=? AND set_id=?');$oldp->execute([$pid,$id]);$old=$oldp->fetch();
                    if($pi) { delete_file($old['prompt_image_path']??null); } else $pi=$old['prompt_image_path']??null;
                    if($ai) { delete_file($old['answer_image_path']??null); } else $ai=$old['answer_image_path']??null;
                    db()->prepare('UPDATE matching_pairs SET prompt=?,answer=?,prompt_image_path=?,answer_image_path=? WHERE id=? AND set_id=?')->execute([$p,$a,$pi,$ai,$pid,$id]);
                    $keep[]=$pid;
                }else{
                    db()->prepare('INSERT INTO matching_pairs(set_id,prompt,answer,prompt_image_path,answer_image_path) VALUES(?,?,?,?,?)')->execute([$id,$p,$a,$pi,$ai]);
                    $keep[]=(int)db()->lastInsertId();
                }
                $valid++;
            }
            if($valid<2){flash('Потрібно щонайменше 2 заповнені пари.');redirect('matching-edit/'.$id);}
            if($keep){
                $marks=implode(',',array_fill(0,count($keep),'?'));
                $params=array_merge([$id],$keep);
                db()->prepare("DELETE FROM matching_pairs WHERE set_id=? AND id NOT IN ($marks)")->execute($params);
            }
            flash('Сопоставлення оновлено.');redirect('course/'.$set['course_id']);
        }
        view('matching/form',['courseId'=>$set['course_id'],'me'=>$u,'set'=>$set,'pairs'=>$pairs]);exit;
    }
    if($action==='matching-play'&&$id){
        $s=db()->prepare('SELECT ms.*,c.id course_id FROM matching_sets ms JOIN courses c ON c.id=ms.course_id WHERE ms.id=?');$s->execute([$id]);$set=$s->fetch();if(!$set||!can_access_course((int)$set['course_id'],$u)||!assignment_allows((int)$set['id'],'matching',$u))exit('Forbidden');$p=db()->prepare('SELECT * FROM matching_pairs WHERE set_id=? ORDER BY id');$p->execute([$id]);view('matching/play',['set'=>$set,'pairs'=>$p->fetchAll(),'me'=>$u]);exit;
    }

    if($action==='roulette'&&$id){
        require_role('teacher','admin'); if(!can_manage_course($id,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf(); $title=trim($_POST['title']??'Рулетка');
            if(!$title){flash('Введіть назву рулетки.');redirect('roulette/'.$id);}
            require_quiz_type_capacity($u,$id,'roulette');
            db()->prepare('INSERT INTO roulette_sets(course_id,title,description) VALUES(?,?,?)')->execute([$id,$title,trim($_POST['description']??'')]); $rid=(int)db()->lastInsertId();
            foreach($_POST['label']??[] as $i=>$label){ $label=trim($label); if($label==='')continue; $img=upload_image('segment_image_'.$i); $weight=(float)($_POST['weight'][$i]??1); if($weight<=0)$weight=1; db()->prepare('INSERT INTO roulette_segments(roulette_id,label,image_path,weight,sort_order) VALUES(?,?,?,?,?)')->execute([$rid,$label,$img,$weight,$i]); }
            flash('Рулетка створена.'); redirect('course/'.$id);
        }
        view('roulette/form',['courseId'=>$id,'me'=>$u,'roulette'=>null,'segments'=>[]]); exit;
    }
    if($action==='roulette-edit'&&$id){
        require_role('teacher','admin'); $s=db()->prepare('SELECT r.*,c.teacher_id FROM roulette_sets r JOIN courses c ON c.id=r.course_id WHERE r.id=?');$s->execute([$id]);$r=$s->fetch();if(!$r||!can_manage_course((int)$r['course_id'],$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf(); db()->prepare('UPDATE roulette_sets SET title=?,description=? WHERE id=?')->execute([trim($_POST['title']),trim($_POST['description']),$id]);
            $keep=[]; foreach($_POST['segment_id']??[] as $i=>$sid){$label=trim($_POST['label'][$i]??'');if($label==='')continue;$sid=(int)$sid;$img=upload_image('segment_image_'.$i);if($sid){$q=db()->prepare('SELECT image_path FROM roulette_segments WHERE id=? AND roulette_id=?');$q->execute([$sid,$id]);$old=$q->fetchColumn();if($img){delete_file($old);}else{$img=$old;}db()->prepare('UPDATE roulette_segments SET label=?,image_path=?,weight=?,sort_order=? WHERE id=? AND roulette_id=?')->execute([$label,$img,max(0.01,(float)($_POST['weight'][$i]??1)),$i,$sid,$id]);$keep[]=$sid;}else{db()->prepare('INSERT INTO roulette_segments(roulette_id,label,image_path,weight,sort_order) VALUES(?,?,?,?,?)')->execute([$id,$label,$img,max(0.01,(float)($_POST['weight'][$i]??1)),$i]);$keep[]=(int)db()->lastInsertId();}}
            if($keep){$marks=implode(',',array_fill(0,count($keep),'?'));db()->prepare("DELETE FROM roulette_segments WHERE roulette_id=? AND id NOT IN ($marks)")->execute(array_merge([$id],$keep));}else db()->prepare('DELETE FROM roulette_segments WHERE roulette_id=?')->execute([$id]);
            flash('Рулетка обновлена.');redirect('course/'.$r['course_id']);
        }
        $q=db()->prepare('SELECT * FROM roulette_segments WHERE roulette_id=? ORDER BY sort_order,id');$q->execute([$id]);view('roulette/form',['courseId'=>$r['course_id'],'me'=>$u,'roulette'=>$r,'segments'=>$q->fetchAll()]);exit;
    }
    if($action==='roulette-play'&&$id){
        $s=db()->prepare('SELECT r.*,c.id course_id FROM roulette_sets r JOIN courses c ON c.id=r.course_id WHERE r.id=?');$s->execute([$id]);$r=$s->fetch();if(!$r||!can_access_course((int)$r['course_id'],$u))exit('Forbidden');$q=db()->prepare('SELECT * FROM roulette_segments WHERE roulette_id=? ORDER BY sort_order,id');$q->execute([$id]);view('roulette/play',['roulette'=>$r,'segments'=>$q->fetchAll(),'me'=>$u]);exit;
    }

    if($action==='analytics'&&$id){
        require_role('teacher','admin');
        if(!can_manage_course($id,$u)) { http_response_code(403); exit('Доступ к аналитике этого курса запрещён.'); }
        $s=db()->prepare('SELECT * FROM courses WHERE id=? LIMIT 1');
        $s->execute([$id]);
        $course=$s->fetch();
        if(!$course){
            http_response_code(404);
            flash('Курс не найден или был удалён.');
            redirect($u['role']==='admin' ? 'admin/teachers' : 'teacher/courses');
        }
        $s=db()->prepare("SELECT COUNT(*) attempts,COALESCE(AVG(score/NULLIF(total,0)*100),0) avg_score FROM attempts WHERE course_id=?");$s->execute([$id]);$stats=$s->fetch() ?: ['attempts'=>0,'avg_score'=>0];
        $s=db()->prepare("SELECT u.name,COUNT(a.id) attempts,COALESCE(AVG(a.score/NULLIF(a.total,0)*100),0) avg_score FROM course_members cm JOIN users u ON u.id=cm.student_id LEFT JOIN attempts a ON a.student_id=u.id AND a.course_id=? WHERE cm.course_id=? GROUP BY u.id ORDER BY avg_score DESC");$s->execute([$id,$id]);$students=$s->fetchAll();
        $s=db()->prepare("SELECT ra.prompt,AVG(ra.is_correct)*100 success FROM result_answers ra JOIN attempts a ON a.id=ra.attempt_id WHERE a.course_id=? GROUP BY ra.prompt ORDER BY success ASC LIMIT 10");$s->execute([$id]);$hard=$s->fetchAll();
        view('analytics/course',['course'=>$course,'stats'=>$stats,'students'=>$students,'hard'=>$hard,'me'=>$u]);exit;
    }

    if($action==='students'&&$id){
        require_role('teacher','admin');if(!can_manage_course($id,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$email=strtolower(trim($_POST['email']??''));$s=db()->prepare("SELECT id FROM users WHERE email=? AND role='student'");$s->execute([$email]);$student=$s->fetch();if($student){db()->prepare("INSERT INTO course_members(course_id,student_id,status) VALUES(?,?, 'active') ON DUPLICATE KEY UPDATE status='active'")->execute([$id,$student['id']]);flash('Ученик добавлен.');}else flash('Ученик не найден.');redirect('students/'.$id);}
        $s=db()->prepare("SELECT u.*,cm.status FROM course_members cm JOIN users u ON u.id=cm.student_id WHERE cm.course_id=? ORDER BY u.name");$s->execute([$id]);view('students/course',['courseId'=>$id,'students'=>$s->fetchAll(),'me'=>$u]);exit;
    }


    /* ---------- Sentence Builder: standalone named quiz ---------- */
    if($action==='sentence-create'){
        require_role('teacher','admin');$courseId=$id;if(!$courseId||!can_manage_course($courseId,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['quiz_title']??'');$prompts=$_POST['prompt']??[];$corrects=$_POST['correct_sentence']??[];
            if(!is_array($prompts))$prompts=[$prompts];if(!is_array($corrects))$corrects=[$corrects];
            if($title===''){flash('Введите тему/название интерактивного квиза.');redirect('sentence-create/'.$courseId);}
            $valid=[];$max=max(count($prompts),count($corrects));
            for($i=0;$i<$max;$i++){ $p=trim((string)($prompts[$i]??''));$c=trim((string)($corrects[$i]??'')); if($p!==''&&$c!=='')$valid[]=[$p,$c]; }
            if(!$valid){flash('Добавьте хотя бы одно предложение.');redirect('sentence-create/'.$courseId);}
            db()->beginTransaction();
            try{
                require_quiz_type_capacity($u,$courseId,'sentence');
                db()->prepare("INSERT INTO quizzes(course_id,title,description,quiz_type) VALUES(?,?,?,'sentence')")->execute([$courseId,$title,'Интерактивный квиз — слова в разброс']);$qid=(int)db()->lastInsertId();
                foreach($valid as $i=>$row){
                    $audio=null;
                    if(false && isset($_FILES['audio_file']['name'][$i])&&($_FILES['audio_file']['error'][$i]??99)===UPLOAD_ERR_OK){
                        $_FILES['_audio_one']=['name'=>$_FILES['audio_file']['name'][$i],'type'=>$_FILES['audio_file']['type'][$i],'tmp_name'=>$_FILES['audio_file']['tmp_name'][$i],'error'=>$_FILES['audio_file']['error'][$i],'size'=>$_FILES['audio_file']['size'][$i]];
                        $audio=upload_audio('_audio_one');
                    }
                    $audio=null;
                    db()->prepare('INSERT INTO sentence_builder_items(quiz_id,prompt,correct_sentence,audio_url,sort_order) VALUES(?,?,?,?,?)')->execute([$qid,$row[0],$row[1],$audio,$i]);
                    $sid=(int)db()->lastInsertId();$wi=0;foreach(preg_split('/\s+/',trim($row[1])) as $word)if($word!=='')db()->prepare('INSERT INTO sentence_builder_words(item_id,word_text,word_order) VALUES(?,?,?)')->execute([$sid,$word,$wi++]);
                }
                db()->commit();
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
            flash('Интерактивный квиз создан.');redirect('course/'.$courseId);
        }
        view('special/sentence-form',['courseId'=>$courseId,'items'=>[],'me'=>$u]);exit;
    }
    if($action==='sentence-quiz-edit'&&$id){
        require_role('teacher','admin');
        $q=db()->prepare("SELECT * FROM quizzes WHERE id=? AND quiz_type='sentence'");$q->execute([$id]);$quiz=$q->fetch();if(!$quiz)exit('Квиз не найден');if(!can_manage_course((int)$quiz['course_id'],$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['quiz_title']??'');$prompts=$_POST['prompt']??[];$corrects=$_POST['correct_sentence']??[];$ids=$_POST['item_id']??[];
            foreach(['prompts','corrects','ids'] as $v)if(!is_array($$v))$$v=[$$v];
            if($title===''){flash('Введите название квиза.');redirect('sentence-quiz-edit/'.$id);}
            $keep=[];db()->beginTransaction();
            try{
                db()->prepare('UPDATE quizzes SET title=? WHERE id=?')->execute([$title,$id]);
                $n=max(count($prompts),count($corrects));
                for($i=0;$i<$n;$i++){
                    $p=trim((string)($prompts[$i]??''));$c=trim((string)($corrects[$i]??''));if($p===''||$c==='')continue;
                    $sid=(int)($ids[$i]??0);
                    if($sid){db()->prepare('UPDATE sentence_builder_items SET prompt=?,correct_sentence=?,sort_order=? WHERE id=? AND quiz_id=?')->execute([$p,$c,$i,$sid,$id]);}
                    else{db()->prepare('INSERT INTO sentence_builder_items(quiz_id,prompt,correct_sentence,sort_order) VALUES(?,?,?,?)')->execute([$id,$p,$c,$i]);$sid=(int)db()->lastInsertId();}
                    db()->prepare('DELETE FROM sentence_builder_words WHERE item_id=?')->execute([$sid]);
                    $wi=0;foreach(preg_split('/\s+/',trim($c)) as $word)if($word!=='')db()->prepare('INSERT INTO sentence_builder_words(item_id,word_text,word_order) VALUES(?,?,?)')->execute([$sid,$word,$wi++]);
                    $keep[]=$sid;
                }
                if($keep){$marks=implode(',',array_fill(0,count($keep),'?'));db()->prepare("DELETE FROM sentence_builder_items WHERE quiz_id=? AND id NOT IN ($marks)")->execute(array_merge([$id],$keep));}
                else db()->prepare('DELETE FROM sentence_builder_items WHERE quiz_id=?')->execute([$id]);
                db()->commit();
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
            flash('Квиз обновлён.');redirect('course/'.$quiz['course_id']);
        }
        $q=db()->prepare('SELECT * FROM sentence_builder_items WHERE quiz_id=? ORDER BY sort_order,id');$q->execute([$id]);
        view('special/sentence-quiz-edit',['quiz'=>$quiz,'items'=>$q->fetchAll(),'me'=>$u]);exit;
    }

    if($action==='sentence-edit'&&$id){
        require_role('teacher','admin');$q=db()->prepare('SELECT s.*,q.course_id,q.title quiz_title FROM sentence_builder_items s JOIN quizzes q ON q.id=s.quiz_id WHERE s.id=?');$q->execute([$id]);$item=$q->fetch();if(!$item)exit('Задание не найдено');if(!can_manage_course((int)$item['course_id'],$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$p=trim($_POST['prompt']??'');$c=trim($_POST['correct_sentence']??'');$aud=trim($_POST['audio_url']??'');$up=upload_audio('audio_file');if($up)$aud=$up;if($p===''||$c===''){flash('Заполните предложение.');redirect('sentence-edit/'.$id);}db()->prepare('UPDATE sentence_builder_items SET prompt=?,correct_sentence=?,audio_url=? WHERE id=?')->execute([$p,$c,$aud?:null,$id]);db()->prepare('DELETE FROM sentence_builder_words WHERE item_id=?')->execute([$id]);$wi=0;foreach(preg_split('/\s+/',trim($c)) as $word)if($word!=='')db()->prepare('INSERT INTO sentence_builder_words(item_id,word_text,word_order) VALUES(?,?,?)')->execute([$id,$word,$wi++]);flash('Сохранено.');redirect('course/'.$item['course_id']);}
        view('special/sentence-edit',['item'=>$item,'me'=>$u]);exit;
    }
    if($action==='sentence-play'&&$id){
        $q=db()->prepare('SELECT s.*,q.course_id,q.title quiz_title FROM sentence_builder_items s JOIN quizzes q ON q.id=s.quiz_id WHERE s.id=?');$q->execute([$id]);$item=$q->fetch();if(!$item)exit('Задание не найдено');if(!can_access_course((int)$item['course_id'],$u)||!assignment_allows((int)$item['quiz_id'],'quiz',$u))exit('Forbidden');$w=db()->prepare('SELECT word_text FROM sentence_builder_words WHERE item_id=? ORDER BY RAND()');$w->execute([$id]);view('special/sentence-play',['item'=>$item,'words'=>$w->fetchAll(),'me'=>$u]);exit;
    }

    if($action==='flashcard-quiz-edit'&&$id){
        require_role('teacher','admin');
        $q=db()->prepare("SELECT * FROM quizzes WHERE id=? AND quiz_type='flashcards'");$q->execute([$id]);$quiz=$q->fetch();if(!$quiz)exit('Квиз не найден');
        if(!can_manage_course((int)$quiz['course_id'],$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['title']??'');$front=$_POST['front']??[];$back=$_POST['back']??[];$keys=$_POST['card_key']??[];$fUrls=$_POST['front_audio_url']??[];$bUrls=$_POST['back_audio_url']??[];
            if($title===''){flash('Введите название квиза.');redirect('flashcard-quiz-edit/'.$id);}
            if(!is_array($front))$front=[$front];if(!is_array($back))$back=[$back];if(!is_array($keys))$keys=[$keys];if(!is_array($fUrls))$fUrls=[$fUrls];if(!is_array($bUrls))$bUrls=[$bUrls];
            $valid=[];$n=max(count($front),count($back));
            for($i=0;$i<$n;$i++){
                $key=(int)($keys[$i]??$i);$a=trim((string)($front[$i]??''));$b=trim((string)($back[$i]??''));
                $fi=upload_image('front_image_'.$key);$bi=upload_image('back_image_'.$key);$fa=upload_audio('front_audio_'.$key);$ba=upload_audio('back_audio_'.$key);
                $oldfi=trim((string)($_POST['existing_front_image'][$i]??''));$oldbi=trim((string)($_POST['existing_back_image'][$i]??''));$oldfa=trim((string)($_POST['existing_front_audio'][$i]??''));$oldba=trim((string)($_POST['existing_back_audio'][$i]??''));
                $fi=$fi?:($oldfi?:null);$bi=$bi?:($oldbi?:null);$fa=$fa?:trim((string)($fUrls[$i]??''));$ba=$ba?:trim((string)($bUrls[$i]??''));
                if($fa==='' )$fa=$oldfa?:null;if($ba==='')$ba=$oldba?:null;
                if($a!==''||$b!==''||$fi||$bi||$fa||$ba)$valid[]=[$a,$b,$fi,$bi,$fa,$ba];
            }
            if(count($valid)<2){flash('Добавьте минимум 2 карточки.');redirect('flashcard-quiz-edit/'.$id);}
            db()->beginTransaction();
            try{
                db()->prepare('UPDATE quizzes SET title=? WHERE id=?')->execute([$title,$id]);
                db()->prepare('DELETE FROM flashcards WHERE quiz_id=?')->execute([$id]);
                $st=db()->prepare('INSERT INTO flashcards(course_id,quiz_id,front_text,back_text,front_image_path,back_image_path,front_audio_path,back_audio_path) VALUES(?,?,?,?,?,?,?,?)');
                foreach($valid as $r)$st->execute([$quiz['course_id'],$id,$r[0],$r[1],$r[2],$r[3],$r[4],$r[5]]);
                db()->commit();flash('Квиз обновлён.');redirect('course/'.$quiz['course_id']);
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        }
        $s=db()->prepare('SELECT * FROM flashcards WHERE quiz_id=? ORDER BY id');$s->execute([$id]);$existingCards=$s->fetchAll();
        view('special/flashcard-quiz-form',['courseId'=>$quiz['course_id'],'quiz'=>$quiz,'editId'=>$id,'existingCards'=>$existingCards,'me'=>$u]);exit;
    }

    if($action==='flashcard-quiz-play'&&$id){
        $q=db()->prepare("SELECT * FROM quizzes WHERE id=? AND quiz_type='flashcards'");$q->execute([$id]);$g=$q->fetch();if(!$g)exit('Квиз не найден');if(!can_access_course((int)$g['course_id'],$u))exit('Forbidden');
        $s=db()->prepare('SELECT * FROM flashcards WHERE quiz_id=? ORDER BY id');$s->execute([$id]);view('special/flashcard-quiz-play',['quiz'=>$g,'cards'=>$s->fetchAll(),'me'=>$u]);exit;
    }

    if($action==='game-quiz'&&$id){
        $q=db()->prepare("SELECT * FROM quizzes WHERE id=? AND quiz_type IN ('sentence','fill')");$q->execute([$id]);$g=$q->fetch();if(!$g)exit('Квиз не найден');
        if(!can_access_course((int)$g['course_id'],$u)||!assignment_allows((int)$g['id'],'quiz',$u))exit('Forbidden');
        if($g['quiz_type']==='fill'){
            $s=db()->prepare('SELECT * FROM fill_blank_items WHERE quiz_id=? ORDER BY sort_order,id');$s->execute([$id]);$items=$s->fetchAll();
            foreach($items as &$it){$o=db()->prepare('SELECT * FROM fill_blank_options WHERE item_id=? ORDER BY sort_order,id');$o->execute([$it['id']]);$it['options']=$o->fetchAll();}
            view('special/fill-quiz-play',['quiz'=>$g,'items'=>$items,'me'=>$u]);exit;
        }
        $s=db()->prepare('SELECT * FROM sentence_builder_items WHERE quiz_id=? ORDER BY sort_order,id');$s->execute([$id]);$items=$s->fetchAll();
        foreach($items as &$it){$w=db()->prepare('SELECT word_text FROM sentence_builder_words WHERE item_id=? ORDER BY word_order,id');$w->execute([$it['id']]);$it['words']=$w->fetchAll();}
        view('special/sentence-quiz-play',['quiz'=>$g,'items'=>$items,'me'=>$u]);exit;
    }

    /* ---------- Fill in the Blank: standalone named quiz ---------- */
    if($action==='fill-create'){
        require_role('teacher','admin');$courseId=$id;if(!$courseId||!can_manage_course($courseId,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['quiz_title']??'');$templates=$_POST['sentence_template']??[];$corrects=$_POST['correct_answer']??[];$urls=$_POST['audio_url']??[];$opts=$_POST['options']??[];$modes=$_POST['answer_mode']??[];
            foreach(['templates','corrects','urls','opts','modes'] as $vn){if(!is_array($$vn))$$vn=[$$vn];}
            if($title===''){flash('Введите тему/название интерактивного квиза.');redirect('fill-create/'.$courseId);}
            $valid=[];$max=max(count($templates),count($corrects));
            for($i=0;$i<$max;$i++){ $st=trim((string)($templates[$i]??''));$ca=trim((string)($corrects[$i]??''));$oo=$opts[$i]??[];if(!is_array($oo))$oo=[$oo];$oo=array_values(array_filter(array_map(fn($v)=>trim((string)$v),$oo),fn($v)=>$v!==''));$mode=in_array((string)($modes[$i]??'choice'),['choice','drag','type'],true)?(string)$modes[$i]:'choice';if($st!==''&&$ca!==''&&count($oo)>=2&&strpos($st,'___')!==false)$valid[]=[$st,$ca,$oo,trim((string)($urls[$i]??'')),$mode]; }
            if(!$valid){flash('Добавьте хотя бы одно предложение с меткой ___ (место пропуска) и минимум 2 варианта для каждого.');redirect('fill-create/'.$courseId);}
            db()->beginTransaction();
            try{
                require_quiz_type_capacity($u,$courseId,'fill');
                db()->prepare("INSERT INTO quizzes(course_id,title,description,quiz_type) VALUES(?,?,?,'fill')")->execute([$courseId,$title,'Интерактивный квиз — вставка слова']);$qid=(int)db()->lastInsertId();
                foreach($valid as $i=>$row){
                    $audio=null;
                    if(false && isset($_FILES['audio_file']['name'][$i])&&($_FILES['audio_file']['error'][$i]??99)===UPLOAD_ERR_OK){
                        $_FILES['_audio_one']=['name'=>$_FILES['audio_file']['name'][$i],'type'=>$_FILES['audio_file']['type'][$i],'tmp_name'=>$_FILES['audio_file']['tmp_name'][$i],'error'=>$_FILES['audio_file']['error'][$i],'size'=>$_FILES['audio_file']['size'][$i]];
                        $audio=upload_audio('_audio_one');
                    }
                    $audio=$audio?:($row[3]!==''?$row[3]:null);
                    db()->prepare('INSERT INTO fill_blank_items(quiz_id,sentence_template,correct_answer,audio_url,answer_mode,sort_order) VALUES(?,?,?,?,?,?)')->execute([$qid,$row[0],$row[1],$audio,$row[4],$i]);$fid=(int)db()->lastInsertId();
                    foreach($row[2] as $j=>$o)db()->prepare('INSERT INTO fill_blank_options(item_id,option_text,is_correct,sort_order) VALUES(?,?,?,?)')->execute([$fid,$o,strtolower($o)===strtolower($row[1])?1:0,$j]);
                }
                db()->commit();
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
            flash('Интерактивный квиз создан.');redirect('course/'.$courseId);
        }
        view('special/fill-form',['courseId'=>$courseId,'items'=>[],'me'=>$u]);exit;
    }
    if($action==='fill-quiz-edit'&&$id){
        require_role('teacher','admin');
        $q=db()->prepare('SELECT * FROM quizzes WHERE id=? AND quiz_type="fill"');$q->execute([$id]);$quiz=$q->fetch();if(!$quiz)exit('Квиз не найден');
        if(!can_manage_course((int)$quiz['course_id'],$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();
            $title=trim($_POST['quiz_title']??'');$items=$_POST['items']??[];
            if($title===''){flash('Введите название квиза.');redirect('fill-quiz-edit/'.$id);}
            if(!is_array($items))$items=[];
            db()->prepare('UPDATE quizzes SET title=? WHERE id=?')->execute([$title,$id]);
            $keep=[];$sort=0;
            foreach($items as $item){
                $fid=(int)($item['id']??0);$st=trim((string)($item['sentence_template']??''));$ca=trim((string)($item['correct_answer']??''));$mode=in_array(($item['answer_mode']??'choice'),['choice','drag','type'],true)?$item['answer_mode']:'choice';
                $ops=$item['options']??[];if(!is_array($ops))$ops=[];$ops=array_values(array_filter(array_map('trim',$ops),fn($x)=>$x!==''));
                if($st===''||$ca===''||count($ops)<2)continue;
                if($fid){db()->prepare('UPDATE fill_blank_items SET sentence_template=?,correct_answer=?,answer_mode=?,sort_order=? WHERE id=? AND quiz_id=?')->execute([$st,$ca,$mode,$sort,$fid,$id]);$itemId=$fid;}
                else{db()->prepare('INSERT INTO fill_blank_items(quiz_id,sentence_template,correct_answer,answer_mode,sort_order) VALUES(?,?,?,?,?)')->execute([$id,$st,$ca,$mode,$sort]);$itemId=(int)db()->lastInsertId();}
                db()->prepare('DELETE FROM fill_blank_options WHERE item_id=?')->execute([$itemId]);
                foreach($ops as $oi=>$op)db()->prepare('INSERT INTO fill_blank_options(item_id,option_text,is_correct,sort_order) VALUES(?,?,?,?)')->execute([$itemId,$op,strtolower($op)===strtolower($ca)?1:0,$oi]);
                $keep[]=$itemId;$sort++;
            }
            if(count($keep)>0){$in=implode(',',array_fill(0,count($keep),'?'));db()->prepare("DELETE FROM fill_blank_items WHERE quiz_id=? AND id NOT IN ($in)")->execute(array_merge([$id],$keep));}
            else {db()->prepare('DELETE FROM fill_blank_items WHERE quiz_id=?')->execute([$id]);}
            flash('Квиз сохранён.');redirect('course/'.$quiz['course_id']);
        }
        $q=db()->prepare('SELECT * FROM fill_blank_items WHERE quiz_id=? ORDER BY sort_order,id');$q->execute([$id]);$items=$q->fetchAll();
        foreach($items as &$it){$o=db()->prepare('SELECT * FROM fill_blank_options WHERE item_id=? ORDER BY sort_order,id');$o->execute([$it['id']]);$it['options']=$o->fetchAll();}
        view('special/fill-quiz-edit',['quiz'=>$quiz,'items'=>$items,'me'=>$u]);exit;
    }

    if($action==='fill-edit'&&$id){
        require_role('teacher','admin');$q=db()->prepare('SELECT f.*,q.course_id,q.title quiz_title FROM fill_blank_items f JOIN quizzes q ON q.id=f.quiz_id WHERE f.id=?');$q->execute([$id]);$item=$q->fetch();if(!$item)exit('Задание не найдено');if(!can_manage_course((int)$item['course_id'],$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$st=trim($_POST['sentence_template']??'');$ca=trim($_POST['correct_answer']??'');$mode=in_array(($_POST['answer_mode']??'choice'),['choice','drag','type'],true)?$_POST['answer_mode']:'choice';$aud=trim($_POST['audio_url']??'');$up=upload_audio('audio_file');if($up)$aud=$up;if($st===''||$ca===''){flash('Заполните предложение.');redirect('fill-edit/'.$id);}db()->prepare('UPDATE fill_blank_items SET sentence_template=?,correct_answer=?,audio_url=?,answer_mode=? WHERE id=?')->execute([$st,$ca,$aud?:null,$mode,$id]);db()->prepare('DELETE FROM fill_blank_options WHERE item_id=?')->execute([$id]);foreach($_POST['option']??[] as $j=>$o){$o=trim($o);if($o!=='')db()->prepare('INSERT INTO fill_blank_options(item_id,option_text,is_correct,sort_order) VALUES(?,?,?,?)')->execute([$id,$o,strtolower($o)===strtolower($ca)?1:0,$j]);}flash('Сохранено.');redirect('course/'.$item['course_id']);}
        $o=db()->prepare('SELECT * FROM fill_blank_options WHERE item_id=? ORDER BY sort_order,id');$o->execute([$id]);$item['options']=$o->fetchAll();view('special/fill-edit',['item'=>$item,'me'=>$u]);exit;
    }
    if($action==='fill-play'&&$id){
        $q=db()->prepare('SELECT f.*,q.course_id,q.title quiz_title FROM fill_blank_items f JOIN quizzes q ON q.id=f.quiz_id WHERE f.id=?');$q->execute([$id]);$item=$q->fetch();if(!$item)exit('Задание не найдено');if(!can_access_course((int)$item['course_id'],$u)||!assignment_allows((int)$item['quiz_id'],'quiz',$u))exit('Forbidden');$o=db()->prepare('SELECT * FROM fill_blank_options WHERE item_id=? ORDER BY RAND()');$o->execute([$id]);view('special/fill-play',['item'=>$item,'options'=>$o->fetchAll(),'me'=>$u]);exit;
    }

    /* ---------- Memory Cards: starts with two, add/remove buttons ---------- */
    if(in_array($action,['memory-create','memory-edit'],true)){
        require_role('teacher','admin');$set=null;$courseId=$id;
        if($action==='memory-edit'){$q=db()->prepare('SELECT * FROM memory_sets WHERE id=?');$q->execute([$id]);$set=$q->fetch();if(!$set)exit('Набор не найден');$courseId=(int)$set['course_id'];$c=db()->prepare('SELECT * FROM memory_cards WHERE set_id=? ORDER BY card_number');$c->execute([$id]);$set['cards']=$c->fetchAll();}
        if(!can_manage_course($courseId,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['title']??'');$slots=array_values(array_unique(array_map('intval',$_POST['card_slot']??[])));$slots=array_values(array_filter($slots,fn($x)=>$x>0));
            if($title===''||count($slots)<2){flash('Введите название и минимум 2 карточки.');redirect($action==='memory-edit'?'memory-edit/'.$id:'memory-create/'.$courseId);}
            if($set){db()->prepare('UPDATE memory_sets SET title=?,card_count=? WHERE id=?')->execute([$title,count($slots),$id]);$sid=$id;}else{require_quiz_type_capacity($u,$courseId,'memory');db()->prepare('INSERT INTO memory_sets(course_id,title,card_count,created_by) VALUES(?,?,?,?)')->execute([$courseId,$title,count($slots),$u['id']]);$sid=(int)db()->lastInsertId();}
            $keep=[];
            foreach($slots as $slot){
                $text=trim($_POST['card_text'][$slot]??'');$audioUrl=trim($_POST['card_audio'][$slot]??'');$img=upload_image('card_image_'.$slot);$aud=upload_audio('card_audio_file_'.$slot);
                $oldq=db()->prepare('SELECT image_path,audio_url FROM memory_cards WHERE set_id=? AND card_number=?');$oldq->execute([$sid,$slot]);$old=$oldq->fetch()?:[];
                if($img)delete_file($old['image_path']??null);else$img=$old['image_path']??null;
                $audio=$aud?:($audioUrl!==''?$audioUrl:($old['audio_url']??null));
                $ex=db()->prepare('SELECT id FROM memory_cards WHERE set_id=? AND card_number=?');$ex->execute([$sid,$slot]);$cid=$ex->fetchColumn();
                if($cid){db()->prepare('UPDATE memory_cards SET text_content=?,image_path=?,audio_url=?,sort_order=? WHERE id=?')->execute([$text,$img,$audio,$slot,$cid]);$keep[]=$cid;}else{db()->prepare('INSERT INTO memory_cards(set_id,card_number,text_content,image_path,audio_url,sort_order) VALUES(?,?,?,?,?,?)')->execute([$sid,$slot,$text,$img,$audio,$slot]);$keep[]=(int)db()->lastInsertId();}
            }
            if($keep){$marks=implode(',',array_fill(0,count($keep),'?'));db()->prepare("DELETE FROM memory_cards WHERE set_id=? AND id NOT IN ($marks)")->execute(array_merge([$sid],$keep));}
            flash('Набор карточек сохранён.');redirect('course/'.$courseId);
        }
        view('special/memory-form',['set'=>$set,'courseId'=>$courseId,'me'=>$u]);exit;
    }
    if($action==='memory-play'&&$id){
        $q=db()->prepare('SELECT m.*,c.id course_id FROM memory_sets m JOIN courses c ON c.id=m.course_id WHERE m.id=?');$q->execute([$id]);$set=$q->fetch();if(!$set||!can_access_course((int)$set['course_id'],$u)||!assignment_allows($id,'memory',$u))exit('Forbidden');$c=db()->prepare('SELECT * FROM memory_cards WHERE set_id=? ORDER BY card_number');$c->execute([$id]);view('special/memory-play',['set'=>$set,'cards'=>$c->fetchAll(),'me'=>$u]);exit;
    }

    /* ---------- Classroom assignments: normal + interactive ---------- */
    if($action==='assignment-create'){
        require_role('teacher','admin');$courseId=(int)($_GET['course_id']??$id);if(!can_manage_course($courseId,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$type=trim($_POST['content_type']??'quiz');$contentId=(int)($_POST['content_id']??0);$students=array_map('intval',$_POST['student_id']??[]);
            if(!$contentId||!$students){flash('Выберите задание и хотя бы одного ученика.');redirect('assignment-create/'.$courseId);}
            db()->prepare('INSERT INTO assignments(course_id,content_type,content_id,assigned_by,available_from,available_until,due_at,attempts_limit) VALUES(?,?,?,?,?,?,?,?)')->execute([$courseId,$type,$contentId,$u['id'],$_POST['available_from']?:null,$_POST['available_until']?:null,$_POST['due_at']?:null,($_POST['attempts_limit']??'')!==''?(int)$_POST['attempts_limit']:null]);
            $aid=(int)db()->lastInsertId();foreach($students as $sid)db()->prepare('INSERT INTO assignment_students(assignment_id,student_id) VALUES(?,?)')->execute([$aid,$sid]);flash('Задание назначено.');redirect('course/'.$courseId);
        }
        $st=db()->prepare("SELECT u.id,u.name,u.email FROM course_members cm JOIN users u ON u.id=cm.student_id WHERE cm.course_id=? AND cm.status='active' ORDER BY u.name");$st->execute([$courseId]);$students=$st->fetchAll();
        $items=[];
        $q=db()->prepare("SELECT id,title,'quiz' content_type FROM quizzes WHERE course_id=? ORDER BY title");$q->execute([$courseId]);$items=array_merge($items,$q->fetchAll());
        $q=db()->prepare("SELECT id,title,'memory' content_type FROM memory_sets WHERE course_id=? ORDER BY title");$q->execute([$courseId]);$items=array_merge($items,$q->fetchAll());
        $q=db()->prepare("SELECT id,title,'matching' content_type FROM matching_sets WHERE course_id=? ORDER BY title");$q->execute([$courseId]);$items=array_merge($items,$q->fetchAll());
        $q=db()->prepare("SELECT id,title,'roulette' content_type FROM roulette_sets WHERE course_id=? ORDER BY title");$q->execute([$courseId]);$items=array_merge($items,$q->fetchAll());
        $labels=['quiz'=>'Квиз / интерактив','memory'=>'Закрытые карточки','matching'=>'Сопоставление','roulette'=>'Рулетка'];
        view('special/assignment-form',['courseId'=>$courseId,'students'=>$students,'assignItems'=>$items,'labels'=>$labels,'me'=>$u]);exit;
    }

    /* ---------- pronunciation helper ---------- */
    if($action==='pronounce'){
        $word=trim($_GET['word']??'');header('Content-Type: application/json; charset=utf-8');echo json_encode(['word'=>$word,'speech'=>'browser-tts'],JSON_UNESCAPED_UNICODE);exit;
    }

    http_response_code(404); exit('Страница не найдена');
} catch(Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="'.e(base_url().'/public/css/style.css').'"><main class="container"><div class="card error-card"><h1>Ошибка приложения</h1><pre>'.e($e->getMessage()).'</pre><a class="btn ghost" href="'.e(url('home')).'">На главную</a></div></main>';
}
