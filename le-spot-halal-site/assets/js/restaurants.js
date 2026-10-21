/* Local listing controls over the five supplied records; no remote data requests. */
(() => {
  "use strict";
  const page = document.querySelector(".page-restaurants");
  if (!page) return;
  const cards = [...page.querySelectorAll("[data-listing-card]")];
  const filters = [...page.querySelectorAll("[data-cuisine]")];
  const list = page.querySelector("#restaurant-list");
  const sort = page.querySelector("#listing-sort");
  const count = page.querySelector("#result-count");
  const empty = page.querySelector("#empty-results");
  const searchDialog = page.querySelector("#listing-search");
  const queryInput = page.querySelector("#listing-query");
  const selected = new Set();
  let query = "";
  const normalize = (value) =>
    value
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();

  function update() {
    const sorted = [...cards].sort((first, second) => {
      if (sort.value === "category")
        return first.dataset.category.localeCompare(
          second.dataset.category,
          "fr",
        );
      if (sort.value === "rating")
        return Number(second.dataset.rating) - Number(first.dataset.rating);
      return Number(first.dataset.order) - Number(second.dataset.order);
    });
    let visible = 0;
    sorted.forEach((card) => {
      const matchesCategory =
        !selected.size || selected.has(card.dataset.category);
      const matchesQuery = normalize(
        `${card.dataset.name} ${card.textContent}`,
      ).includes(normalize(query));
      card.hidden = !matchesCategory || !matchesQuery;
      if (!card.hidden) visible += 1;
      list.append(card);
    });
    filters.forEach((filter) => {
      filter.classList.remove("listing-filter--reference");
      filter.setAttribute(
        "aria-pressed",
        String(selected.has(filter.dataset.cuisine)),
      );
    });
    const plural = visible === 1 ? "" : "s";
    count.textContent = `${visible} spot${plural} trouvé${plural}.`;
    empty.hidden = visible > 0;
  }

  filters.forEach((filter) =>
    filter.addEventListener("click", () => {
      const cuisine = filter.dataset.cuisine;
      selected.has(cuisine) ? selected.delete(cuisine) : selected.add(cuisine);
      update();
    }),
  );
  page.querySelector("[data-reset-listing]").addEventListener("click", () => {
    selected.clear();
    query = "";
    queryInput.value = "";
    sort.value = "relevance";
    update();
  });
  sort.addEventListener("change", update);
  page
    .querySelector(".site-header__search")
    .addEventListener("click", (event) => {
      event.preventDefault();
      searchDialog.showModal();
      queryInput.focus();
    });
  page
    .querySelector("[data-listing-search]")
    .addEventListener("submit", (event) => {
      event.preventDefault();
      query = queryInput.value.trim();
      update();
      searchDialog.close();
    });
  // Preserve the initial 27-spot Figma label until a local control is used.
})();
