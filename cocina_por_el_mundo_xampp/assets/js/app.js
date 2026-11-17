document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('.mobile-menu');
  const nav = document.querySelector('.main-nav');
  if (menuButton && nav) {
    menuButton.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
      const message = element.getAttribute('data-confirm') || '¿Continuar?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-preview]').forEach((input) => {
    input.addEventListener('change', () => {
      const target = document.querySelector(input.getAttribute('data-preview'));
      const file = input.files && input.files[0];
      if (!target || !file) return;
      const reader = new FileReader();
      reader.onload = () => {
        target.src = String(reader.result);
        target.hidden = false;
      };
      reader.readAsDataURL(file);
    });
  });

  document.querySelectorAll('form[data-disable-on-submit]').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"], input[type="submit"]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Procesando…';
      }
    });
  });
});

// Pequeña animación de progreso para la pantalla del recomendador.
document.addEventListener('DOMContentLoaded', () => {
  const result = document.querySelector('[data-recommend-result]');
  if (!result) return;
  const loader = result.querySelector('.recommend-loader');
  const card = result.querySelector('.recommend-card');
  window.setTimeout(() => {
    if (loader) loader.hidden = true;
    if (card) card.hidden = false;
  }, 1700);
});
