(() => {
  const list = document.querySelector('[data-election-list]');
  if (!list) return;
  const form = list.querySelector('[data-election-filters]');
  const grid = list.querySelector('[data-election-grid]');
  const cards = [...list.querySelectorAll('[data-election-card]')];
  const visibleCount = list.querySelector('[data-visible-count]');
  const visibleBadge = list.querySelector('[data-visible-badge]');
  const empty = list.querySelector('[data-filter-empty]');
  const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');

  function applyFilters() {
    const data = new FormData(form);
    const query = normalize(String(data.get('query') || '').trim());
    const status = String(data.get('status') || '');
    const organization = String(data.get('organization') || '');
    const sort = String(data.get('sort') || 'recent');
    let count = 0;
    cards.forEach(card => {
      const matches = (!query || normalize(card.dataset.search).includes(query))
        && (!status || card.dataset.status === status)
        && (!organization || card.dataset.organization === organization);
      card.hidden = !matches;
      if (matches) count += 1;
    });
    const sorted = [...cards].sort((a, b) => {
      if (sort === 'name') return normalize(a.dataset.search).localeCompare(normalize(b.dataset.search), 'es');
      if (sort === 'start_asc') return a.dataset.start.localeCompare(b.dataset.start);
      return b.dataset.created.localeCompare(a.dataset.created) || Number(b.dataset.electionId || 0) - Number(a.dataset.electionId || 0);
    });
    sorted.forEach(card => grid.insertBefore(card, empty));
    visibleCount.textContent = String(count);
    visibleBadge.textContent = String(count);
    empty.hidden = count !== 0;
  }

  form.addEventListener('input', applyFilters);
  form.addEventListener('change', applyFilters);
  form.addEventListener('reset', () => window.setTimeout(applyFilters));
  applyFilters();
})();
