<?php
require_once __DIR__ . '/database.php';

function get_owned_quiz(int $quizId, int $teacherId): ?array {
    $stmt = db()->prepare('SELECT * FROM quizzes WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$quizId, $teacherId]);
    $quiz = $stmt->fetch();
    return $quiz ?: null;
}

function get_quiz_by_code(string $code): ?array {
    $stmt = db()->prepare('SELECT * FROM quizzes WHERE code = ?');
    $stmt->execute([strtoupper(trim($code))]);
    $quiz = $stmt->fetch();
    return $quiz ?: null;
}

function get_quiz_questions(int $quizId): array {
    $stmt = db()->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY order_index ASC, id ASC');
    $stmt->execute([$quizId]);
    return $stmt->fetchAll();
}

function get_question_answers(int $questionId, bool $includeCorrectFlag = true): array {
    $sql = $includeCorrectFlag
        ? 'SELECT id, answer_text, is_correct, order_index FROM answers WHERE question_id = ? ORDER BY order_index ASC, id ASC'
        : 'SELECT id, answer_text, order_index FROM answers WHERE question_id = ? ORDER BY order_index ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute([$questionId]);
    return $stmt->fetchAll();
}

function quiz_question_count(int $quizId): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id = ?');
    $stmt->execute([$quizId]);
    return (int)$stmt->fetchColumn();
}

function enabled_powerups_for_quiz(array $quiz): array {
    if (empty($quiz['powerups_enabled'])) return [];
    return array_filter(array_map('trim', explode(',', $quiz['powerups_enabled'])));
}

function participant_answered_count(int $participantId): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM participant_answers WHERE participant_id = ?');
    $stmt->execute([$participantId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Creates the temporary session row and flips the quiz definition into
 * "open for joining" state. Only the owning teacher may call this.
 */
function open_quiz_for_joining(array $quiz): int {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO quiz_sessions (quiz_id, status) VALUES (?, "waiting")');
    $stmt->execute([$quiz['id']]);
    $sessionId = (int)$pdo->lastInsertId();

    $upd = $pdo->prepare(
        "UPDATE quizzes SET status = 'open', session_status = 'waiting',
         session_started_at = NULL, session_closed_at = NULL WHERE id = ?"
    );
    $upd->execute([$quiz['id']]);
    return $sessionId;
}

function get_active_session_for_quiz(int $quizId): ?array {
    $stmt = db()->prepare(
        "SELECT * FROM quiz_sessions WHERE quiz_id = ? AND status IN ('waiting','active') ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$quizId]);
    $s = $stmt->fetch();
    return $s ?: null;
}

function start_quiz_session(array $quiz, array $session): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE quiz_sessions SET status='active', started_at = NOW() WHERE id = ?")->execute([$session['id']]);
        $pdo->prepare(
            "UPDATE quizzes SET status='in_progress', session_status='active', session_started_at = NOW() WHERE id = ?"
        )->execute([$quiz['id']]);
        // Every participant starts question 1 at the same moment; from then on
        // each participant advances individually as soon as they answer.
        $pdo->prepare(
            "UPDATE participants SET current_question_started_at = NOW() WHERE session_id = ? AND completed_at IS NULL"
        )->execute([$session['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function close_quiz_session(array $quiz): void {
    $pdo = db();
    $session = get_active_session_for_quiz($quiz['id']);
    $pdo->beginTransaction();
    try {
        if ($session) {
            $pdo->prepare("UPDATE quiz_sessions SET status='completed', closed_at = NOW() WHERE id = ?")->execute([$session['id']]);
            $pdo->prepare("UPDATE participants SET completed_at = NOW() WHERE session_id = ? AND completed_at IS NULL")->execute([$session['id']]);
        }
        $pdo->prepare("UPDATE quizzes SET status='completed', session_status='completed', session_closed_at = NOW() WHERE id = ?")->execute([$quiz['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
