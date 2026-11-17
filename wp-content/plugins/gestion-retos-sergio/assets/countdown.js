(() => {
  const timers = document.querySelectorAll('[data-saretos-countdown]');
  if (!timers.length) return;
  const units = ['days', 'hours', 'minutes', 'seconds'];
  function update(timer) {
    const end = Number(timer.dataset.end);
    let remaining = Math.max(0, Math.floor((end - Date.now()) / 1000));
    if (!Number.isFinite(end) || remaining <= 0) {
      timer.closest('.saretos-band')?.remove();
      return false;
    }
    const values = {
      days: Math.floor(remaining / 86400),
      hours: Math.floor((remaining % 86400) / 3600),
      minutes: Math.floor((remaining % 3600) / 60),
      seconds: remaining % 60,
    };
    units.forEach((unit) => {
      const output = timer.querySelector(`[data-countdown-${unit}]`);
      if (output) output.textContent = String(values[unit]).padStart(2, '0');
    });
    return true;
  }
  timers.forEach((timer) => {
    if (!update(timer)) return;
    const interval = window.setInterval(() => {
      if (!update(timer)) window.clearInterval(interval);
    }, 1000);
  });
})();
