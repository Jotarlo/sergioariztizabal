document.querySelectorAll('[data-saretos-media]').forEach((picker) => {
  const field = picker.querySelector('input');
  const image = picker.querySelector('img');
  const remove = picker.querySelector('[data-media-clear]');
  let library;

  picker.querySelector('[data-media-select]').addEventListener('click', () => {
    if (!library) {
      library = wp.media({
        title: 'Seleccionar imagen del reto',
        button: { text: 'Usar imagen' },
        library: { type: 'image' },
        multiple: false,
      });
      library.on('select', () => {
        const selection = library.state().get('selection').first().toJSON();
        field.value = selection.id;
        image.src = selection.sizes?.medium?.url || selection.url;
        image.hidden = false;
        remove.hidden = false;
      });
    }
    library.open();
  });

  remove.addEventListener('click', () => {
    field.value = '0';
    image.removeAttribute('src');
    image.hidden = true;
    remove.hidden = true;
  });
});
