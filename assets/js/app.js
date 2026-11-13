(function () {
  var codeInput = document.getElementById('code');
  if (codeInput) {
    codeInput.addEventListener('input', function () {
      this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
  }

  var typeSelect = document.getElementById('type');
  if (typeSelect) {
    var mcqFields = document.getElementById('mcqFields');
    var tfFields = document.getElementById('tfFields');
    function toggleFields() {
      if (typeSelect.value === 'truefalse') {
        mcqFields.classList.add('hidden');
        tfFields.classList.remove('hidden');
      } else {
        mcqFields.classList.remove('hidden');
        tfFields.classList.add('hidden');
      }
    }
    typeSelect.addEventListener('change', toggleFields);
    toggleFields();
  }

  var penaltyToggle = document.getElementById('penaltyToggle');
  var penaltyInput = document.getElementById('penalty_points');
  if (penaltyToggle && penaltyInput) {
    function syncPenalty() { penaltyInput.disabled = !penaltyToggle.checked; }
    penaltyToggle.addEventListener('change', syncPenalty);
    syncPenalty();
  }
})();
