<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (is_teacher_logged_in()) redirect('teacher/index.php');

$error = null;
$notice = flash_get('login_notice');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $result = attempt_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($result['ok']) {
        redirect('teacher/index.php');
    }
    $error = $result['error'];
}

$pageTitle = 'Teacher Login';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card glass-card">
  <h2><?= e(t('teacher_login')) ?></h2>
  <?php if ($notice): ?><p class="alert alert-success"><?= e($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="auth-form">
    <?= csrf_field() ?>
    <label for="username">Username or email</label>
    <input type="text" id="username" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <br>

    <button type="submit" class="btn btn-primary btn-lg">Log In</button>
  </form>
  <p class="auth-alt">New teacher? <a href="<?= e(base_url('register.php')) ?>">Create an account</a></p>
  <!--<p class="auth-demo">Demo login: <code>demo_teacher</code> / <code>Demo@1234</code></p>-->
  <br>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
