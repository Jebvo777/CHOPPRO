(() => {
  const body = document.body;
  const key = 'choppro-acceptance-v3:' + body.dataset.repository;
  const boxes = [...document.querySelectorAll('[data-accept]')];
  const read = () => { try { return JSON.parse(localStorage.getItem(key) || '{}'); } catch { return {}; } };
  const save = value => { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* Private mode or quota. */ } };
  function progress() {
    const state = read();
    let checked = 0;
    for (const box of boxes) {
      box.checked = state[box.dataset.accept]?.revision === box.dataset.revision
        && state[box.dataset.accept]?.checked === true;
      if (box.checked) checked++;
    }
    const total = boxes.length;
    document.querySelectorAll('[data-progress-bar]').forEach(el => el.style.width = (total ? checked / total * 100 : 0) + '%');
    document.querySelectorAll('[data-progress-text]').forEach(el => el.textContent = checked + ' / ' + total);
  }
  boxes.forEach(box => box.addEventListener('change', () => {
    const state = read();
    state[box.dataset.accept] = {checked: box.checked, revision: box.dataset.revision};
    save(state); progress();
  }));
  document.querySelector('[data-reset-accept]')?.addEventListener('click', () => {
    if (confirm('Сбросить отметки приемки в этом браузере?')) { save({}); progress(); }
  });
  progress();
  const search = document.querySelector('[data-req-search]');
  const category = document.querySelector('[data-req-cat]');
  const stage = document.querySelector('[data-req-stage]');
  function filter() {
    let visible = 0;
    document.querySelectorAll('[data-req-row]').forEach(row => {
      const query = (search?.value || '').toLocaleLowerCase('ru');
      const stages = [];
      for (const match of row.dataset.stage.matchAll(/(\d+)(?:\s*[-–]\s*(\d+))?/g)) {
        const first = Number(match[1]), last = Number(match[2] || match[1]);
        if (last >= first && last - first <= 20) for (let i = first; i <= last; i++) stages.push(String(i));
      }
      row.hidden = !!((query && !row.textContent.toLocaleLowerCase('ru').includes(query))
        || (category?.value && row.dataset.cat !== category.value)
        || (stage?.value && !stages.includes(stage.value)));
      if (!row.hidden) visible++;
    });
    const counter = document.querySelector('[data-req-count]');
    if (counter) counter.textContent = 'Показано: ' + visible;
  }
  [search, category, stage].forEach(el => el?.addEventListener('input', filter));
  document.querySelector('[data-doc-search]')?.addEventListener('input', event => {
    const query = event.target.value.toLocaleLowerCase('ru');
    document.querySelectorAll('[data-doc]').forEach(el => el.hidden = !el.textContent.toLocaleLowerCase('ru').includes(query));
  });
  // A visible tab checks itself; acceptance input is preserved until the user opens the new version.
  let checking = false;
  async function checkVersion() {
    if (document.hidden || checking) return;
    checking = true;
    try {
      const response = await fetch('api.php', {cache: 'no-store', credentials: 'same-origin'});
      if (!response.ok) return;
      const status = await response.json();
      if (status.sha && status.sha !== body.dataset.sha) {
        document.querySelector('[data-sync-notice]').hidden = false;
      }
    } catch { /* The saved page remains usable during an outage. */ }
    finally { checking = false; }
  }
  setInterval(checkVersion, 60000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) checkVersion(); });
})();
