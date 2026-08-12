<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_teacher_login();

$quizId = (int)($_GET['id'] ?? 0);
$quiz = get_owned_quiz($quizId, current_teacher_id());
if (!$quiz) {
    flash_set('teacher_notice', 'Quiz not found.');
    redirect('index.php');
}

$pdo = db();

$overall = $pdo->prepare(
    'SELECT COUNT(*) AS n, AVG(score) AS avg_score, MAX(score) AS max_score, MIN(score) AS min_score,
     AVG(correct_count / NULLIF(correct_count + incorrect_count, 0)) AS avg_accuracy
     FROM participants WHERE quiz_id = ?'
);
$overall->execute([$quizId]);
$o = $overall->fetch();

$avgTime = $pdo->prepare(
    'SELECT AVG(pa.time_taken_ms) FROM participant_answers pa
     JOIN participants p ON p.id = pa.participant_id WHERE p.quiz_id = ?'
);
$avgTime->execute([$quizId]);
$avgTimeMs = (float)($avgTime->fetchColumn() ?: 0);

$questions = get_quiz_questions($quizId);
$questionStats = [];
foreach ($questions as $q) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS attempts, SUM(is_correct) AS correct FROM participant_answers WHERE question_id = ?'
    );
    $stmt->execute([$q['id']]);
    $r = $stmt->fetch();
    $attempts = (int)$r['attempts'];
    $correct = (int)$r['correct'];
    $rate = $attempts > 0 ? round(($correct / $attempts) * 100) : null;

    $wrongStmt = $pdo->prepare(
        'SELECT a.answer_text, COUNT(*) AS n FROM participant_answers pa
         JOIN answers a ON a.id = pa.answer_id
         WHERE pa.question_id = ? AND pa.is_correct = 0
         GROUP BY a.id ORDER BY n DESC LIMIT 1'
    );
    $wrongStmt->execute([$q['id']]);
    $topWrong = $wrongStmt->fetch();

    $questionStats[] = [
        'text' => $q['question_text'],
        'attempts' => $attempts,
        'rate' => $rate,
        'top_wrong' => $topWrong ? $topWrong['answer_text'] : null,
    ];
}

$rates = array_filter(array_column($questionStats, 'rate'), fn($r) => $r !== null);
$hardest = null; $easiest = null;
if ($rates) {
    $minRate = min($rates); $maxRate = max($rates);
    foreach ($questionStats as $qs) {
        if ($qs['rate'] === $minRate && $hardest === null) $hardest = $qs;
        if ($qs['rate'] === $maxRate && $easiest === null) $easiest = $qs;
    }
}

$pageTitle = 'Statistics · ' . $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1><?= e($quiz['title']) ?> — Statistics</h1>
</section>

<section class="stat-cards">
  <div class="glass-card stat-card"><span class="stat-value"><?= (int)$o['n'] ?></span><span class="stat-label">Participants</span></div>
  <div class="glass-card stat-card"><span class="stat-value"><?= $o['avg_score'] !== null ? round($o['avg_score']) : '—' ?></span><span class="stat-label">Average Score</span></div>
  <div class="glass-card stat-card"><span class="stat-value"><?= $o['max_score'] ?? '—' ?></span><span class="stat-label">Highest Score</span></div>
  <div class="glass-card stat-card"><span class="stat-value"><?= $o['min_score'] ?? '—' ?></span><span class="stat-label">Lowest Score</span></div>
  <div class="glass-card stat-card"><span class="stat-value"><?= $o['avg_accuracy'] !== null ? round($o['avg_accuracy'] * 100) . '%' : '—' ?></span><span class="stat-label">Average Accuracy</span></div>
  <div class="glass-card stat-card"><span class="stat-value"><?= $avgTimeMs > 0 ? round($avgTimeMs / 1000, 1) . 's' : '—' ?></span><span class="stat-label">Avg Answer Time</span></div>
</section>

<section class="glass-card">
  <h2>Question Difficulty</h2>
  <?php if ($hardest): ?><p><strong>Most difficult:</strong> <?= e($hardest['text']) ?> (<?= $hardest['rate'] ?>% correct)</p><?php endif; ?>
  <?php if ($easiest): ?><p><strong>Easiest:</strong> <?= e($easiest['text']) ?> (<?= $easiest['rate'] ?>% correct)</p><?php endif; ?>
  <table class="data-table">
    <thead><tr><th>Question</th><th>Attempts</th><th>Correct Rate</th><th>Most common wrong answer</th></tr></thead>
    <tbody>
      <?php foreach ($questionStats as $qs): ?>
        <tr>
          <td><?= e($qs['text']) ?></td>
          <td><?= $qs['attempts'] ?></td>
          <td><?= $qs['rate'] !== null ? $qs['rate'] . '%' : '—' ?></td>
          <td><?= $qs['top_wrong'] ? e($qs['top_wrong']) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
