/* Shared navigation and information dialog. No backend or account state. */
(() => {
  "use strict";
  const site = document.querySelector(".lsh-site");
  if (!site) return;
  const dialog = site.querySelector("#info-dialog");
  function showInfo({ title, copy, href, label }) {
    if (!dialog) return;
    dialog.querySelector("#dialog-title").textContent = title;
    dialog.querySelector("#dialog-copy").textContent = copy;
    const link = dialog.querySelector("#dialog-link");
    link.href = href;
    link.textContent = label;
    dialog.showModal();
  }
  site.addEventListener("lsh:show-info", (event) => showInfo(event.detail));
  site.querySelectorAll("dialog").forEach((element) => {
    element.addEventListener("click", (event) => {
      if (event.target === element) element.close();
    });
  });

  const toggle = site.querySelector("[data-menu-toggle]");
  const navigation = site.querySelector("[data-navigation]");
  if (!toggle || !navigation) return;

  function closeMenu({ restoreFocus = false } = {}) {
    navigation.classList.remove("is-open");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", "Ouvrir le menu");
    if (restoreFocus) toggle.focus();
  }

  toggle.addEventListener("click", () => {
    const open = toggle.getAttribute("aria-expanded") !== "true";
    toggle.setAttribute("aria-expanded", String(open));
    toggle.setAttribute(
      "aria-label",
      open ? "Fermer le menu" : "Ouvrir le menu",
    );
    navigation.classList.toggle("is-open", open);
  });
  navigation.addEventListener("click", (event) => {
    if (event.target.closest("a, button")) closeMenu();
  });
  document.addEventListener("keydown", (event) => {
    if (
      event.key === "Escape" &&
      toggle.getAttribute("aria-expanded") === "true"
    ) {
      closeMenu({ restoreFocus: true });
    }
  });
  document.addEventListener("click", (event) => {
    if (
      !event.target.closest(".site-header") ||
      event.target.closest(".site-header__search")
    )
      closeMenu();
  });
  // Reset the collapsed menu after crossing the desktop breakpoint.
  window
    .matchMedia("(min-width: 64rem)")
    .addEventListener("change", () => closeMenu());
  site.classList.add("is-enhanced");
})();
