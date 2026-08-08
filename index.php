<?php
require __DIR__.'/config/config.php';

$path=trim($_GET['url']??'','/');
$parts=$path===''?[]:explode('/',$path);
$action=$parts[0]??'home';
$id=isset($parts[1])&&ctype_digit($parts[1])?(int)$parts[1]:null;
$sub=$parts[2]??null;
// Routes with a nested resource id, e.g. admin/teacher/2.
$nestedId=isset($parts[2])&&ctype_digit($parts[2])?(int)$parts[2]:null;

try {
    if ($action==='logout') { session_destroy(); redirect('login'); }

    if (in_array($action,['login','register','forgot','reset'],true)) {
        if ($action==='login') {
            if ($_SERVER['REQUEST_METHOD']==='POST') {
                check_csrf();
                $s=db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
                $s->execute([strtolower(trim($_POST['email']??''))]);
                $u=$s->fetch();
                if ($u && password_verify($_POST['password']??'',$u['password_hash'])) {
                    session_regenerate_id(true); $_SESSION['user_id']=$u['id']; redirect('home');
                }
                flash('Неверный email или пароль.');
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
                    $_SESSION['dev_reset_code']=$sent?null:$code;
                    flash($sent?'Код отправлен на email.':'XAMPP mail() не настроен — для локальной разработки код показан на экране.');
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

    $u=require_login();

    if ($action==='home') {
        if($u['role']==='admin') redirect('admin/teachers');
        if($u['role']==='teacher') redirect('teacher/courses');
        $s=db()->prepare("SELECT c.*,u.name teacher_name FROM courses c JOIN course_members cm ON cm.course_id=c.id JOIN users u ON u.id=c.teacher_id WHERE cm.student_id=? AND cm.status='active' ORDER BY c.id DESC");
        $s->execute([$u['id']]); view('student/home',['courses'=>$s->fetchAll(),'me'=>$u]); exit;
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
        $s=db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM quizzes q WHERE q.course_id=c.id) quiz_count, (SELECT COUNT(*) FROM questions qq JOIN quizzes q2 ON q2.id=qq.quiz_id WHERE q2.course_id=c.id) question_count, (SELECT COUNT(*) FROM bank_questions b WHERE b.course_id=c.id) bank_count, (SELECT COUNT(*) FROM flashcards f WHERE f.course_id=c.id) card_count, (SELECT COUNT(*) FROM matching_sets m WHERE m.course_id=c.id) matching_count FROM courses c WHERE c.teacher_id=? ORDER BY c.id DESC'); $s->execute([$id]);
        view('admin/teacher',['teacher'=>$teacher,'courses'=>$s->fetchAll(),'me'=>$u]); exit;
    }

    if ($action==='teacher' && ($parts[1]??'')==='courses') {
        require_role('teacher','admin');
        if($u['role']==='admin') $courses=db()->query("SELECT c.*,u.name teacher_name,(SELECT COUNT(*) FROM course_members cm WHERE cm.course_id=c.id AND cm.status='active') students FROM courses c JOIN users u ON u.id=c.teacher_id ORDER BY c.id DESC")->fetchAll();
        else { $s=db()->prepare("SELECT c.*,u.name teacher_name,(SELECT COUNT(*) FROM course_members cm WHERE cm.course_id=c.id AND cm.status='active') students FROM courses c JOIN users u ON u.id=c.teacher_id WHERE c.teacher_id=? ORDER BY c.id DESC");$s->execute([$u['id']]);$courses=$s->fetchAll(); }
        view('teacher/courses',['courses'=>$courses,'me'=>$u]); exit;
    }

    if ($action==='course' && $id) {
        if(!can_access_course($id,$u)) { http_response_code(403); exit('Нет доступа к курсу'); }
        $s=db()->prepare('SELECT c.*,u.name teacher_name,u.email teacher_email FROM courses c JOIN users u ON u.id=c.teacher_id WHERE c.id=?');$s->execute([$id]);$course=$s->fetch();if(!$course)exit('Курс не найден');
        $s=db()->prepare('SELECT q.*,COUNT(qq.id) question_count FROM quizzes q LEFT JOIN questions qq ON qq.quiz_id=q.id WHERE q.course_id=? GROUP BY q.id ORDER BY q.id DESC');$s->execute([$id]);$quizzes=$s->fetchAll();
        $s=db()->prepare('SELECT * FROM bank_questions WHERE course_id=? ORDER BY id DESC');$s->execute([$id]);$bank=$s->fetchAll();
        $s=db()->prepare('SELECT * FROM flashcards WHERE course_id=? ORDER BY id DESC');$s->execute([$id]);$cards=$s->fetchAll();
        $s=db()->prepare('SELECT ms.*,COUNT(mp.id) pair_count FROM matching_sets ms LEFT JOIN matching_pairs mp ON mp.set_id=ms.id WHERE ms.course_id=? GROUP BY ms.id ORDER BY ms.id DESC');$s->execute([$id]);$matching=$s->fetchAll();
        $s=db()->prepare('SELECT rs.*,COUNT(rg.id) segment_count FROM roulette_sets rs LEFT JOIN roulette_segments rg ON rg.roulette_id=rs.id WHERE rs.course_id=? GROUP BY rs.id ORDER BY rs.id DESC');$s->execute([$id]);$roulettes=$s->fetchAll();
        view('course/show',['course'=>$course,'quizzes'=>$quizzes,'bank'=>$bank,'cards'=>$cards,'matching'=>$matching,'roulettes'=>$roulettes,'me'=>$u]); exit;
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
        require_role('teacher','admin');
        check_csrf();
        $type=$sub;
        $map=[
            'course'=>['courses','id'],
            'quiz'=>['quizzes','id'],
            'question'=>['bank_questions','id'],
            'flashcard'=>['flashcards','id'],
            'matching'=>['matching_sets','id'],
            'matching-pair'=>['matching_pairs','id'],
            'roulette'=>['roulette_sets','id']
        ];
        if(!isset($map[$type])) exit('Неизвестный тип удаления');
        [$table,$pk]=$map[$type];

        // Determine the parent course BEFORE deleting the record.
        $courseId=null;
        if($type==='course') {
            $s=db()->prepare('SELECT id FROM courses WHERE id=?');
            $s->execute([$id]); $courseId=$s->fetchColumn();
        } elseif($type==='quiz') {
            $s=db()->prepare('SELECT course_id FROM quizzes WHERE id=?'); $s->execute([$id]); $courseId=$s->fetchColumn();
        } elseif($type==='question') {
            $s=db()->prepare('SELECT course_id FROM bank_questions WHERE id=?'); $s->execute([$id]); $courseId=$s->fetchColumn();
        } elseif($type==='flashcard') {
            $s=db()->prepare('SELECT course_id FROM flashcards WHERE id=?'); $s->execute([$id]); $courseId=$s->fetchColumn();
        } elseif($type==='matching') {
            $s=db()->prepare('SELECT course_id FROM matching_sets WHERE id=?'); $s->execute([$id]); $courseId=$s->fetchColumn();
        } elseif($type==='matching-pair') {
            $s=db()->prepare('SELECT m.course_id FROM matching_pairs p JOIN matching_sets m ON m.id=p.set_id WHERE p.id=?'); $s->execute([$id]); $courseId=$s->fetchColumn();
        } elseif($type==='roulette') {
            $s=db()->prepare('SELECT course_id FROM roulette_sets WHERE id=?'); $s->execute([$id]); $courseId=$s->fetchColumn();
        }

        // Verify ownership before touching any files.
        if($type==='course') $ok=can_manage_course($id,$u);
        else {
            $queryMap=[
                'quiz'=>'SELECT c.teacher_id FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.id=?',
                'question'=>'SELECT c.teacher_id FROM bank_questions b JOIN courses c ON c.id=b.course_id WHERE b.id=?',
                'flashcard'=>'SELECT c.teacher_id FROM flashcards f JOIN courses c ON c.id=f.course_id WHERE f.id=?',
                'matching'=>'SELECT c.teacher_id FROM matching_sets m JOIN courses c ON c.id=m.course_id WHERE m.id=?',
                'matching-pair'=>'SELECT c.teacher_id FROM matching_pairs p JOIN matching_sets m ON m.id=p.set_id JOIN courses c ON c.id=m.course_id WHERE p.id=?',
                'roulette'=>'SELECT c.teacher_id FROM roulette_sets r JOIN courses c ON c.id=r.course_id WHERE r.id=?'
            ];
            $s=db()->prepare($queryMap[$type]); $s->execute([$id]); $r=$s->fetch();
            $ok=$r && ($u['role']==='admin'||(int)$r['teacher_id']===(int)$u['id']);
        }
        if(!$ok) { http_response_code(403); exit('Forbidden'); }

        // Remove uploaded files that would otherwise become orphaned after FK cascade.
        if($type==='course') {
            $queries=[
                'SELECT image_path FROM questions q JOIN quizzes z ON z.id=q.quiz_id WHERE z.course_id=?',
                'SELECT image_path FROM bank_questions WHERE course_id=?',
                'SELECT front_image_path,back_image_path FROM flashcards WHERE course_id=?',
                'SELECT p.prompt_image_path,p.answer_image_path FROM matching_pairs p JOIN matching_sets m ON m.id=p.set_id WHERE m.course_id=?',
                'SELECT s.image_path FROM roulette_segments s JOIN roulette_sets r ON r.id=s.roulette_id WHERE r.course_id=?'
            ];
            foreach($queries as $sql){
                $s=db()->prepare($sql);$s->execute([$id]);
                foreach($s->fetchAll() as $row) foreach($row as $path) delete_file($path);
            }
        } elseif($type==='quiz') {
            $s=db()->prepare('SELECT image_path FROM questions WHERE quiz_id=?');$s->execute([$id]);
            foreach($s->fetchAll() as $row) delete_file($row['image_path']);
        } elseif($type==='matching') {
            $s=db()->prepare('SELECT prompt_image_path,answer_image_path FROM matching_pairs WHERE set_id=?');$s->execute([$id]);
            foreach($s->fetchAll() as $row) foreach($row as $path) delete_file($path);
        } elseif($type==='roulette') {
            $s=db()->prepare('SELECT image_path FROM roulette_segments WHERE roulette_id=?');$s->execute([$id]);
            foreach($s->fetchAll() as $row) delete_file($row['image_path']);
        } else {
            $fields=[
                'question'=>'image_path',
                'flashcard'=>'front_image_path,back_image_path',
                'matching-pair'=>'prompt_image_path,answer_image_path'
            ];
            if(isset($fields[$type])){
                $s=db()->prepare("SELECT {$fields[$type]} FROM {$table} WHERE {$pk}=?");$s->execute([$id]);
                if($old=$s->fetch()) foreach($old as $path) delete_file($path);
            }
        }

        db()->prepare("DELETE FROM {$table} WHERE {$pk}=?")->execute([$id]);

        if($type==='course') $back=$u['role']==='admin' ? 'admin/teachers' : 'teacher/courses';
        else $back=$courseId ? 'course/'.(int)$courseId : 'home';
        flash('Удалено.');
        redirect($back);
    }

    if ($action==='quiz-create' || $action==='quiz-edit') {
        require_role('teacher','admin'); $quiz=null;$courseId=$id;
        if($action==='quiz-edit'){ $s=db()->prepare('SELECT * FROM quizzes WHERE id=?');$s->execute([$id]);$quiz=$s->fetch();if(!$quiz)exit('Тест не найден');$courseId=(int)$quiz['course_id'];}
        if(!can_manage_course($courseId,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();$title=trim($_POST['title']??'');if(!$title){flash('Введите название теста.');redirect($action==='quiz-edit'?'quiz-edit/'.$id:'quiz-create/'.$courseId);}
            if($quiz){db()->prepare('UPDATE quizzes SET title=?,description=? WHERE id=?')->execute([$title,trim($_POST['description']??''),$id]);$qid=$id;}
            else{db()->prepare('INSERT INTO quizzes(course_id,title,description) VALUES(?,?,?)')->execute([$courseId,$title,trim($_POST['description']??'')]);$qid=(int)db()->lastInsertId();}
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
        $s=db()->prepare('SELECT q.*,qu.course_id,qu.title FROM quizzes qu JOIN questions q ON q.quiz_id=qu.id WHERE qu.id=? ORDER BY q.sort_order,q.id');$s->execute([$id]);$questions=$s->fetchAll();if(!$questions)exit('Тест пуст');$courseId=(int)$questions[0]['course_id'];if(!can_access_course($courseId,$u))exit('Forbidden');
        foreach($questions as &$q){$a=db()->prepare('SELECT * FROM quiz_answers WHERE question_id=? ORDER BY RAND()');$a->execute([$q['id']]);$q['answers']=$a->fetchAll();}
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$score=0;$attempt=db()->prepare('INSERT INTO attempts(student_id,course_id,type,material_id,total) VALUES(?,?,?,?,?)');$attempt->execute([$u['id'],$courseId,'quiz',$id,count($questions)]);$aid=(int)db()->lastInsertId();foreach($questions as $q){$given=$_POST['q'][$q['id']]??'';$correct='';foreach($q['answers'] as $a)if($a['is_correct'])$correct=$a['answer_text'];$ok=hash_equals($correct,$given);if($ok)$score++;db()->prepare('INSERT INTO result_answers(attempt_id,question_id,prompt,given_answer,correct_answer,is_correct) VALUES(?,?,?,?,?,?)')->execute([$aid,$q['id'],$q['question_text'],$given,$correct,$ok]);}db()->prepare('UPDATE attempts SET score=? WHERE id=?')->execute([$score,$aid]);view('quiz/result',['score'=>$score,'total'=>count($questions),'me'=>$u]);exit;}
        view('quiz/play',['quizTitle'=>$questions[0]['title'],'questions'=>$questions,'me'=>$u]);exit;
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
        if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$score=0;$attempt=db()->prepare('INSERT INTO attempts(student_id,course_id,type,material_id,total) VALUES(?,?,?,?,?)');$attempt->execute([$u['id'],$id,'bank',$id,count($bank)]);$aid=(int)db()->lastInsertId();foreach($bank as $q){$given=$_POST['q'][$q['id']]??'';$ok=hash_equals($q['correct_answer'],$given);if($ok)$score++;db()->prepare('INSERT INTO result_answers(attempt_id,bank_question_id,prompt,given_answer,correct_answer,is_correct) VALUES(?,?,?,?,?,?)')->execute([$aid,$q['id'],$q['question_text'],$given,$q['correct_answer'],$ok]);}db()->prepare('UPDATE attempts SET score=? WHERE id=?')->execute([$score,$aid]);view('quiz/result',['score'=>$score,'total'=>count($bank),'me'=>$u]);exit;}
        view('bank/play',['courseId'=>$id,'questions'=>$bank,'me'=>$u]);exit;
    }

    if($action==='flashcards'&&$id){
        require_role('teacher','admin');if(!can_manage_course($id,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf();
            foreach($_POST['front']??[] as $i=>$front){
                $front=trim($front);$back=trim($_POST['back'][$i]??'');
                if($front==='')continue;
                $fi=upload_image('front_image_'.$i);$bi=upload_image('back_image_'.$i);
                db()->prepare('INSERT INTO flashcards(course_id,front_text,back_text,front_image_path,back_image_path) VALUES(?,?,?,?,?)')
                  ->execute([$id,$front,$back,$fi,$bi]);
            }
            flash('Картки додані.');redirect('course/'.$id);
        }
        view('flashcards/form',['courseId'=>$id,'me'=>$u]);exit;
    }
    if($action==='flashcard-edit'&&$id){
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
        $s=db()->prepare('SELECT ms.*,c.id course_id FROM matching_sets ms JOIN courses c ON c.id=ms.course_id WHERE ms.id=?');$s->execute([$id]);$set=$s->fetch();if(!$set||!can_access_course((int)$set['course_id'],$u))exit('Forbidden');$p=db()->prepare('SELECT * FROM matching_pairs WHERE set_id=? ORDER BY id');$p->execute([$id]);view('matching/play',['set'=>$set,'pairs'=>$p->fetchAll(),'me'=>$u]);exit;
    }

    if($action==='roulette'&&$id){
        require_role('teacher','admin'); if(!can_manage_course($id,$u))exit('Forbidden');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_csrf(); $title=trim($_POST['title']??'Рулетка');
            if(!$title){flash('Введіть назву рулетки.');redirect('roulette/'.$id);}
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

    http_response_code(404); exit('Страница не найдена');
} catch(Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="'.e(base_url().'/public/css/style.css').'"><main class="container"><div class="card error-card"><h1>Ошибка приложения</h1><pre>'.e($e->getMessage()).'</pre><a class="btn ghost" href="'.e(url('home')).'">На главную</a></div></main>';
}
