document.querySelector('.menu-toggle')?.addEventListener('click', function () {
  const expanded = this.getAttribute('aria-expanded') !== 'true';
  this.setAttribute('aria-expanded', String(expanded));
  document.querySelector('#navigation').classList.toggle('open', expanded);
});
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
