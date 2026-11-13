<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_once __DIR__ . '/../includes/powerups.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../index.php');

if (!csrf_verify()) {
    flash_set('join_error', 'Your session expired. Please try again.');
    redirect('../index.php');
}

$name = trim((string)($_POST['name'] ?? ''));
$code = strtoupper(trim((string)($_POST['code'] ?? '')));

if ($name === '' || mb_strlen($name) > 60) {
    flash_set('join_error', 'Please enter your name.');
    redirect('../index.php');
}
if (!preg_match('/^[A-Z0-9]{4,10}$/', $code)) {
    flash_set('join_error', 'Invalid quiz code.');
    redirect('../index.php');
}

$ipHash = client_ip_hash();
$rateKey = 'join_attempts';
$_SESSION[$rateKey] = array_filter($_SESSION[$rateKey] ?? [], fn($t) => $t > time() - 60);
if (count($_SESSION[$rateKey]) >= 10) {
    log_security_event('join_rate_limited', null, $ipHash);
    flash_set('join_error', 'Too many attempts. Please wait a moment and try again.');
    redirect('../index.php');
}

$quiz = get_quiz_by_code($code);
if (!$quiz) {
    $_SESSION[$rateKey][] = time();
    log_security_event('join_invalid_code', null, $code);
    flash_set('join_error', 'No quiz found with that code.');
    redirect('../index.php');
}

if ($quiz['status'] !== 'open') {
    flash_set('join_error', 'This quiz is not currently open for joining.');
    redirect('../index.php');
}

$session = get_active_session_for_quiz((int)$quiz['id']);
if (!$session || $session['status'] !== 'waiting') {
    flash_set('join_error', 'This quiz has already started or is closed.');
    redirect('../index.php');
}

$pdo = db();
try {
    $token = generate_participant_token();
    $stmt = $pdo->prepare(
        'INSERT INTO participants (session_id, quiz_id, name, participant_token, ip_hash) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([$session['id'], $quiz['id'], $name, $token, $ipHash]);
    $participantId = (int)$pdo->lastInsertId();
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        flash_set('join_error', 'That name is already taken in this quiz. Try another.');
        redirect('../index.php');
    }
    error_log('join failed: ' . $e->getMessage());
    flash_set('join_error', 'Could not join the quiz. Please try again.');
    redirect('../index.php');
}

grant_default_powerups($participantId, $quiz);

session_regenerate_id(true);
$_SESSION['participant'] = [
    'id' => $participantId,
    'token' => $token,
    'quiz_id' => (int)$quiz['id'],
    'session_id' => (int)$session['id'],
];

redirect('../quiz.php');
