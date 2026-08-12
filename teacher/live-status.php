<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_teacher_login();

header('Content-Type: application/json; charset=utf-8');
$quizId = (int)($_GET['id'] ?? 0);
$quiz = get_owned_quiz($quizId, current_teacher_id());
if (!$quiz) json_response(['ok' => false], 404);

$totalQuestions = quiz_question_count($quizId);
$session = get_active_session_for_quiz($quizId);
$participantCount = 0;
$completedCount = 0;
$avgQuestion = 0;

if ($session) {
    $stmt = db()->prepare('SELECT COUNT(*), SUM(completed_at IS NOT NULL) FROM participants WHERE session_id = ?');
    $stmt->execute([$session['id']]);
    [$participantCount, $completedCount] = $stmt->fetch(PDO::FETCH_NUM);
    $participantCount = (int)$participantCount;
    $completedCount = (int)$completedCount;

    if ($participantCount > 0) {
        $stmt = db()->prepare(
            'SELECT AVG(LEAST(answered, ?)) FROM (
                SELECT p.id, COUNT(pa.id) AS answered
                FROM participants p LEFT JOIN participant_answers pa ON pa.participant_id = p.id
                WHERE p.session_id = ? GROUP BY p.id
             ) t'
        );
        $stmt->execute([$totalQuestions, $session['id']]);
        $avgQuestion = round((float)$stmt->fetchColumn(), 1);
    }
}

json_response([
    'ok' => true,
    'session_status' => $quiz['session_status'],
    'total_questions' => $totalQuestions,
    'participant_count' => $participantCount,
    'completed_count' => $completedCount,
    'avg_question' => $avgQuestion,
]);
