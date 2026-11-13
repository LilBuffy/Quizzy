<?php
require_once __DIR__ . '/database.php';

/**
 * Removes temporary quiz SESSION data older than the retention window.
 * Quiz definitions (quizzes/questions/answers) and teacher accounts are never touched here.
 */
function run_cleanup(): int {
    $pdo = db();
    $hours = defined('SESSION_DATA_LIFETIME_HOURS') ? SESSION_DATA_LIFETIME_HOURS : 24;

    $stmt = $pdo->prepare(
        "SELECT id FROM quiz_sessions
         WHERE (closed_at IS NOT NULL AND closed_at < DATE_SUB(NOW(), INTERVAL ? HOUR))
            OR (closed_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR))"
    );
    $stmt->execute([$hours, $hours]);
    $sessionIds = array_column($stmt->fetchAll(), 'id');

    if (empty($sessionIds)) {
        return 0;
    }

    $pdo->beginTransaction();
    try {
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        // participants/participant_answers/participant_powerups cascade via FK on delete
        $del = $pdo->prepare("DELETE FROM quiz_sessions WHERE id IN ($placeholders)");
        $del->execute($sessionIds);

        // Reset quiz definitions that were tied to an expired session back to a clean state
        $reset = $pdo->prepare(
            "UPDATE quizzes SET session_status = 'idle'
             WHERE session_status = 'completed' AND session_closed_at < DATE_SUB(NOW(), INTERVAL ? HOUR)"
        );
        $reset->execute([$hours]);

        $pdo->commit();
        return count($sessionIds);
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('cleanup failed: ' . $e->getMessage());
        return 0;
    }
}
