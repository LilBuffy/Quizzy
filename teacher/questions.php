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

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_question') {
    csrf_require();
    $text = trim((string)($_POST['question_text'] ?? ''));
    $type = ($_POST['type'] ?? 'mcq') === 'truefalse' ? 'truefalse' : 'mcq';
    $points = max(1, min(10000, (int)($_POST['points'] ?? 100)));
    $timeLimit = !empty($_POST['time_limit']) ? max(5, min(300, (int)$_POST['time_limit'])) : null;

    if ($text === '') {
        $error = 'Please enter the question text.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(order_index),0)+1 FROM questions WHERE quiz_id = ?');
            $orderStmt->execute([$quizId]);
            $order = (int)$orderStmt->fetchColumn();

            $insQ = $pdo->prepare('INSERT INTO questions (quiz_id, question_text, type, points, time_limit, order_index) VALUES (?,?,?,?,?,?)');
            $insQ->execute([$quizId, $text, $type, $points, $timeLimit, $order]);
            $questionId = (int)$pdo->lastInsertId();

            if ($type === 'truefalse') {
                $correct = ($_POST['tf_correct'] ?? 'true') === 'true' ? 1 : 0;
                $insA = $pdo->prepare('INSERT INTO answers (question_id, answer_text, is_correct, order_index) VALUES (?,?,?,?)');
                $insA->execute([$questionId, 'True', $correct === 1 ? 1 : 0, 1]);
                $insA->execute([$questionId, 'False', $correct === 0 ? 1 : 0, 2]);
            } else {
                $choices = $_POST['choice'] ?? [];
                $correctIndex = (int)($_POST['correct_choice'] ?? -1);
                $validChoices = 0;
                $insA = $pdo->prepare('INSERT INTO answers (question_id, answer_text, is_correct, order_index) VALUES (?,?,?,?)');
                foreach ($choices as $i => $choiceText) {
                    $choiceText = trim((string)$choiceText);
                    if ($choiceText === '') continue;
                    $validChoices++;
                    $insA->execute([$questionId, $choiceText, ($i === $correctIndex) ? 1 : 0, $i + 1]);
                }
                if ($validChoices < 2) {
                    throw new RuntimeException('at_least_two_choices');
                }
            }
            $pdo->commit();
            redirect('questions.php?id=' . $quizId);
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            $error = 'Multiple choice questions need at least 2 answer choices.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('add question failed: ' . $e->getMessage());
            $error = 'Could not save the question.';
        }
    }
}

$questions = get_quiz_questions($quizId);
$pageTitle = 'Questions · ' . $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1><?= e($quiz['title']) ?></h1>
  <p class="muted">Code: <strong><?= e($quiz['code']) ?></strong> · <?= count($questions) ?> question(s)</p>
</section>

<?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

<section class="questions-list">
  <?php foreach ($questions as $i => $q):
      $answers = get_question_answers((int)$q['id'], true);
  ?>
    <div class="glass-card question-row">
      <div class="question-row-top">
        <span class="q-index">Q<?= $i + 1 ?></span>
        <span class="q-type"><?= $q['type'] === 'mcq' ? 'Multiple Choice' : 'True / False' ?></span>
        <span class="q-points"><?= (int)$q['points'] ?> pts</span>
        <div class="q-reorder">
          <form method="post" action="actions.php" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move_question">
            <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
            <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
            <input type="hidden" name="direction" value="up">
            <button type="submit" class="icon-btn" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
          </form>
          <form method="post" action="actions.php" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move_question">
            <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
            <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
            <input type="hidden" name="direction" value="down">
            <button type="submit" class="icon-btn" <?= $i === count($questions) - 1 ? 'disabled' : '' ?>>↓</button>
          </form>
        </div>
      </div>
      <p class="q-text"><?= e($q['question_text']) ?></p>
      <ul class="q-answers">
        <?php foreach ($answers as $a): ?>
          <li class="<?= $a['is_correct'] ? 'is-correct' : '' ?>"><?= e($a['answer_text']) ?><?= $a['is_correct'] ? ' ✓' : '' ?></li>
        <?php endforeach; ?>
      </ul>
      <form method="post" action="actions.php" onsubmit="return confirm('Delete this question?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_question">
        <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
        <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
</section>

<section class="glass-card form-card">
  <h2>Add a Question</h2>
  <form method="post" class="quiz-form" id="questionForm">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="add_question">

    <label for="question_text">Question text</label>
    <textarea id="question_text" name="question_text" rows="2" required maxlength="1000"></textarea>

    <label for="type">Question type</label>
    <select id="type" name="type">
      <option value="mcq">Multiple Choice</option>
      <option value="truefalse">True / False</option>
    </select>

    <div class="grid-2">
      <div>
        <label for="points">Points</label>
        <input type="number" id="points" name="points" min="1" max="10000" value="100">
      </div>
      <div>
        <label for="time_limit">Time limit override (seconds, optional)</label>
        <input type="number" id="time_limit" name="time_limit" min="5" max="300">
      </div>
    </div>

    <div id="mcqFields">
      <label>Answer choices (mark the correct one)</label>
      <div class="choice-row"><input type="radio" name="correct_choice" value="0"><input type="text" name="choice[]" maxlength="255" placeholder="Choice A"></div>
      <div class="choice-row"><input type="radio" name="correct_choice" value="1"><input type="text" name="choice[]" maxlength="255" placeholder="Choice B"></div>
      <div class="choice-row"><input type="radio" name="correct_choice" value="2"><input type="text" name="choice[]" maxlength="255" placeholder="Choice C (optional)"></div>
      <div class="choice-row"><input type="radio" name="correct_choice" value="3"><input type="text" name="choice[]" maxlength="255" placeholder="Choice D (optional)"></div>
    </div>

    <div id="tfFields" class="hidden">
      <label>Correct answer</label>
      <select name="tf_correct">
        <option value="true">True</option>
        <option value="false">False</option>
      </select>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">Add Question</button>
  </form>
</section>

<a class="btn btn-outline" href="<?= e(base_url('teacher/index.php')) ?>">Back to Dashboard</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
