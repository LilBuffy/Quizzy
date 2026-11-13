<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_teacher_login();

$teacherId = current_teacher_id();
$pdo = db();

$counts = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status IN ('open','in_progress')) AS active,
        SUM(status = 'completed') AS completed
     FROM quizzes WHERE teacher_id = ?"
);
$counts->execute([$teacherId]);
$stats = $counts->fetch();

$participantsStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT p.id) FROM participants p JOIN quizzes q ON q.id = p.quiz_id WHERE q.teacher_id = ?"
);
$participantsStmt->execute([$teacherId]);
$totalParticipants = (int)$participantsStmt->fetchColumn();

$recentStmt = $pdo->prepare(
    "SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
     (SELECT COUNT(*) FROM participants WHERE quiz_id = q.id) AS participant_count
     FROM quizzes q WHERE teacher_id = ? ORDER BY updated_at DESC LIMIT 8"
);
$recentStmt->execute([$teacherId]);
$recentQuizzes = $recentStmt->fetchAll();

$notice = flash_get('teacher_notice');
$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-head">
  <div>
    <h1>Welcome back, <?= e($_SESSION['teacher_name']) ?></h1>
    <p class="muted">Here's what's happening with your quizzes.</p>
  </div>
  <a class="btn btn-primary btn-lg" href="<?= e(base_url('teacher/create-quiz.php')) ?>">+ Create Quiz</a>
</section>

<?php if ($notice): ?><p class="alert alert-success"><?= e($notice) ?></p><?php endif; ?>

<section class="stat-cards">
  <div class="glass-card stat-card"><span class="stat-value"><?= (int)$stats['total'] ?></span><span class="stat-label">Total Quizzes</span></div>
  <div class="glass-card stat-card"><span class="stat-value"><?= (int)$stats['active'] ?></span><span class="stat-label">Active</span></div>
  <div class="glass-card stat-card"><span class="stat-value"><?= (int)$stats['completed'] ?></span><span class="stat-label">Completed</span></div>
  <div class="glass-card stat-card"><span class="stat-value"><?= $totalParticipants ?></span><span class="stat-label">Total Participants</span></div>
</section>

<section class="quiz-list-section">
  <h2>Recent Quizzes</h2>
  <?php if (!$recentQuizzes): ?>
    <p class="muted">You haven't created any quizzes yet.</p>
  <?php else: ?>
  <div class="quiz-cards">
    <?php foreach ($recentQuizzes as $q): ?>
      <div class="glass-card quiz-card">
        <div class="quiz-card-top">
          <h3><?= e($q['title']) ?></h3>
          <span class="status-pill status-<?= e($q['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $q['status']))) ?></span>
        </div>
        <div class="quiz-card-meta">
          <span>Code: <strong><?= e($q['code']) ?></strong></span>
          <span><?= (int)$q['question_count'] ?> questions</span>
          <span><?= (int)$q['participant_count'] ?> participants</span>
        </div>
        <div class="quiz-card-actions">
          <a href="<?= e(base_url('teacher/edit-quiz.php?id=' . $q['id'])) ?>">Edit</a>
          <a href="<?= e(base_url('teacher/questions.php?id=' . $q['id'])) ?>">Questions</a>
          <a href="<?= e(base_url('teacher/session.php?id=' . $q['id'])) ?>">Manage Session</a>
          <a href="<?= e(base_url('teacher/statistics.php?id=' . $q['id'])) ?>">Stats</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
