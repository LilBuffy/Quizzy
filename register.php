<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (is_teacher_logged_in()) redirect('teacher/index.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (!hash_equals($password, $confirm)) {
        $error = 'Passwords do not match.';
    } else {
        $result = register_teacher(
            (string)($_POST['name'] ?? ''),
            (string)($_POST['username'] ?? ''),
            (string)($_POST['email'] ?? ''),
            $password
        );
        if ($result['ok']) {
            flash_set('login_notice', 'Account created. Please log in.');
            redirect('login.php');
        }
        $error = $result['error'];
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card glass-card">
  <h2><?= e(t('teacher_register')) ?></h2>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="auth-form" novalidate>
    <?= csrf_field() ?>
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" required maxlength="100" value="<?= e($_POST['name'] ?? '') ?>">

    <label for="username">Username</label>
    <input type="text" id="username" name="username" required maxlength="50" pattern="[a-zA-Z0-9_]{3,50}" value="<?= e($_POST['username'] ?? '') ?>">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" required maxlength="150" value="<?= e($_POST['email'] ?? '') ?>">

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">

    <label for="confirm_password">Confirm password</label>
    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">

    <br>

    <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
  </form>
  <p class="auth-alt">Already have an account? <a href="<?= e(base_url('login.php')) ?>">Log in</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
