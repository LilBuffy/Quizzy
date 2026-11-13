<?php
require_once __DIR__ . '/functions.php';

function register_teacher(string $name, string $username, string $email, string $password): array {
    $name = trim($name);
    $username = trim($username);
    $email = trim($email);

    if ($name === '' || mb_strlen($name) > 100) return ['ok' => false, 'error' => 'Please enter a valid name.'];
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) return ['ok' => false, 'error' => 'Username must be 3-50 characters (letters, numbers, underscore only).'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    if (strlen($password) < 8) return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];

    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM teachers WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    if ((int)$stmt->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'That username or email is already registered.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO teachers (name, username, email, password_hash) VALUES (?,?,?,?)');
    $stmt->execute([$name, $username, $email, $hash]);
    return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
}

function attempt_login(string $username, string $password): array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $teacher = $stmt->fetch();

    $generic = 'Invalid username or password.';

    if (!$teacher) {
        log_security_event('login_failed_unknown_user', null, $username);
        usleep(random_int(100000, 300000));
        return ['ok' => false, 'error' => $generic];
    }

    if (!empty($teacher['locked_until']) && strtotime($teacher['locked_until']) > time()) {
        log_security_event('login_blocked_locked', (int)$teacher['id']);
        return ['ok' => false, 'error' => 'Too many failed attempts. Please try again later.'];
    }

    if (!password_verify($password, $teacher['password_hash'])) {
        $attempts = (int)$teacher['failed_login_attempts'] + 1;
        $lockUntil = null;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
        }
        $stmt = $pdo->prepare('UPDATE teachers SET failed_login_attempts = ?, locked_until = ? WHERE id = ?');
        $stmt->execute([$attempts, $lockUntil, $teacher['id']]);
        log_security_event('login_failed_bad_password', (int)$teacher['id']);
        return ['ok' => false, 'error' => $generic];
    }

    $stmt = $pdo->prepare('UPDATE teachers SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
    $stmt->execute([$teacher['id']]);

    session_regenerate_id(true);
    $_SESSION['teacher_id'] = (int)$teacher['id'];
    $_SESSION['teacher_name'] = $teacher['name'];
    log_security_event('login_success', (int)$teacher['id']);

    return ['ok' => true];
}

function logout_teacher(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path']);
    }
    session_destroy();
}
