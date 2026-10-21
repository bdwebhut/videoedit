/* Local photo and contact dialogs. Reservation times are static design content. */
(() => {
  "use strict";
  const page = document.querySelector(".page-details");
  if (!page) return;
  const gallery = page.querySelector("#gallery-dialog");
  const delivery = page.querySelector("#delivery-dialog");
  const reservation = page.querySelector("#reservation-dialog");
  page.addEventListener("click", (event) => {
    if (event.target.closest("[data-gallery]")) gallery.showModal();
    if (event.target.closest("[data-delivery]")) delivery.showModal();
    if (event.target.closest("[data-reservation]")) reservation.showModal();
  });
})();
