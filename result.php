<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/quiz_functions.php';

$p = $_SESSION['participant'] ?? null;
if (!$p) redirect('index.php');

$stmt = db()->prepare('SELECT * FROM participants WHERE id = ? AND participant_token = ?');
$stmt->execute([$p['id'], $p['token']]);
$participant = $stmt->fetch();
if (!$participant) redirect('index.php');

$stmt = db()->prepare('SELECT * FROM quizzes WHERE id = ?');
$stmt->execute([$participant['quiz_id']]);
$quiz = $stmt->fetch();
if (!$quiz) redirect('index.php');

$rankStmt = db()->prepare(
    'SELECT COUNT(*) + 1 FROM participants WHERE session_id = ? AND score > ?'
);
$rankStmt->execute([$participant['session_id'], $participant['score']]);
$rank = (int)$rankStmt->fetchColumn();

$totalQuestions = quiz_question_count((int)$quiz['id']);
$answered = $participant['correct_count'] + $participant['incorrect_count'];
$accuracy = $answered > 0 ? round(($participant['correct_count'] / $answered) * 100) : 0;

$powerupsUsed = db()->prepare('SELECT powerup_code FROM participant_powerups WHERE participant_id = ? AND last_used_at IS NOT NULL');
$powerupsUsed->execute([$participant['id']]);
$usedList = array_column($powerupsUsed->fetchAll(), 'powerup_code');

$correctAnswers = [];
if ($quiz['show_correct_answers_after']) {
    $questions = get_quiz_questions((int)$quiz['id']);
    foreach ($questions as $q) {
        $answers = get_question_answers((int)$q['id'], true);
        $correct = array_values(array_filter($answers, fn($a) => (int)$a['is_correct'] === 1));
        $correctAnswers[] = ['q' => $q['question_text'], 'a' => $correct[0]['answer_text'] ?? ''];
    }
}

$pageTitle = 'Your Results';
require __DIR__ . '/includes/header.php';
?>
<section class="result-panel glass-card">
  <h2>Quiz Complete!</h2>
  <div class="result-grid">
    <div class="result-stat"><span class="stat-value"><?= (int)$participant['score'] ?></span><span class="stat-label">Final Score</span></div>
    <div class="result-stat"><span class="stat-value">#<?= $rank ?></span><span class="stat-label">Rank</span></div>
    <div class="result-stat"><span class="stat-value"><?= $accuracy ?>%</span><span class="stat-label">Accuracy</span></div>
    <div class="result-stat"><span class="stat-value"><?= (int)$participant['correct_count'] ?></span><span class="stat-label">Correct</span></div>
    <div class="result-stat"><span class="stat-value"><?= (int)$participant['incorrect_count'] ?></span><span class="stat-label">Incorrect</span></div>
    <div class="result-stat"><span class="stat-value"><?= count($usedList) ?></span><span class="stat-label">Powerups Used</span></div>
  </div>

  <?php if ($quiz['show_correct_answers_after'] && $correctAnswers): ?>
  <div class="answer-review">
    <h3>Correct Answers</h3>
    <ol>
      <?php foreach ($correctAnswers as $ca): ?>
        <li><strong><?= e($ca['q']) ?></strong> — <?= e($ca['a']) ?></li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php endif; ?>

  <a class="btn btn-primary btn-lg" href="<?= e(base_url('leaderboard.php')) ?>">View Leaderboard</a>
  <br><br>
  <a class="btn btn-outline btn-lg" href="<?= e(base_url('index.php')) ?>">Join Another Quiz</a>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
