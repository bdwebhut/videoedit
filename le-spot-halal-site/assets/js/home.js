/* Homepage behavior. Markup uses data attributes; styling uses component classes. */
(() => {
  "use strict";

  const homepage = document.querySelector(".lsh-site.page-home");
  if (!homepage) return;

  const form = homepage.querySelector("[data-restaurant-search]");
  const queryInput = form.querySelector('[name="q"]');
  const cards = [...homepage.querySelectorAll("[data-restaurant]")];
  const status = homepage.querySelector("#results-status");
  const normalize = (value) =>
    value
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  const mapsUrl = (query) =>
    `https://www.google.com/maps/search/${encodeURIComponent(query)}`;

  function scrollToSection(id) {
    homepage.querySelector(id).scrollIntoView({
      behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches
        ? "instant"
        : "smooth",
      block: "start",
    });
  }

  function showInfo(title, copy, href, label) {
    homepage.dispatchEvent(
      new CustomEvent("lsh:show-info", {
        detail: { title, copy, href, label },
      }),
    );
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
      // Hide unmatched cards in the shared responsive grid.
      card.classList.toggle("is-filtered-out", !matches);
      if (matches) visibleCount += 1;
    });

    const plural = visibleCount === 1 ? "" : "s";
    status.textContent = normalizedQuery
      ? `${visibleCount} restaurant${plural} trouvé${plural}`
      : "";
    scrollToSection("#restaurants");
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    filterRestaurants();
  });

  // Delegate component actions without attaching listeners to every button.
  homepage.addEventListener("click", (event) => {
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
})();
