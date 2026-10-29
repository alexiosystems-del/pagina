// Menú móvil
const toggle = document.querySelector('.menu-toggle');
const nav = document.getElementById('nav');
if (toggle && nav) {
  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('open', open);
  });
}

// Videos de YouTube: se cargan recién al hacer clic
document.querySelectorAll('.yt-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    const box = btn.closest('.yt');
    const iframe = document.createElement('iframe');
    iframe.src = `https://www.youtube-nocookie.com/embed/${box.dataset.id}?autoplay=1&rel=0`;
    iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen';
    iframe.allowFullscreen = true;
    iframe.title = btn.getAttribute('aria-label') || 'YouTube';
    box.replaceChildren(iframe);
  });
});

// Visor de fotos
const lightbox = document.querySelector('.lightbox');
if (lightbox) {
  const lbImg = lightbox.querySelector('img');
  document.querySelectorAll('.zoom').forEach((el) => {
    el.addEventListener('click', () => {
      lbImg.src = el.dataset.full;
      lightbox.showModal();
    });
  });
  lightbox.addEventListener('click', () => lightbox.close());
}

// Buscador del catálogo
const q = document.getElementById('q');
if (q) {
  const cards = [...document.querySelectorAll('#cards .card')];
  const empty = document.getElementById('empty');
  const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  const index = cards.map((c) => norm(c.dataset.search));
  q.addEventListener('input', () => {
    const terms = norm(q.value).split(/\s+/).filter(Boolean);
    let shown = 0;
    cards.forEach((c, i) => {
      const ok = terms.every((t) => index[i].includes(t));
      c.hidden = !ok;
      if (ok) shown++;
    });
    empty.hidden = shown > 0;
  });
}

// Formulario de contacto: arma el mensaje y abre WhatsApp o el programa de email
const form = document.querySelector('.wa-form');
if (form) {
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const name = form.elements.name.value.trim();
    const msg = form.elements.msg.value.trim();
    if (e.submitter && e.submitter.value === 'mail') {
      const body = msg + (name ? `\n\n${name}` : '');
      location.href = `mailto:${form.dataset.email}?subject=${encodeURIComponent(form.dataset.subject)}&body=${encodeURIComponent(body)}`;
      return;
    }
    const text = (name ? `${name}: ` : '') + msg;
    window.open(`https://wa.me/${form.dataset.number}?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
  });
}
