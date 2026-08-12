(function () {
  var toggle = document.getElementById('themeToggle');
  if (!toggle) return;
  toggle.addEventListener('click', function () {
    var root = document.documentElement;
    var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    localStorage.setItem('quizzy_theme', next);
  });
})();
