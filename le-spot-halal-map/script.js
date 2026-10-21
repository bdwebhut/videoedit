const cards = [...document.querySelectorAll('.map-card')];
const markers = [...document.querySelectorAll('.map-marker')];
const selectedCuisines = new Set();
const status = document.querySelector('#map-status');
let query = '';
const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
function filterResults() {
  cards.forEach(card => {
    card.hidden = (selectedCuisines.size > 0 && !selectedCuisines.has(card.dataset.category)) || !normalize(card.textContent).includes(normalize(query));
  });
  markers.forEach(marker => { marker.hidden = cards[Number(marker.dataset.cardTarget)].hidden; });
  const count = cards.filter(card => !card.hidden).length;
  document.querySelector('#empty-results').hidden = count > 0;
  status.textContent = `${count} spot${count === 1 ? '' : 's'} trouvé${count === 1 ? '' : 's'}.`;
  const filterCount = selectedCuisines.size;
  document.querySelector('#filter-button>span:last-child').textContent = filterCount ? `Filtres (${filterCount})` : 'Filtres';
}
function selectCard(index, scroll = false) {
  cards.forEach((card, i) => { card.classList.toggle('is-selected', i === index); card.setAttribute('aria-pressed', String(i === index)); });
  markers.forEach(marker => {
    const selected = Number(marker.dataset.cardTarget) === index;
    marker.classList.toggle('is-selected', selected);
    marker.setAttribute('aria-pressed', String(selected));
  });
  const card = cards[index];
  status.textContent = `${card.dataset.name} · ${card.dataset.category} · 4.9`;
  if (scroll) {
    const panel = document.querySelector('.cards-panel');
    panel.scrollTo({ top: Math.max(0, card.offsetTop - 10), behavior: 'smooth' });
  }
}
cards.forEach((card, index) => {
  card.addEventListener('click', () => selectCard(index));
  card.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); selectCard(index); }
  });
});
markers.forEach(marker => marker.addEventListener('click', () => selectCard(Number(marker.dataset.cardTarget), true)));
document.querySelector('#search-form').addEventListener('submit', event => {
  event.preventDefault(); query = document.querySelector('#query').value.trim(); filterResults();
});
document.querySelector('#query').addEventListener('search', () => {
  query = document.querySelector('#query').value.trim(); filterResults();
});
document.querySelector('.search-button').addEventListener('click', () => document.querySelector('#query').focus());
const filterDialog = document.querySelector('#filter-dialog');
document.querySelector('#filter-button').addEventListener('click', () => filterDialog.showModal());
document.querySelector('#filter-form').addEventListener('submit', event => {
  event.preventDefault(); selectedCuisines.clear();
  document.querySelectorAll('input[name=cuisine]:checked').forEach(input => selectedCuisines.add(input.value));
  filterResults(); filterDialog.close();
});
document.querySelector('#reset-filters').addEventListener('click', () => {
  selectedCuisines.clear(); query = ''; document.querySelector('#query').value = '';
  document.querySelectorAll('input[name=cuisine]').forEach(input => { input.checked = false; });
  filterResults();
});
const cityDialog = document.querySelector('#city-dialog');
document.querySelector('#city-button').addEventListener('click', () => cityDialog.showModal());
document.querySelector('#city-form').addEventListener('submit', event => {
  event.preventDefault(); document.querySelector('#city-button>span').textContent = document.querySelector('#city').value; cityDialog.close();
});
document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); }));
