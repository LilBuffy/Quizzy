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

$stmt = db()->prepare('SELECT * FROM participants WHERE quiz_id = ? ORDER BY score DESC, joined_at ASC');
$stmt->execute([$quizId]);
$participants = $stmt->fetchAll();

$pageTitle = 'Leaderboard · ' . $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head"><h1><?= e($quiz['title']) ?> — Leaderboard</h1></section>
<section class="glass-card leaderboard-panel">
  <ol class="leaderboard-list">
    <?php $rank = 0; $prevScore = null; $displayRank = 0; foreach ($participants as $i => $p):
        $rank = $i + 1;
        if ($p['score'] !== $prevScore) $displayRank = $rank;
        $prevScore = $p['score'];
    ?>
      <li class="leaderboard-row">
        <span class="lb-rank">#<?= $displayRank ?></span>
        <span class="lb-name"><?= e($p['name']) ?></span>
        <span class="lb-score"><?= (int)$p['score'] ?> pts</span>
        <span class="lb-status"><?= (int)$p['correct_count'] ?> correct</span>
      </li>
    <?php endforeach; ?>
    <?php if (!$participants): ?><p class="muted">No participants yet.</p><?php endif; ?>
  </ol>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
