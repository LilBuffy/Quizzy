<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/quiz_functions.php';

$p = $_SESSION['participant'] ?? null;
if (!$p) redirect('index.php');

$stmt = db()->prepare('SELECT * FROM participants WHERE id = ? AND participant_token = ?');
$stmt->execute([$p['id'], $p['token']]);
$participant = $stmt->fetch();
if (!$participant) {
    unset($_SESSION['participant']);
    flash_set('join_error', 'Your quiz session is no longer valid.');
    redirect('index.php');
}

$stmt = db()->prepare('SELECT * FROM quizzes WHERE id = ?');
$stmt->execute([$participant['quiz_id']]);
$quiz = $stmt->fetch();

if (!$quiz) redirect('index.php');

if ($participant['completed_at'] || $quiz['session_status'] === 'completed') {
    redirect('result.php');
}

$pageTitle = 'Playing: ' . $quiz['title'];
require __DIR__ . '/includes/header.php';
?>
<section class="quiz-shell" id="quizShell" data-csrf="<?= e(csrf_token()) ?>">
  <div class="quiz-top-bar">
    <div class="quiz-title"><?= e($quiz['title']) ?></div>
    <div class="quiz-top-right">
      <button id="muteToggle" class="icon-btn" type="button" aria-label="Mute or unmute sound">🔊</button>
      <div class="quiz-score">Score: <span id="scoreValue"><?= (int)$participant['score'] ?></span></div>
    </div>
  </div>

  <div id="waitingPanel" class="glass-card waiting-panel">
    <h2>You're in! 🎉</h2>
    <p>Waiting for the teacher to start the quiz…</p>
    <div class="spinner"></div>
  </div>

  <div id="questionPanel" class="glass-card question-panel hidden">
    <div class="question-meta">
      <span id="questionNumber">Question 1 / 1</span>
      <span id="timer" class="timer">--</span>
    </div>
    <h2 id="questionText"></h2>
    <div id="answerGrid" class="answer-grid"></div>
    <div id="powerupBar" class="powerup-bar"></div>
    <div id="feedbackMsg" class="feedback-msg"></div>
  </div>

  <div id="finishedPanel" class="glass-card hidden">
    <h2>Quiz complete!</h2>
    <p>Redirecting to your results…</p>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script>window.QUIZZY_BASE = <?= json_encode(base_url('')) ?>;</script>
<script src="<?= e(base_url('assets/js/sound.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/quiz.js')) ?>"></script>
