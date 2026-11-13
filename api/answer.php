<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_once __DIR__ . '/../includes/powerups.php';
require_once __DIR__ . '/../includes/scoring.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'error' => 'method'], 405);
if (!csrf_verify()) json_response(['ok' => false, 'error' => 'csrf'], 403);

$p = $_SESSION['participant'] ?? null;
if (!$p) json_response(['ok' => false, 'error' => 'not_joined'], 401);

$stmt = db()->prepare('SELECT * FROM participants WHERE id = ? AND participant_token = ?');
$stmt->execute([$p['id'], $p['token']]);
$participant = $stmt->fetch();
if (!$participant) json_response(['ok' => false, 'error' => 'invalid_session'], 401);
if ($participant['completed_at']) json_response(['ok' => false, 'error' => 'already_completed'], 409);

$stmt = db()->prepare('SELECT * FROM quizzes WHERE id = ?');
$stmt->execute([$participant['quiz_id']]);
$quiz = $stmt->fetch();
if (!$quiz || $quiz['session_status'] !== 'active') json_response(['ok' => false, 'error' => 'not_active'], 409);

$questionId = (int)($_POST['question_id'] ?? 0);
$answerId = isset($_POST['answer_id']) && $_POST['answer_id'] !== '' ? (int)$_POST['answer_id'] : null;
$clientTimeTaken = (int)($_POST['time_taken_ms'] ?? 0);

$questions = get_quiz_questions((int)$quiz['id']);
$totalQuestions = count($questions);

// Each participant's "current question" is simply the next one they haven't
// answered yet — there is no shared, teacher-driven question index anymore.
$expectedIndex = participant_answered_count((int)$participant['id']);
$currentQuestion = $questions[$expectedIndex] ?? null;

if (!$currentQuestion || (int)$currentQuestion['id'] !== $questionId) {
    json_response(['ok' => false, 'error' => 'stale_question'], 409);
}

$timeLimit = (int)($currentQuestion['time_limit'] ?: $quiz['default_time_limit']);
$startedAt = $participant['current_question_started_at'] ? strtotime($participant['current_question_started_at']) : time();
$elapsed = time() - $startedAt;
$graceSeconds = 2;
$expired = $elapsed > ($timeLimit + $graceSeconds);

if ($answerId !== null) {
    $stmt = db()->prepare('SELECT id FROM answers WHERE id = ? AND question_id = ?');
    $stmt->execute([$answerId, $questionId]);
    if (!$stmt->fetch()) json_response(['ok' => false, 'error' => 'invalid_answer'], 400);
}

$result = record_participant_answer($participant, $quiz, $currentQuestion, $answerId, $clientTimeTaken, $expired);
if ($result === null) {
    json_response(['ok' => false, 'error' => 'already_answered'], 409);
}

$showFeedback = (bool)$quiz['show_feedback_immediately'];
json_response([
    'ok' => true,
    'correct' => $showFeedback ? $result['correct'] : null,
    'points_awarded' => $showFeedback ? $result['points_awarded'] : null,
    'expired' => $result['expired'],
]);
