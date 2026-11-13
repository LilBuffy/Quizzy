<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_once __DIR__ . '/../includes/powerups.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'error' => 'method'], 405);
if (!csrf_verify()) json_response(['ok' => false, 'error' => 'csrf'], 403);

$p = $_SESSION['participant'] ?? null;
if (!$p) json_response(['ok' => false, 'error' => 'not_joined'], 401);

$stmt = db()->prepare('SELECT * FROM participants WHERE id = ? AND participant_token = ?');
$stmt->execute([$p['id'], $p['token']]);
$participant = $stmt->fetch();
if (!$participant) json_response(['ok' => false, 'error' => 'invalid_session'], 401);

$stmt = db()->prepare('SELECT * FROM quizzes WHERE id = ?');
$stmt->execute([$participant['quiz_id']]);
$quiz = $stmt->fetch();
if (!$quiz || $quiz['session_status'] !== 'active') json_response(['ok' => false, 'error' => 'not_active'], 409);

$code = preg_replace('/[^a-z_]/', '', (string)($_POST['code'] ?? ''));
$result = use_powerup($participant, $quiz, $code);
json_response($result, $result['ok'] ? 200 : 400);
