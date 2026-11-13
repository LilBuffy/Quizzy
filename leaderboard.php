<?php
require_once __DIR__ . '/includes/functions.php';

$p = $_SESSION['participant'] ?? null;
if (!$p) redirect('index.php');

$stmt = db()->prepare('SELECT * FROM participants WHERE id = ? AND participant_token = ?');
$stmt->execute([$p['id'], $p['token']]);
$participant = $stmt->fetch();
if (!$participant) redirect('index.php');

$stmt = db()->prepare('SELECT * FROM quizzes WHERE id = ?');
$stmt->execute([$participant['quiz_id']]);
$quiz = $stmt->fetch();

$lb = db()->prepare('SELECT name, score, correct_count, completed_at FROM participants WHERE session_id = ? ORDER BY score DESC, joined_at ASC');
$lb->execute([$participant['session_id']]);
$rows = $lb->fetchAll();

$pageTitle = 'Leaderboard';
require __DIR__ . '/includes/header.php';
?>
<section class="leaderboard-panel glass-card">
  <h2><?= e($quiz['title'] ?? 'Leaderboard') ?> — Leaderboard</h2>
  <ol class="leaderboard-list">
    <?php $rank = 0; $prevScore = null; $displayRank = 0; foreach ($rows as $i => $row):
        $rank = $i + 1;
        if ($row['score'] !== $prevScore) $displayRank = $rank;
        $prevScore = $row['score'];
        $isMe = $row['name'] === $participant['name'];
    ?>
      <li class="leaderboard-row <?= $isMe ? 'is-me' : '' ?>">
        <span class="lb-rank">#<?= $displayRank ?></span>
        <span class="lb-name"><?= e($row['name']) ?></span>
        <span class="lb-score"><?= (int)$row['score'] ?> pts</span>
        <span class="lb-status"><?= $row['completed_at'] ? 'Finished' : 'In progress' ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
  <a class="btn btn-outline btn-lg" href="<?= e(base_url('index.php')) ?>">Join Another Quiz</a>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
