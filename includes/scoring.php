<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/powerups.php';

/**
 * Records one answer (or an auto-skip when $answerId is null / time ran out),
 * scores it server-side, updates the participant's running totals, and
 * advances their personal timer to the next question (or marks them
 * completed if this was the last one). Used by both api/answer.php
 * (explicit submissions) and api/state.php (auto-skip on timeout).
 *
 * Returns null if the answer was already recorded for this question
 * (duplicate submission — caller should treat this as a no-op).
 */
function record_participant_answer(array $participant, array $quiz, array $question, ?int $answerId, int $clientTimeTakenMs, bool $expired): ?array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $dupCheck = $pdo->prepare('SELECT 1 FROM participant_answers WHERE participant_id = ? AND question_id = ? FOR UPDATE');
        $dupCheck->execute([$participant['id'], $question['id']]);
        if ($dupCheck->fetchColumn()) {
            $pdo->rollBack();
            return null;
        }

        $isCorrect = false;
        $pointsAwarded = 0;

        if (!$expired && $answerId !== null) {
            $correctStmt = $pdo->prepare('SELECT id FROM answers WHERE question_id = ? AND is_correct = 1 LIMIT 1');
            $correctStmt->execute([$question['id']]);
            $correctId = (int)$correctStmt->fetchColumn();
            $isCorrect = ($answerId === $correctId);

            if ($isCorrect) {
                $pointsAwarded = (int)$question['points'];

                $powerups = get_participant_powerups((int)$participant['id']);
                if (!empty($powerups['double_points']) && (int)$powerups['double_points']['active_for_next_question'] === 1) {
                    $pointsAwarded *= 2;
                    consume_active_flag((int)$participant['id'], 'double_points');
                }

                if ($quiz['time_bonus_enabled']) {
                    $timeLimit = (int)($question['time_limit'] ?: $quiz['default_time_limit']);
                    $safeElapsed = max(0, min($clientTimeTakenMs / 1000, $timeLimit));
                    $timeBonus = (int)round((($timeLimit - $safeElapsed) / max(1, $timeLimit)) * $pointsAwarded * 0.3);
                    $pointsAwarded += max(0, $timeBonus);
                }
            } elseif ($quiz['penalty_enabled']) {
                $powerups = get_participant_powerups((int)$participant['id']);
                $shielded = !empty($powerups['shield']) && (int)$powerups['shield']['active_for_next_question'] === 1;
                if ($shielded) {
                    consume_active_flag((int)$participant['id'], 'shield');
                } else {
                    $pointsAwarded = -1 * abs((int)$quiz['penalty_points']);
                }
            }
        }

        $insert = $pdo->prepare(
            'INSERT INTO participant_answers (participant_id, question_id, answer_id, is_correct, points_awarded, time_taken_ms) VALUES (?,?,?,?,?,?)'
        );
        $insert->execute([$participant['id'], $question['id'], $answerId, $isCorrect ? 1 : 0, $pointsAwarded, max(0, $clientTimeTakenMs)]);

        $updateParticipant = $pdo->prepare(
            'UPDATE participants SET score = score + ?, correct_count = correct_count + ?, incorrect_count = incorrect_count + ? WHERE id = ?'
        );
        $updateParticipant->execute([$pointsAwarded, $isCorrect ? 1 : 0, (!$isCorrect && $answerId !== null) ? 1 : 0, $participant['id']]);

        $totalQuestions = quiz_question_count((int)$quiz['id']);
        $answeredSoFar = participant_answered_count((int)$participant['id']);
        if ($answeredSoFar >= $totalQuestions) {
            $pdo->prepare('UPDATE participants SET completed_at = NOW() WHERE id = ?')->execute([$participant['id']]);
        } else {
            $pdo->prepare('UPDATE participants SET current_question_started_at = NOW() WHERE id = ?')->execute([$participant['id']]);
        }

        $pdo->commit();
        return ['correct' => $isCorrect, 'points_awarded' => $pointsAwarded, 'expired' => $expired];
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('record_participant_answer failed: ' . $e->getMessage());
        return null;
    }
}
