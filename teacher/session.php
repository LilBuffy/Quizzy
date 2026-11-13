<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_teacher_login();

$quizId = (int)($_GET['id'] ?? 0);
$quiz = get_owned_quiz($quizId, current_teacher_id());
if (!$quiz) {
    flash_set('teacher_notice', 'Quiz not found.');
    redirect('index.php');
}

$questionCount = quiz_question_count($quizId);
$session = get_active_session_for_quiz($quizId);
$notice = flash_get('teacher_notice');

$stmt = db()->prepare('SELECT * FROM participants WHERE quiz_id = ? ORDER BY score DESC, joined_at ASC LIMIT 50');
$stmt->execute([$quizId]);
$participants = $stmt->fetchAll();

$pageTitle = 'Manage Session · ' . $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1><?= e($quiz['title']) ?></h1>
  <p class="muted">Status: <span class="status-pill status-<?= e($quiz['status']) ?>"><?= e(ucwords(str_replace('_',' ',$quiz['status']))) ?></span> · <?= $questionCount ?> question(s)</p>
</section>

<?php if ($notice): ?><p class="alert alert-success"><?= e($notice) ?></p><?php endif; ?>

<section class="glass-card session-control">
  <div class="quiz-code-display">
    <span class="muted">Join code</span>
    <span class="quiz-code-big"><?= e($quiz['code']) ?></span>
  </div>

  <div class="session-actions">
    <?php if ($quiz['status'] === 'draft'): ?>
      <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="action" value="publish_quiz"><input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <button class="btn btn-primary" <?= $questionCount < 1 ? 'disabled' : '' ?>>Publish Quiz</button></form>
    <?php elseif (in_array($quiz['status'], ['published', 'completed'], true)): ?>
      <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="action" value="open_quiz"><input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <button class="btn btn-primary">Open for Joining</button></form>
    <?php elseif ($quiz['status'] === 'open' && $quiz['session_status'] === 'waiting'): ?>
      <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="action" value="start_quiz"><input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <button class="btn btn-primary">Start Quiz</button></form>
    <?php endif; ?>

    <?php if (in_array($quiz['session_status'], ['waiting','active'], true)): ?>
      <form method="post" action="actions.php" onsubmit="return confirm('Close this quiz session now?');"><?= csrf_field() ?><input type="hidden" name="action" value="close_quiz"><input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <button class="btn btn-outline">Close Session</button></form>
    <?php endif; ?>

    <?php if ($quiz['status'] === 'completed'): ?>
      <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="action" value="archive_quiz"><input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <button class="btn btn-outline">Archive</button></form>
    <?php endif; ?>
  </div>

  <div id="liveStats" class="live-stats">
    <span id="liveQuestion">Self-paced · <?= $questionCount ?> question(s)</span>
    <span id="liveParticipants"><?= count($participants) ?> joined</span>
    <span id="liveAnswered"></span>
  </div>
</section>

<section class="glass-card">
  <div class="section-head-row">
    <h2>Participants & Scores</h2>
    <a href="<?= e(base_url('teacher/statistics.php?id=' . $quizId)) ?>">View Statistics</a>
  </div>
  <table class="data-table">
    <thead><tr><th>#</th><th>Name</th><th>Score</th><th>Correct</th><th>Incorrect</th><th>Status</th></tr></thead>
    <tbody id="participantTableBody">
      <?php foreach ($participants as $i => $p): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($p['name']) ?></td>
          <td><?= (int)$p['score'] ?></td>
          <td><?= (int)$p['correct_count'] ?></td>
          <td><?= (int)$p['incorrect_count'] ?></td>
          <td><?= $p['completed_at'] ? 'Finished' : 'In progress' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$participants): ?><tr><td colspan="6" class="muted">No participants yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<script>
(function(){
  var quizId = <?= (int)$quizId ?>;
  var active = <?= $quiz['session_status'] === 'active' || $quiz['session_status'] === 'waiting' ? 'true' : 'false' ?>;
  if (!active) return;
  function poll(){
    fetch('live-status.php?id=' + quizId).then(r => r.json()).then(d => {
      if (!d.ok) return;
      document.getElementById('liveQuestion').textContent = 'Avg progress: ' + d.avg_question + ' / ' + d.total_questions;
      document.getElementById('liveParticipants').textContent = d.participant_count + ' joined';
      document.getElementById('liveAnswered').textContent = d.completed_count + ' finished';
    }).catch(() => {});
  }
  poll();
  setInterval(poll, 4000);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
