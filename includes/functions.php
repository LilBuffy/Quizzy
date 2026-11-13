<?php
require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('quizzy_sid');
    session_start();
}

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function is_teacher_logged_in(): bool {
    return !empty($_SESSION['teacher_id']);
}

function require_teacher_login(): void {
    if (!is_teacher_logged_in()) {
        redirect(base_url('login.php'));
    }
}

function current_teacher_id(): ?int {
    return $_SESSION['teacher_id'] ?? null;
}

function base_url(string $path = ''): string {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $root = preg_replace('#/(teacher|api)$#', '', $scriptDir);
    if ($root === '/' || $root === '') $root = '';
    return $root . '/' . ltrim($path, '/');
}

function flash_set(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string {
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function generate_quiz_code(int $length = 6): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I ambiguity
    $pdo = db();
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM quizzes WHERE code = ?');
        $stmt->execute([$code]);
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists);
    return $code;
}

function generate_participant_token(): string {
    return bin2hex(random_bytes(32));
}

function client_ip_hash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', $ip);
}

function log_security_event(string $eventType, ?int $teacherId = null, string $details = ''): void {
    try {
        $stmt = db()->prepare('INSERT INTO security_logs (event_type, teacher_id, ip_hash, details) VALUES (?,?,?,?)');
        $stmt->execute([$eventType, $teacherId, client_ip_hash(), substr($details, 0, 255)]);
    } catch (Throwable $e) {
        error_log('security log failed: ' . $e->getMessage());
    }
}

function maybe_run_cleanup(): void {
    if (mt_rand() / mt_getrandmax() < CLEANUP_TRIGGER_PROBABILITY) {
        require_once __DIR__ . '/cleanup.php';
        run_cleanup();
    }
}

function t(string $key): string {
    static $strings = null;
    if ($strings === null) {
        $lang = $_SESSION['lang'] ?? 'en';
        if (!in_array($lang, ['en', 'fil'], true)) $lang = 'en';
        $strings = require __DIR__ . '/../assets/lang/' . $lang . '.php';
    }
    return $strings[$key] ?? $key;
}

maybe_run_cleanup();
