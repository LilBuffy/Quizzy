<?php
if (!isset($pageTitle)) $pageTitle = APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
<title><?= e($pageTitle) ?> · QUIZZY</title>
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>
<script>
(function(){
  var saved = localStorage.getItem('quizzy_theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);
})();
</script>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(base_url('index.php')) ?>">
      <span class="brand-mark">Q</span><span class="brand-name">QUIZZY</span>
    </a>
    <nav class="main-nav">
      <?php if (is_teacher_logged_in()): ?>
        <a href="<?= e(base_url('teacher/index.php')) ?>">Dashboard</a>
        <a href="<?= e(base_url('teacher/quizzes.php')) ?>">My Quizzes</a>
        <a href="<?= e(base_url('logout.php')) ?>">Logout</a>
      <?php else: ?>
        <a href="<?= e(base_url('login.php')) ?>">Teacher Login</a>
      <?php endif; ?>
      <button id="themeToggle" class="icon-btn" type="button" aria-label="Toggle dark or light theme">🌓</button>
      <div class="lang-switch">
        <a href="<?= e(base_url('lang.php?lang=en')) ?>" class="<?= ($_SESSION['lang'] ?? 'en') === 'en' ? 'lang-active' : '' ?>">EN</a>
        <a href="<?= e(base_url('lang.php?lang=fil')) ?>" class="<?= ($_SESSION['lang'] ?? 'en') === 'fil' ? 'lang-active' : '' ?>">FIL</a>
      </div>
    </nav>
  </div>
</header>
<main class="container page-main">
