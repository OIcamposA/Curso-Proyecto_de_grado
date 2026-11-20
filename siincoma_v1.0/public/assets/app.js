(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

  // Vista previa y validación de imágenes antes de enviarlas
  document.querySelectorAll('input[type=file][data-preview]').forEach(inp => {
    const caja = $(inp.dataset.preview), msg = $(inp.dataset.msg), max = (+inp.dataset.max || 3) * 1048576;
    const original = caja.innerHTML;
    inp.addEventListener('change', () => {
      const f = inp.files[0];
      msg.textContent = '';
      if (!f) { caja.innerHTML = original; return; }
      if (!TIPOS.includes(f.type)) { msg.textContent = 'Use una imagen JPG, PNG o WEBP.'; inp.value = ''; caja.innerHTML = original; return; }
      if (f.size > max) { msg.textContent = `La imagen no debe superar ${inp.dataset.max} MB.`; inp.value = ''; caja.innerHTML = original; return; }
      const img = new Image();
      img.alt = 'Vista previa de la imagen seleccionada';
      img.onload = () => URL.revokeObjectURL(img.src);
      img.src = URL.createObjectURL(f);
      caja.replaceChildren(img);
    });
  });

  // Confirmación antes de acciones delicadas
  document.querySelectorAll('form[data-confirm]').forEach(form =>
    form.addEventListener('submit', e => { if (!confirm(form.dataset.confirm)) e.preventDefault(); }));

  // Mostrar u ocultar contraseña
  document.querySelectorAll('[data-toggle-pass]').forEach(btn => btn.addEventListener('click', () => {
    const campo = $(btn.dataset.togglePass);
    campo.type = campo.type === 'password' ? 'text' : 'password';
    btn.textContent = campo.type === 'password' ? 'Mostrar' : 'Ocultar';
  }));

  // Búsqueda instantánea en tablas
  document.querySelectorAll('input[data-filter]').forEach(inp => inp.addEventListener('input', () => {
    const t = inp.value.trim().toLowerCase();
    document.querySelectorAll(inp.dataset.filter + ' tbody tr').forEach(tr => { tr.hidden = t !== '' && !tr.textContent.toLowerCase().includes(t); });
  }));
})();
