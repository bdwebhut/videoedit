const filters = [...document.querySelectorAll('[data-cuisine]')];
const cards = [...document.querySelectorAll('.restaurant-card')];
const selected = new Set();
let query = '';
const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
function update() {
  const list = document.querySelector('#restaurant-list');
  const order = document.querySelector('#sort').value;
  const sorted = [...cards].sort((a, b) => order === 'category'
    ? a.dataset.category.localeCompare(b.dataset.category, 'fr')
    : order === 'rating' ? Number(b.dataset.rating) - Number(a.dataset.rating)
    : Number(a.dataset.order) - Number(b.dataset.order));
  let count = 0;
  sorted.forEach(card => {
    card.hidden = (selected.size > 0 && !selected.has(card.dataset.category)) || !normalize(card.textContent).includes(normalize(query));
    if (!card.hidden) count += 1;
    list.append(card);
  });
  filters.forEach(button => {
    button.classList.remove('is-reference-selected');
    button.setAttribute('aria-pressed', String(selected.has(button.dataset.cuisine)));
  });
  document.querySelector('#result-count').textContent = `${count} spot${count === 1 ? '' : 's'} trouvé${count === 1 ? '' : 's'}.`;
  document.querySelector('#empty-results').hidden = count > 0;
}
filters.forEach(button => button.addEventListener('click', () => {
  const category = button.dataset.cuisine;
  selected.has(category) ? selected.delete(category) : selected.add(category);
  update();
}));
document.querySelector('#reset-filters').addEventListener('click', () => {
  selected.clear(); query = ''; document.querySelector('#query').value = '';
  document.querySelector('#sort').value = 'relevance'; update();
});
document.querySelector('#sort').addEventListener('change', update);
const mapDialog = document.querySelector('#map-dialog');
document.querySelectorAll('[data-map]').forEach(button => button.addEventListener('click', () => mapDialog.showModal()));
const searchDialog = document.querySelector('#search-dialog');
document.querySelector('.search-button').addEventListener('click', () => searchDialog.showModal());
document.querySelector('#search-form').addEventListener('submit', event => {
  event.preventDefault(); query = document.querySelector('#query').value.trim(); update(); searchDialog.close();
});
document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('click', event => {
  if (event.target === dialog) dialog.close();
}));
// Figma's initial label is 27 spots; only its five supplied cards are available locally.
// Counts are recomputed from the actual local records after using a control.
