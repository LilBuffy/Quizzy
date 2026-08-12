<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_once __DIR__ . '/../includes/powerups.php';
require_once __DIR__ . '/../includes/scoring.php';

header('Content-Type: application/json; charset=utf-8');

$p = $_SESSION['participant'] ?? null;
if (!$p) json_response(['ok' => false, 'error' => 'not_joined'], 401);

$stmt = db()->prepare('SELECT * FROM participants WHERE id = ? AND participant_token = ?');
$stmt->execute([$p['id'], $p['token']]);
$participant = $stmt->fetch();
if (!$participant) json_response(['ok' => false, 'error' => 'invalid_session'], 401);

$stmt = db()->prepare('SELECT * FROM quizzes WHERE id = ?');
$stmt->execute([$participant['quiz_id']]);
$quiz = $stmt->fetch();
if (!$quiz) json_response(['ok' => false, 'error' => 'quiz_missing'], 404);

if ($participant['completed_at'] || $quiz['session_status'] === 'completed') {
    json_response(['ok' => true, 'status' => 'completed']);
}

if ($quiz['session_status'] !== 'active') {
    json_response(['ok' => true, 'status' => 'waiting']);
}

$questions = get_quiz_questions((int)$quiz['id']);
$totalQuestions = count($questions);

// Each participant progresses at their own pace: their current question is
// simply the next one they haven't answered yet. If time has run out on it
// without a submission, auto-skip it (0 points) and move them along —
// nobody waits on a teacher to click "next".
$question = null;
$timeLimit = 0;
$elapsed = 0;

while (true) {
    $index = participant_answered_count((int)$participant['id']);
    if ($index >= $totalQuestions) {
        db()->prepare('UPDATE participants SET completed_at = NOW() WHERE id = ? AND completed_at IS NULL')->execute([$participant['id']]);
        json_response(['ok' => true, 'status' => 'completed']);
    }

    $question = $questions[$index];
    $timeLimit = (int)($question['time_limit'] ?: $quiz['default_time_limit']);
    $startedAt = $participant['current_question_started_at'] ? strtotime($participant['current_question_started_at']) : time();
    $elapsed = time() - $startedAt;
    $graceSeconds = 2;

    if ($elapsed > ($timeLimit + $graceSeconds)) {
        record_participant_answer($participant, $quiz, $question, null, $timeLimit * 1000, true);
        $stmt = db()->prepare('SELECT * FROM participants WHERE id = ?');
        $stmt->execute([$participant['id']]);
        $participant = $stmt->fetch();
        continue;
    }

    break;
}

$index = participant_answered_count((int)$participant['id']);
$remaining = max(0, $timeLimit - $elapsed);

$powerups = get_participant_powerups((int)$participant['id']);
$powerupList = [];
foreach (enabled_powerups_for_quiz($quiz) as $code) {
    if (!isset(POWERUP_DEFS[$code])) continue;
    $row = $powerups[$code] ?? null;
    $powerupList[] = [
        'code' => $code,
        'name' => POWERUP_DEFS[$code]['name'],
        'desc' => POWERUP_DEFS[$code]['desc'],
        'remaining' => $row ? (int)$row['uses_remaining'] : 0,
    ];
}

$answers = get_question_answers((int)$question['id'], false);
$fiftyRow = $powerups['fifty_fifty'] ?? null;
if ($fiftyRow && (int)$fiftyRow['active_for_next_question'] === 1) {
    $correctStmt = db()->prepare('SELECT id FROM answers WHERE question_id = ? AND is_correct = 1 LIMIT 1');
    $correctStmt->execute([$question['id']]);
    $correctId = (int)$correctStmt->fetchColumn();
    $wrong = array_values(array_filter($answers, fn($a) => (int)$a['id'] !== $correctId));
    shuffle($wrong);
    $toRemove = array_slice($wrong, 0, max(0, count($wrong) - 1));
    $removeIds = array_column($toRemove, 'id');
    $answers = array_values(array_filter($answers, fn($a) => !in_array($a['id'], $removeIds, true)));
    consume_active_flag((int)$participant['id'], 'fifty_fifty');
}

$leaderboard = null;
if ($quiz['show_leaderboard_during']) {
    $lb = db()->prepare('SELECT name, score FROM participants WHERE session_id = ? ORDER BY score DESC, joined_at ASC LIMIT 10');
    $lb->execute([$participant['session_id']]);
    $leaderboard = $lb->fetchAll();
}

json_response([
    'ok' => true,
    'status' => 'active',
    'already_answered' => false,
    'score' => (int)$participant['score'],
    'question' => [
        'id' => (int)$question['id'],
        'index' => $index,
        'total' => $totalQuestions,
        'text' => $question['question_text'],
        'type' => $question['type'],
        'points' => (int)$question['points'],
        'time_remaining' => $remaining,
        'time_limit' => $timeLimit,
        'answers' => array_map(fn($a) => ['id' => (int)$a['id'], 'text' => $a['answer_text']], $answers),
    ],
    'powerups' => $powerupList,
    'leaderboard' => $leaderboard,
]);
