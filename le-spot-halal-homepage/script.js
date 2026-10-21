/* Homepage behavior. Markup uses data attributes; styling uses component classes. */
(() => {
  "use strict";

  const homepage = document.querySelector(".lsh-homepage");
  if (!homepage) return;

  const form = homepage.querySelector("[data-restaurant-search]");
  const queryInput = form.querySelector('[name="q"]');
  const cards = [...homepage.querySelectorAll("[data-restaurant]")];
  const status = homepage.querySelector("#results-status");
  const dialog = homepage.querySelector("#info-dialog");
  const dialogTitle = dialog.querySelector("#dialog-title");
  const dialogCopy = dialog.querySelector("#dialog-copy");
  const dialogLink = dialog.querySelector("#dialog-link");

  const normalize = (value) =>
    value
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  const mapsUrl = (query) =>
    `https://www.google.com/maps/search/${encodeURIComponent(query)}`;

  function scrollToSection(id) {
    homepage
      .querySelector(id)
      .scrollIntoView({ behavior: "smooth", block: "center" });
  }

  function showInfo(title, copy, href, label) {
    dialogTitle.textContent = title;
    dialogCopy.textContent = copy;
    dialogLink.href = href;
    dialogLink.textContent = label;
    dialog.showModal();
  }

  function filterRestaurants(query = queryInput.value) {
    const normalizedQuery = normalize(query.trim());
    let visibleCount = 0;

    cards.forEach((card) => {
      const matches =
        !normalizedQuery ||
        normalize(`${card.dataset.name} ${card.textContent}`).includes(
          normalizedQuery,
        );
      // Keep each card's place in the desktop grid, as in the original homepage.
      card.classList.toggle("is-filtered-out", !matches);
      if (matches) visibleCount += 1;
    });

    const plural = visibleCount === 1 ? "" : "s";
    status.textContent = normalizedQuery
      ? `${visibleCount} restaurant${plural} trouvé${plural}`
      : "";
    scrollToSection("#restaurants");
  }

  function showMap(city = "Genève") {
    showInfo(
      "Les spots sur la carte",
      "Retrouvez les restaurants halal autour de Genève et en France voisine.",
      mapsUrl(`restaurants halal ${city}`),
      "Ouvrir la carte",
    );
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    filterRestaurants();
  });

  // Delegate component actions without attaching listeners to every button.
  homepage.addEventListener("click", (event) => {
    const anchor = event.target.closest('a[href^="#"]');
    if (anchor && homepage.querySelector(anchor.hash)) {
      event.preventDefault();
      scrollToSection(anchor.hash);
      if (anchor.classList.contains("skip-link")) {
        const main = homepage.querySelector("#main-content");
        main.tabIndex = -1;
        main.focus({ preventScroll: true });
      }
      return;
    }

    const control = event.target.closest(
      "button[data-action], button[data-filter], button[data-city]",
    );
    if (!control) return;

    if (control.hasAttribute("data-filter")) {
      queryInput.value =
        control.dataset.filter === "burger"
          ? "Burgers"
          : control.textContent.trim();
      filterRestaurants(control.dataset.filter);
      return;
    }

    if (control.hasAttribute("data-city")) {
      const city = control.dataset.city;
      if (city === "Genève") {
        filterRestaurants("");
      } else {
        showInfo(
          city,
          `Découvrir les restaurants halal à ${city}.`,
          mapsUrl(`restaurants halal ${city}`),
          "Voir les restaurants sur la carte",
        );
      }
      return;
    }

    switch (control.dataset.action) {
      case "reset":
        queryInput.value = "";
        filterRestaurants("");
        break;
      case "map":
        showMap();
        break;
      case "restaurant": {
        const card = control.closest("[data-restaurant]");
        showInfo(
          card.dataset.name,
          card.textContent.replace(/\s+/g, " ").trim(),
          mapsUrl(`${card.dataset.name} restaurant Genève`),
          "Voir le restaurant",
        );
        break;
      }
    }
  });

  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
})();
