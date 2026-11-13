<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_once __DIR__ . '/../includes/powerups.php';
require_teacher_login();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $timeLimit = max(5, min(300, (int)($_POST['default_time_limit'] ?? 20)));
    $powerupCodes = array_intersect((array)($_POST['powerups'] ?? []), array_keys(POWERUP_DEFS));

    if ($title === '' || mb_strlen($title) > 150) {
        $error = 'Please enter a quiz title (up to 150 characters).';
    } else {
        $code = generate_quiz_code();
        $stmt = db()->prepare(
            'INSERT INTO quizzes (teacher_id, title, description, code, default_time_limit, powerups_enabled,
             show_feedback_immediately, show_leaderboard_during, show_correct_answers_after, time_bonus_enabled, penalty_enabled, penalty_points)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            current_teacher_id(), $title, $description, $code, $timeLimit, implode(',', $powerupCodes),
            isset($_POST['show_feedback_immediately']) ? 1 : 0,
            isset($_POST['show_leaderboard_during']) ? 1 : 0,
            isset($_POST['show_correct_answers_after']) ? 1 : 0,
            isset($_POST['time_bonus_enabled']) ? 1 : 0,
            isset($_POST['penalty_enabled']) ? 1 : 0,
            max(0, min(1000, (int)($_POST['penalty_points'] ?? 0))),
        ]);
        $newId = (int)db()->lastInsertId();
        flash_set('teacher_notice', "Quiz created! Code: {$code}");
        redirect('questions.php?id=' . $newId);
    }
}

$pageTitle = 'Create Quiz';
require __DIR__ . '/../includes/header.php';
?>
<section class="form-card glass-card">
  <h1>Create a New Quiz</h1>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="quiz-form">
    <?= csrf_field() ?>
    <label for="title">Quiz title</label>
    <input type="text" id="title" name="title" required maxlength="150" value="<?= e($_POST['title'] ?? '') ?>">

    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3" maxlength="1000"><?= e($_POST['description'] ?? '') ?></textarea>

    <label for="default_time_limit">Default time per question (seconds)</label>
    <input type="number" id="default_time_limit" name="default_time_limit" min="5" max="300" value="20">

    <fieldset class="checkbox-group">
      <legend>Quiz behavior</legend>
      <label><input type="checkbox" name="show_feedback_immediately" checked> Show correct/incorrect immediately after each answer</label>
      <label><input type="checkbox" name="show_leaderboard_during" checked> Show live leaderboard during the quiz</label>
      <label><input type="checkbox" name="show_correct_answers_after" checked> Reveal correct answers after the quiz ends</label>
      <label><input type="checkbox" name="time_bonus_enabled" checked> Award bonus points for faster answers</label>
      <label><input type="checkbox" name="penalty_enabled" id="penaltyToggle"> Apply a point penalty for wrong answers</label>
    </fieldset>

    <label for="penalty_points">Penalty points (if enabled)</label>
    <input type="number" id="penalty_points" name="penalty_points" min="0" max="1000" value="0">

    <fieldset class="checkbox-group">
      <legend>Powerups</legend>
      <?php foreach (POWERUP_DEFS as $code => $def): ?>
        <label><input type="checkbox" name="powerups[]" value="<?= e($code) ?>" checked> <?= e($def['name']) ?> — <span class="muted"><?= e($def['desc']) ?></span></label>
      <?php endforeach; ?>
    </fieldset>

    <button type="submit" class="btn btn-primary btn-lg">Create Quiz & Add Questions</button>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
