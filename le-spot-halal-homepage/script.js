// Local homepage interactions. No backend or new pages are assumed.
const node = id => document.querySelector(`[data-node-id="${id}"]`);
const dialog = document.querySelector('#info-dialog');
function showInfo(title, copy, href, label = 'En savoir plus') {
  document.querySelector('#dialog-title').textContent = title;
  document.querySelector('#dialog-copy').textContent = copy;
  const link = document.querySelector('#dialog-link');
  link.href = href;
  link.textContent = label;
  dialog.showModal();
}
function action(id, label, callback) {
  const element = node(id);
  if (!element) return;
  element.role = 'button';
  element.tabIndex = 0;
  element.setAttribute('aria-label', label);
  element.addEventListener('click', callback);
  element.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      callback();
    }
  });
}
function scrollToSection(id) {
  document.querySelector(id).scrollIntoView({ behavior: 'smooth', block: 'center' });
}
// Use the direct children of the original Figma card container.
const restaurantCards = [...document.querySelector('#restaurants').children];
function search(query = document.querySelector('#restaurant-query').value) {
  const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const q = normalize(query.trim());
  let visible = 0;
  restaurantCards.forEach(card => {
    const match = !q || normalize(card.textContent).includes(q);
    card.style.visibility = match ? 'visible' : 'hidden';
    if (match) visible += 1;
  });
  document.querySelector('#results-status').textContent = q
    ? `${visible} restaurant${visible === 1 ? '' : 's'} trouvé${visible === 1 ? '' : 's'}` : '';
  scrollToSection('#restaurants');
}
action('2:358', 'Rechercher un restaurant', () => search());
document.querySelector('#restaurant-query').addEventListener('keydown', event => {
  if (event.key === 'Enter') search();
});
action('2:320', 'Afficher tous les restaurants', () => {
  document.querySelector('#restaurant-query').value = '';
  search('');
});
['2:452', '2:343', '2:145'].forEach(id => action(id, 'Voir les restaurants', () => scrollToSection('#search')));
action('2:454', 'À propos du Spot Halal', () => scrollToSection('#about'));
action('2:134', 'Nous contacter', () => scrollToSection('#contact'));
['2:131', '2:434', '2:460'].forEach(id => action(id, 'Ajouter mon restaurant', () => {
  window.location.href = 'mailto:hello@lespothalal.com?subject=Ajouter%20mon%20restaurant';
}));
['2:394', '2:380'].forEach(id => action(id, 'Écrire au Spot Halal', () => {
  window.location.href = 'mailto:hello@lespothalal.com';
}));
['2:453', '2:146'].forEach(id => action(id, 'Voir la carte', () => {
  showInfo('Les spots sur la carte', 'Retrouvez les restaurants halal autour de Genève et en France voisine.',
    'https://www.google.com/maps/search/restaurants+halal+Gen%C3%A8ve/', 'Ouvrir la carte');
}));
const city = { '2:326': 'Ferney-Voltaire', '2:327': 'Genève', '2:328': 'Annemasse' };
Object.entries(city).forEach(([id, name]) => action(id, name, () => {
  // The design provides only the Genève restaurant data; other city data needs an API.
  if (name === 'Genève') {
    search('');
  } else {
    showInfo(name, `Découvrir les restaurants halal à ${name}.`,
      `https://www.google.com/maps/search/${encodeURIComponent(`restaurants halal ${name}`)}`, 'Voir les restaurants sur la carte');
  }
}));
for (let id = 331; id <= 339; id += 1) {
  const element = node(`2:${id}`);
  const category = element.textContent.trim();
  action(`2:${id}`, `Rechercher : ${category}`, () => {
    document.querySelector('#restaurant-query').value = category;
    search(category === 'Burgers' ? 'burger' : category);
  });
}
restaurantCards.forEach(card => {
  const heading = card.querySelector('[data-name="Heading 3"]');
  const name = heading?.querySelector('p')?.parentElement.textContent.replace(/\s+/g, ' ').trim();
  if (!name) return;
  action(card.dataset.nodeId, `Découvrir ${name}`, () => {
    showInfo(name, card.textContent.replace(/\s+/g, ' ').trim(),
      `https://www.google.com/maps/search/${encodeURIComponent(`${name} restaurant Genève`)}`, 'Voir le restaurant');
  });
});
const footer = { '2:438': '#search', '2:440': '#search', '2:442': '#about' };
Object.entries(footer).forEach(([id, target]) => action(id, node(id).textContent.trim(), () => scrollToSection(target)));
dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
