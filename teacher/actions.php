<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/quiz_functions.php';
require_teacher_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_require();

$action = (string)($_POST['action'] ?? '');
$teacherId = current_teacher_id();
$pdo = db();

function back_to_quiz(int $quizId, ?string $notice = null, string $page = 'edit-quiz.php'): void {
    if ($notice) flash_set('teacher_notice', $notice);
    redirect($page . '?id=' . $quizId);
}

switch ($action) {
    case 'delete_quiz': {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $quiz = get_owned_quiz($quizId, $teacherId);
        if (!$quiz) redirect('index.php');
        $stmt = $pdo->prepare('DELETE FROM quizzes WHERE id = ? AND teacher_id = ?');
        $stmt->execute([$quizId, $teacherId]);
        flash_set('teacher_notice', 'Quiz deleted.');
        redirect('index.php');
    }

    case 'delete_question': {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $quiz = get_owned_quiz($quizId, $teacherId);
        if (!$quiz) redirect('index.php');
        $stmt = $pdo->prepare('DELETE FROM questions WHERE id = ? AND quiz_id = ?');
        $stmt->execute([$questionId, $quizId]);
        redirect('questions.php?id=' . $quizId);
    }

    case 'move_question': {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
        $quiz = get_owned_quiz($quizId, $teacherId);
        if (!$quiz) redirect('index.php');

        $questions = get_quiz_questions($quizId);
        $pos = null;
        foreach ($questions as $i => $q) if ((int)$q['id'] === $questionId) { $pos = $i; break; }
        $swapPos = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos !== null && isset($questions[$swapPos])) {
            $a = $questions[$pos];
            $b = $questions[$swapPos];
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE questions SET order_index = ? WHERE id = ?')->execute([$b['order_index'], $a['id']]);
                $pdo->prepare('UPDATE questions SET order_index = ? WHERE id = ?')->execute([$a['order_index'], $b['id']]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
            }
        }
        redirect('questions.php?id=' . $quizId);
    }

    case 'publish_quiz': {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $quiz = get_owned_quiz($quizId, $teacherId);
        if (!$quiz) redirect('index.php');
        if (quiz_question_count($quizId) < 1) {
            back_to_quiz($quizId, 'Add at least one question before publishing.');
        }
        $pdo->prepare("UPDATE quizzes SET status='published' WHERE id = ? AND teacher_id = ?")->execute([$quizId, $teacherId]);
        back_to_quiz($quizId, 'Quiz published.', 'session.php');
    }

    case 'open_quiz': {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $quiz = get_owned_quiz($quizId, $teacherId);
        if (!$quiz) redirect('index.php');
        if (!in_array($quiz['status'], ['published', 'completed'], true)) {
            back_to_quiz($quizId, 'Publish the quiz first.', 'session.php');
        }
        open_quiz_for_joining($quiz);
        back_to_quiz($quizId, 'Quiz is now open for participants to join.', 'session.php');
    }

    case 'start_quiz': {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $quiz = get_owned_quiz($quizId, $teacherId);
        if (!$quiz) redirect('index.php');
        $session = get_active_session_for_quiz($quizId);
        if (!$session || $quiz['status'] !== 'open') {
            back_to_quiz($quizId, 'Open the quiz for joining first.', 'session.php');
        }
        start_quiz_session($quiz, $session);
        back_to_quiz($quizId, 'Quiz started!', 'session.php');
    }

    case 'close_quiz': {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $quiz = get_owned_quiz($quizId, $teacherId);
        if (!$quiz) redirect('index.php');
        close_quiz_session($quiz);
        back_to_quiz($quizId, 'Quiz session closed.', 'session.php');
    }

    case 'archive_quiz': {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $quiz = get_owned_quiz($quizId, $teacherId);
        if (!$quiz) redirect('index.php');
        $pdo->prepare("UPDATE quizzes SET status='archived' WHERE id = ? AND teacher_id = ?")->execute([$quizId, $teacherId]);
        flash_set('teacher_notice', 'Quiz archived.');
        redirect('index.php');
    }

    default:
        redirect('index.php');
}
