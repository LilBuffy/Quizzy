<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
$pageTitle = 'Home';
$error = flash_get('join_error');
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-badge" style="display:none;">Live Quiz Platform</div>
  <h1>QUIZZY</h1>
  <p class="hero-tagline"><?= e(t('app_tagline')) ?></p>
</section>

<section class="join-card glass-card">
  <h2><?= e(t('join_quiz')) ?></h2>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <form action="api/join.php" method="post" class="join-form" id="joinForm">
    <?= csrf_field() ?>
    <label for="name"><?= e(t('your_name')) ?></label>
    <input type="text" id="name" name="name" maxlength="60" required autocomplete="off">

    <label for="code"><?= e(t('quiz_code')) ?></label>
    <input type="text" id="code" name="code" maxlength="10" required autocomplete="off" class="code-input" placeholder="ABC123">

    <br>

    <button type="submit" class="btn btn-primary btn-lg"><?= e(t('join_now')) ?></button>

    <br>
    
  </form>
</section>

<br>

<section class="teacher-cta">
  <div class="glass-card teacher-cta-inner">
    <div>
      <h3>Are you a teacher?</h3>
      <p>Create quizzes, run live sessions, and track results.</p>
    </div>
    <div class="teacher-cta-buttons">
      <a class="btn btn-secondary" href="<?= e(base_url('login.php')) ?>">Login</a>
      <a class="btn btn-outline" href="<?= e(base_url('register.php')) ?>">Register</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
