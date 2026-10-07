<!doctype html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e(env('APP_NAME','QuizSpace'))?> — интерактивная образовательная платформа</title><meta name="description" content="QuizSpace — интерактивные тесты, учебные карточки, сопоставления, задания и упражнения для преподавателей и учеников."><meta name="robots" content="index,follow">
<link rel="stylesheet" href="<?=e(base_url())?>/public/css/style.css?v=69">
<style>/* Google Translate */
.goog-te-banner-frame.skiptranslate{display:none!important}body{top:0!important}.goog-te-gadget{font-family:inherit!important;font-size:0!important}.goog-te-gadget .goog-te-combo{font:600 12px Inter,Segoe UI,Arial,sans-serif!important;color:#303448;background:#f0f1f6;border:1px solid #e1e2ea;border-radius:10px;padding:8px 30px 8px 10px;outline:none;cursor:pointer;max-width:170px}.goog-logo-link,.goog-te-gadget span{display:none!important}.translate-wrap{display:flex;align-items:center;gap:7px;margin-left:auto}.translate-label{font-size:12px;color:#777e94}.translate-icon{font-size:14px}</style>
<script>function googleTranslateElementInit(){new google.translate.TranslateElement({pageLanguage:'uk',includedLanguages:'uk,en,ru,de,pl,ro,bg,tr,fr,es,it,pt,cs,sk,hu',layout:google.translate.TranslateElement.InlineLayout.SIMPLE,autoDisplay:false},'google_translate_element');}</script>
<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" defer></script>
<script>(function(){try{var m=localStorage.getItem('quizspace-theme');if(m==='dark')document.documentElement.dataset.theme='dark'}catch(e){}})();</script></head>
<body>
<header class="top">
  <a class="brand" href="<?=url('home')?>"><span class="logo">Q</span><span>QuizSpace</span></a>
  <nav class="main-nav">
    <?php if($me): ?>
      <a href="<?=url('home')?>">Головна</a>
      <?php if($me['role']==='teacher'): ?><a href="<?=url('teacher/courses')?>">Мої курси</a><a href="<?=url('subscribe')?>">⭐ Premium</a><?php endif; ?>
      <?php if($me['role']==='admin'): ?><a href="<?=url('admin/teachers')?>">Вчителі</a><a href="<?=url('admin/premium')?>">⭐ Premium</a><?php endif; ?>
    <?php endif; ?>
  </nav>
  <button type="button" class="theme-toggle" id="themeToggle" aria-label="Переключить тему"><span id="themeIcon">☀️</span><span>Тема</span></button>
  <div class="translate-wrap"><span class="translate-icon">🌐</span><span class="translate-label">Мова</span><div id="google_translate_element"></div></div>
  <?php if($me): ?>
    <div class="user-menu"><span><?=e($me['name'])?></span><span class="pill"><?=e($me['role'])?></span><a class="btn ghost small" href="<?=url('logout')?>">Вийти</a></div>
  <?php endif; ?>
</header><script>(function(){var r=document.documentElement,b=document.getElementById('themeToggle'),i=document.getElementById('themeIcon');function set(m){r.dataset.theme=m;if(i)i.textContent=m==='dark'?'🌙':'☀️';try{localStorage.setItem('quizspace-theme',m)}catch(e){}}var m='light';try{m=localStorage.getItem('quizspace-theme')||'light'}catch(e){}set(m);if(b)b.addEventListener('click',function(){set(r.dataset.theme==='dark'?'light':'dark')});})();</script>


<main class="container">
<?php if($m=flash()): ?><div class="flash"><?=e($m)?></div><?php endif; ?>
