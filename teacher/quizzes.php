<?php
require_once __DIR__ . '/../includes/functions.php';
require_teacher_login();

$teacherId = current_teacher_id();
$stmt = db()->prepare(
    "SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
     (SELECT COUNT(*) FROM participants WHERE quiz_id = q.id) AS participant_count
     FROM quizzes q WHERE teacher_id = ? ORDER BY created_at DESC"
);
$stmt->execute([$teacherId]);
$quizzes = $stmt->fetchAll();

$pageTitle = 'All Quizzes';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>All Quizzes</h1>
  <a class="btn btn-primary" href="<?= e(base_url('teacher/create-quiz.php')) ?>">+ Create Quiz</a>
</section>
<section class="quiz-cards">
  <?php foreach ($quizzes as $q): ?>
    <div class="glass-card quiz-card">
      <div class="quiz-card-top">
        <h3><?= e($q['title']) ?></h3>
        <span class="status-pill status-<?= e($q['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $q['status']))) ?></span>
      </div>
      <div class="quiz-card-meta">
        <span>Code: <strong><?= e($q['code']) ?></strong></span>
        <span><?= (int)$q['question_count'] ?> questions</span>
        <span><?= (int)$q['participant_count'] ?> participants</span>
        <span><?= e(date('M j, Y', strtotime($q['created_at']))) ?></span>
      </div>
      <div class="quiz-card-actions">
        <a href="<?= e(base_url('teacher/edit-quiz.php?id=' . $q['id'])) ?>">Edit</a>
        <a href="<?= e(base_url('teacher/questions.php?id=' . $q['id'])) ?>">Questions</a>
        <a href="<?= e(base_url('teacher/session.php?id=' . $q['id'])) ?>">Manage Session</a>
        <a href="<?= e(base_url('teacher/statistics.php?id=' . $q['id'])) ?>">Stats</a>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$quizzes): ?><p class="muted">No quizzes yet.</p><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
