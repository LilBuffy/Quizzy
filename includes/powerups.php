<?php
require_once __DIR__ . '/database.php';

const POWERUP_DEFS = [
    'double_points' => ['name' => 'Double Points', 'uses' => 1, 'desc' => 'Your next correct answer scores double.'],
    'shield'        => ['name' => 'Shield',        'uses' => 1, 'desc' => 'Blocks the next penalty from a wrong answer.'],
    'time_boost'    => ['name' => 'Time Boost',    'uses' => 1, 'desc' => 'Adds extra seconds to the current question.'],
    'fifty_fifty'   => ['name' => '50 / 50',        'uses' => 1, 'desc' => 'Removes two incorrect choices.'],
    'score_boost'   => ['name' => 'Score Boost',   'uses' => 1, 'desc' => 'Grants a small flat bonus, once per quiz.'],
];

const TIME_BOOST_SECONDS = 5;
const SCORE_BOOST_POINTS = 25;

function grant_default_powerups(int $participantId, array $quiz): void {
    $enabled = enabled_powerups_for_quiz($quiz);
    if (empty($enabled)) return;
    $stmt = db()->prepare(
        'INSERT INTO participant_powerups (participant_id, powerup_code, uses_remaining) VALUES (?,?,?)'
    );
    foreach ($enabled as $code) {
        if (!isset(POWERUP_DEFS[$code])) continue;
        $stmt->execute([$participantId, $code, POWERUP_DEFS[$code]['uses']]);
    }
}

function get_participant_powerups(int $participantId): array {
    $stmt = db()->prepare('SELECT * FROM participant_powerups WHERE participant_id = ?');
    $stmt->execute([$participantId]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['powerup_code']] = $r;
    return $out;
}

/**
 * Server-validated powerup activation. Returns ['ok'=>bool, 'error'=>?, 'effect'=>?]
 */
function use_powerup(array $participant, array $quiz, string $code): array {
    if (!isset(POWERUP_DEFS[$code])) return ['ok' => false, 'error' => 'Unknown powerup.'];
    if (!in_array($code, enabled_powerups_for_quiz($quiz), true)) {
        return ['ok' => false, 'error' => 'This powerup is not enabled for this quiz.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM participant_powerups WHERE participant_id = ? AND powerup_code = ? FOR UPDATE');
        $stmt->execute([$participant['id'], $code]);
        $row = $stmt->fetch();

        if (!$row || (int)$row['uses_remaining'] <= 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'No uses of this powerup remaining.'];
        }

        $active = in_array($code, ['double_points', 'shield'], true) ? 1 : 0;
        $pdo->prepare('UPDATE participant_powerups SET uses_remaining = uses_remaining - 1, active_for_next_question = ?, last_used_at = NOW() WHERE id = ?')
            ->execute([$active, $row['id']]);

        $effect = null;
        if ($code === 'score_boost') {
            $pdo->prepare('UPDATE participants SET score = score + ? WHERE id = ?')->execute([SCORE_BOOST_POINTS, $participant['id']]);
            $effect = ['points' => SCORE_BOOST_POINTS];
        } elseif ($code === 'time_boost') {
            $effect = ['seconds' => TIME_BOOST_SECONDS];
        }

        $pdo->commit();
        return ['ok' => true, 'effect' => $effect];
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('powerup use failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not use powerup right now.'];
    }
}

function consume_active_flag(int $participantId, string $code): void {
    db()->prepare('UPDATE participant_powerups SET active_for_next_question = 0 WHERE participant_id = ? AND powerup_code = ?')
        ->execute([$participantId, $code]);
}
