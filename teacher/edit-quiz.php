<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_once __DIR__ . '/../includes/powerups.php';
require_teacher_login();

$quizId = (int)($_GET['id'] ?? 0);
$quiz = get_owned_quiz($quizId, current_teacher_id());
if (!$quiz) {
    flash_set('teacher_notice', 'Quiz not found.');
    redirect('index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $timeLimit = max(5, min(300, (int)($_POST['default_time_limit'] ?? 20)));
    $powerupCodes = array_intersect((array)($_POST['powerups'] ?? []), array_keys(POWERUP_DEFS));

    if ($title === '' || mb_strlen($title) > 150) {
        $error = 'Please enter a valid title.';
    } else {
        $stmt = db()->prepare(
            'UPDATE quizzes SET title=?, description=?, default_time_limit=?, powerups_enabled=?,
             show_feedback_immediately=?, show_leaderboard_during=?, show_correct_answers_after=?,
             time_bonus_enabled=?, penalty_enabled=?, penalty_points=? WHERE id=? AND teacher_id=?'
        );
        $stmt->execute([
            $title, $description, $timeLimit, implode(',', $powerupCodes),
            isset($_POST['show_feedback_immediately']) ? 1 : 0,
            isset($_POST['show_leaderboard_during']) ? 1 : 0,
            isset($_POST['show_correct_answers_after']) ? 1 : 0,
            isset($_POST['time_bonus_enabled']) ? 1 : 0,
            isset($_POST['penalty_enabled']) ? 1 : 0,
            max(0, min(1000, (int)($_POST['penalty_points'] ?? 0))),
            $quizId, current_teacher_id(),
        ]);
        flash_set('teacher_notice', 'Quiz updated.');
        redirect('edit-quiz.php?id=' . $quizId);
    }
    $quiz = get_owned_quiz($quizId, current_teacher_id());
}

$enabledPowerups = enabled_powerups_for_quiz($quiz);
$pageTitle = 'Edit Quiz';
require __DIR__ . '/../includes/header.php';
?>
<section class="form-card glass-card">
  <h1>Edit Quiz</h1>
  <p class="muted">Quiz code: <strong><?= e($quiz['code']) ?></strong> · Status: <?= e(ucwords(str_replace('_',' ',$quiz['status']))) ?></p>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="quiz-form">
    <?= csrf_field() ?>
    <label for="title">Quiz title</label>
    <input type="text" id="title" name="title" required maxlength="150" value="<?= e($quiz['title']) ?>">

    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3" maxlength="1000"><?= e($quiz['description']) ?></textarea>

    <label for="default_time_limit">Default time per question (seconds)</label>
    <input type="number" id="default_time_limit" name="default_time_limit" min="5" max="300" value="<?= (int)$quiz['default_time_limit'] ?>">

    <fieldset class="checkbox-group">
      <legend>Quiz behavior</legend>
      <label><input type="checkbox" name="show_feedback_immediately" <?= $quiz['show_feedback_immediately'] ? 'checked' : '' ?>> Show correct/incorrect immediately</label>
      <label><input type="checkbox" name="show_leaderboard_during" <?= $quiz['show_leaderboard_during'] ? 'checked' : '' ?>> Show live leaderboard</label>
      <label><input type="checkbox" name="show_correct_answers_after" <?= $quiz['show_correct_answers_after'] ? 'checked' : '' ?>> Reveal correct answers after</label>
      <label><input type="checkbox" name="time_bonus_enabled" <?= $quiz['time_bonus_enabled'] ? 'checked' : '' ?>> Award time-based bonus points</label>
      <label><input type="checkbox" name="penalty_enabled" <?= $quiz['penalty_enabled'] ? 'checked' : '' ?>> Apply penalty for wrong answers</label>
    </fieldset>

    <label for="penalty_points">Penalty points</label>
    <input type="number" id="penalty_points" name="penalty_points" min="0" max="1000" value="<?= (int)$quiz['penalty_points'] ?>">

    <fieldset class="checkbox-group">
      <legend>Powerups</legend>
      <?php foreach (POWERUP_DEFS as $code => $def): ?>
        <label><input type="checkbox" name="powerups[]" value="<?= e($code) ?>" <?= in_array($code, $enabledPowerups, true) ? 'checked' : '' ?>> <?= e($def['name']) ?></label>
      <?php endforeach; ?>
    </fieldset>

    <button type="submit" class="btn btn-primary btn-lg">Save Changes</button>
  </form>

  <div class="danger-zone">
    <h3>Danger Zone</h3>
    <form method="post" action="actions.php" onsubmit="return confirm('Delete this quiz and all its questions? This cannot be undone.');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_quiz">
      <input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>">
      <button type="submit" class="btn btn-danger">Delete Quiz</button>
    </form>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
