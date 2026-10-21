/* Local selection and filtering of the supplied static map records. */
(() => {
  "use strict";
  const page = document.querySelector(".page-map");
  if (!page) return;
  const cards = [...page.querySelectorAll("[data-map-card]")];
  const markers = [...page.querySelectorAll("[data-marker-target]")];
  const status = page.querySelector("#map-status");
  const input = page.querySelector("#map-query");
  const filterDialog = page.querySelector("#map-filter-dialog");
  const cityDialog = page.querySelector("#map-city-dialog");
  const checkboxes = [...filterDialog.querySelectorAll('[name="cuisine"]')];
  const filterLabel = page.querySelector("[data-filter-label]");
  let cuisines = new Set();
  let query = "";
  let selected = null;
  const normalize = (value) =>
    value
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  function renderSelection() {
    cards.forEach((card) => {
      const active = card.dataset.mapCard === selected && !card.hidden;
      card.classList.toggle("is-selected", active);
      card
        .querySelector("[data-select-card]")
        .setAttribute("aria-pressed", String(active));
    });
    markers.forEach((marker) =>
      marker.setAttribute(
        "aria-pressed",
        String(!marker.hidden && marker.dataset.markerTarget === selected),
      ),
    );
  }
  function filterResults() {
    cards.forEach((card) => {
      const haystack = `${card.dataset.name} ${card.dataset.category} ${card.dataset.location} ${card.querySelector(".map-card__quote").textContent}`;
      card.hidden =
        (cuisines.size > 0 && !cuisines.has(card.dataset.category)) ||
        !normalize(haystack).includes(normalize(query));
    });
    markers.forEach(
      (marker) =>
        (marker.hidden = cards.find(
          (card) => card.dataset.mapCard === marker.dataset.markerTarget,
        ).hidden),
    );
    if (
      selected &&
      cards.find((card) => card.dataset.mapCard === selected).hidden
    )
      selected = null;
    renderSelection();
    const count = cards.filter((card) => !card.hidden).length;
    page.querySelector("#map-empty").hidden = count > 0;
    status.textContent = `${count} spot${count === 1 ? "" : "s"} trouvé${count === 1 ? "" : "s"}.`;
    filterLabel.textContent = cuisines.size
      ? `Filtres (${cuisines.size})`
      : "Filtres";
  }
  function selectCard(id, fromMarker) {
    const card = cards.find((card) => card.dataset.mapCard === id);
    if (!card || card.hidden) return;
    selected = id;
    renderSelection();
    status.textContent = `${card.dataset.name} · ${card.dataset.category} · 4.9`;
    if (fromMarker) {
      const panel = page.querySelector(".map-results");
      if (matchMedia("(min-width:64rem)").matches)
        panel.scrollTo({
          top: Math.max(0, card.offsetTop - cards[0].offsetTop - 8),
          behavior: matchMedia("(prefers-reduced-motion: reduce)").matches
            ? "auto"
            : "smooth",
        });
      else card.scrollIntoView({ block: "nearest", behavior: "smooth" });
      card.querySelector("[data-select-card]").focus({ preventScroll: true });
    }
  }
  page.addEventListener("click", (event) => {
    const card = event.target.closest("[data-select-card]");
    const marker = event.target.closest("[data-marker-target]");
    if (card) selectCard(card.dataset.selectCard, false);
    if (marker) selectCard(marker.dataset.markerTarget, true);
  });
  page.querySelector("#map-search").addEventListener("submit", (event) => {
    event.preventDefault();
    query = input.value.trim();
    filterResults();
  });
  input.addEventListener("search", () => {
    query = input.value.trim();
    filterResults();
  });
  page
    .querySelector(".site-header__search")
    .addEventListener("click", () => input.focus());
  page.querySelector("[data-map-filter]").addEventListener("click", () => {
    checkboxes.forEach((box) => (box.checked = cuisines.has(box.value)));
    filterDialog.showModal();
  });
  page.querySelector("#map-filter-form").addEventListener("submit", (event) => {
    event.preventDefault();
    cuisines = new Set(
      checkboxes.filter((box) => box.checked).map((box) => box.value),
    );
    filterResults();
    filterDialog.close();
  });
  page.querySelector("[data-map-reset]").addEventListener("click", () => {
    cuisines.clear();
    query = "";
    input.value = "";
    checkboxes.forEach((box) => (box.checked = false));
    filterResults();
  });
  page
    .querySelector("[data-map-city]")
    .addEventListener("click", () => cityDialog.showModal());
  page.querySelector("#map-city-form").addEventListener("submit", (event) => {
    event.preventDefault();
    cityDialog.close();
  });
})();
