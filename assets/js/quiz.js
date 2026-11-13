(function () {
  var shell = document.getElementById('quizShell');
  if (!shell) return;
  var csrfToken = shell.dataset.csrf;
  var base = window.QUIZZY_BASE || '/';

  var waitingPanel = document.getElementById('waitingPanel');
  var questionPanel = document.getElementById('questionPanel');
  var finishedPanel = document.getElementById('finishedPanel');
  var questionNumberEl = document.getElementById('questionNumber');
  var timerEl = document.getElementById('timer');
  var questionTextEl = document.getElementById('questionText');
  var answerGridEl = document.getElementById('answerGrid');
  var powerupBarEl = document.getElementById('powerupBar');
  var feedbackEl = document.getElementById('feedbackMsg');
  var scoreEl = document.getElementById('scoreValue');
  var muteBtn = document.getElementById('muteToggle');

  if (muteBtn && window.QuizzySound) {
    function syncMuteBtn() { muteBtn.textContent = window.QuizzySound.isMuted() ? '🔇' : '🔊'; }
    syncMuteBtn();
    muteBtn.addEventListener('click', function () {
      window.QuizzySound.setMuted(!window.QuizzySound.isMuted());
      syncMuteBtn();
    });
  }

  var currentQuestionId = null;
  var answered = false;
  var localRemaining = 0;
  var countdownHandle = null;
  var questionShownAt = 0;
  var extraSeconds = 0;

  function show(panel) {
    [waitingPanel, questionPanel, finishedPanel].forEach(function (p) {
      p.classList.toggle('hidden', p !== panel);
    });
  }

  function postForm(url, data) {
    var form = new FormData();
    form.append('csrf_token', csrfToken);
    Object.keys(data).forEach(function (k) { form.append(k, data[k]); });
    return fetch(base + url, { method: 'POST', body: form, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function startCountdown(seconds) {
    clearInterval(countdownHandle);
    localRemaining = seconds;
    renderTimer();
    countdownHandle = setInterval(function () {
      localRemaining -= 1;
      if (localRemaining < 0) localRemaining = 0;
      renderTimer();
      if (localRemaining <= 0) clearInterval(countdownHandle);
    }, 1000);
  }

  function renderTimer() {
    timerEl.textContent = localRemaining + 's';
    timerEl.classList.toggle('low', localRemaining <= 5);
  }

  function renderPowerups(list) {
    powerupBarEl.innerHTML = '';
    (list || []).forEach(function (pu) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'powerup-chip';
      btn.disabled = pu.remaining <= 0 || answered;
      btn.innerHTML = '<span class="pc-count">' + pu.remaining + '</span><span>' + pu.name + '</span>';
      btn.title = pu.desc;
      btn.addEventListener('click', function () {
        btn.disabled = true;
        postForm('api/powerup.php', { code: pu.code }).then(function (res) {
          if (!res.ok) { btn.disabled = false; return; }
          if (window.QuizzySound) window.QuizzySound.powerup();
          if (res.effect && res.effect.seconds) {
            extraSeconds += res.effect.seconds;
            localRemaining += res.effect.seconds;
            renderTimer();
          }
          poll();
        });
      });
      powerupBarEl.appendChild(btn);
    });
  }

  function renderQuestion(q, alreadyAnswered) {
    var isNewQuestion = currentQuestionId !== q.id;
    currentQuestionId = q.id;
    answered = alreadyAnswered;

    questionNumberEl.textContent = 'Question ' + (q.index + 1) + ' / ' + q.total;
    questionTextEl.textContent = q.text;

    if (isNewQuestion) {
      feedbackEl.textContent = '';
      feedbackEl.className = 'feedback-msg';
      answerGridEl.innerHTML = '';
      q.answers.forEach(function (a) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'answer-btn';
        btn.textContent = a.text;
        btn.dataset.answerId = a.id;
        btn.disabled = alreadyAnswered;
        btn.addEventListener('click', function () { submitAnswer(q.id, a.id, btn); });
        answerGridEl.appendChild(btn);
      });
      questionShownAt = Date.now();
      extraSeconds = 0;
      startCountdown(q.time_remaining);
    } else {
      localRemaining = q.time_remaining + extraSeconds;
      renderTimer();
      var btns = answerGridEl.querySelectorAll('.answer-btn');
      btns.forEach(function (b) { b.disabled = alreadyAnswered; });
    }

    if (alreadyAnswered && !feedbackEl.textContent) {
      feedbackEl.textContent = "Answer locked in. Waiting for the next question…";
    }
  }

  function submitAnswer(questionId, answerId, btn) {
    if (answered) return;
    answered = true;
    var allBtns = answerGridEl.querySelectorAll('.answer-btn');
    allBtns.forEach(function (b) { b.disabled = true; });
    btn.classList.add('selected');
    clearInterval(countdownHandle);

    var timeTaken = Date.now() - questionShownAt;
    postForm('api/answer.php', { question_id: questionId, answer_id: answerId, time_taken_ms: timeTaken })
      .then(function (res) {
        if (!res.ok) {
          feedbackEl.textContent = 'Could not submit answer.';
          return;
        }
        if (res.correct === true) {
          btn.classList.add('correct');
          feedbackEl.textContent = 'Correct! +' + res.points_awarded + ' points';
          feedbackEl.className = 'feedback-msg correct';
          if (window.QuizzySound) window.QuizzySound.correct();
        } else if (res.correct === false) {
          btn.classList.add('incorrect');
          feedbackEl.textContent = res.points_awarded < 0 ? (res.points_awarded + ' points') : 'Not quite!';
          feedbackEl.className = 'feedback-msg incorrect';
          if (window.QuizzySound) window.QuizzySound.incorrect();
        } else {
          feedbackEl.textContent = 'Answer submitted!';
        }
        poll();
      });
  }

  function poll() {
    fetch(base + 'api/state.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        if (data.status === 'completed') {
          if (!shell.dataset.completedSoundPlayed) {
            shell.dataset.completedSoundPlayed = '1';
            if (window.QuizzySound) window.QuizzySound.complete();
          }
          show(finishedPanel);
          setTimeout(function () { window.location.href = base + 'result.php'; }, 900);
          return;
        }
        if (data.status === 'waiting') {
          show(waitingPanel);
          return;
        }
        if (data.status === 'active') {
          show(questionPanel);
          scoreEl.textContent = data.score;
          renderQuestion(data.question, data.already_answered);
          renderPowerups(data.powerups);
        }
      })
      .catch(function () {});
  }

  poll();
  setInterval(poll, 2000);
})();
