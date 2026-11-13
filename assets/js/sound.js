// Simple synthesized sound effects (no external audio assets, no copyrighted material).
window.QuizzySound = (function () {
  var ctx = null;
  function getCtx() {
    if (!ctx) {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return null;
      ctx = new AC();
    }
    return ctx;
  }

  function isMuted() {
    return localStorage.getItem('quizzy_muted') === '1';
  }

  function setMuted(muted) {
    localStorage.setItem('quizzy_muted', muted ? '1' : '0');
  }

  function tone(freq, duration, delay, type) {
    if (isMuted()) return;
    var audio = getCtx();
    if (!audio) return;
    var osc = audio.createOscillator();
    var gain = audio.createGain();
    osc.type = type || 'sine';
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.0001, audio.currentTime + (delay || 0));
    gain.gain.exponentialRampToValueAtTime(0.15, audio.currentTime + (delay || 0) + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + (delay || 0) + duration);
    osc.connect(gain);
    gain.connect(audio.destination);
    osc.start(audio.currentTime + (delay || 0));
    osc.stop(audio.currentTime + (delay || 0) + duration + 0.05);
  }

  return {
    correct: function () { tone(660, 0.12, 0, 'sine'); tone(880, 0.16, 0.1, 'sine'); },
    incorrect: function () { tone(220, 0.25, 0, 'sawtooth'); },
    powerup: function () { tone(520, 0.08, 0, 'square'); tone(780, 0.1, 0.08, 'square'); },
    complete: function () { tone(523, 0.12, 0); tone(659, 0.12, 0.12); tone(784, 0.2, 0.24); },
    isMuted: isMuted,
    setMuted: setMuted,
  };
})();
