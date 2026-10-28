// Confirmación antes de borrar
document.querySelectorAll('form[data-confirm]').forEach((f) => {
  f.addEventListener('submit', (e) => {
    if (!confirm(f.dataset.confirm)) e.preventDefault();
  });
});

// Filtro de la lista de productos
const filtro = document.getElementById('filtro');
if (filtro) {
  const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  const rows = [...document.querySelectorAll('#products > li')];
  filtro.addEventListener('input', () => {
    const q = norm(filtro.value.trim());
    rows.forEach((r) => { r.hidden = q !== '' && !norm(r.dataset.search).includes(q); });
  });
}

// El campo de precio crece con su contenido
document.querySelectorAll('.price-form textarea').forEach((t) => {
  const fit = () => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; };
  t.addEventListener('input', fit);
  fit();
});

// Aviso si se sale del formulario sin guardar
const edit = document.querySelector('form.edit');
if (edit) {
  let dirty = false;
  edit.addEventListener('input', () => { dirty = true; });
  edit.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', (e) => { if (dirty) e.preventDefault(); });
}
