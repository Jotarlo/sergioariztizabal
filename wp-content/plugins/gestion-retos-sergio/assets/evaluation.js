(() => {
  const report = document.querySelector('.saretos-evaluation');
  if (!report) return;
  const buttons = report.querySelectorAll('.saretos-grade button[type=submit]');
  const ranking = report.querySelector('[data-saretos-ranking]');
  let saving = false;

  report.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!form.matches('.saretos-grade')) return;
    event.preventDefault();
    if (saving) return;
    const row = form.closest('tr');
    const feedback = form.querySelector('.saretos-feedback');
    saving = true;
    buttons.forEach((button) => { button.disabled = true; });
    feedback.textContent = 'Guardando…';
    feedback.classList.remove('saretos-feedback-error');
    try {
      const response = await fetch(form.dataset.ajax, {
        method: 'POST',
        credentials: 'same-origin',
        body: new FormData(form),
      });
      const result = await response.json();
      if (!result.success) throw new Error(result.data?.message || 'No se pudo guardar.');
      form.elements.revision.value = result.data.revision;
      row.querySelector('[data-score]').textContent = result.data.score;
      row.querySelector('[data-review]').textContent = result.data.review;
      form.querySelectorAll('[data-criterion-author]').forEach((node) => {
        const author = result.data.authors?.[node.dataset.criterionAuthor];
        if (author) node.textContent = author;
      });
      ranking.innerHTML = result.data.ranking;
      feedback.textContent = result.data.message;
    } catch (error) {
      feedback.textContent = error instanceof SyntaxError || error instanceof TypeError
        ? 'No se pudo confirmar el guardado. Vuelve a cargar la página y verifica la inscripción.'
        : error.message;
      feedback.classList.add('saretos-feedback-error');
    } finally {
      saving = false;
      buttons.forEach((button) => { button.disabled = false; });
    }
  });
})();
