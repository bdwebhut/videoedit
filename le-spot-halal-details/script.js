const gallery = document.querySelector('#gallery-dialog');
document.querySelectorAll('[data-gallery]').forEach(button => button.addEventListener('click', () => gallery.showModal()));
const delivery = document.querySelector('#delivery-dialog');
document.querySelector('#order-delivery').addEventListener('click', () => delivery.showModal());
document.querySelector('.search-button').addEventListener('click', () => {
  window.location.href = '../le-spot-halal-restaurants/index.html';
});
document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('click', event => {
  if (event.target === dialog) dialog.close();
}));
