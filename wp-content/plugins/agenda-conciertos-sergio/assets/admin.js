jQuery(function ($) {
  var frame;
  $('#sa-select-background').on('click', function (event) {
    event.preventDefault();
    if (frame) { frame.open(); return; }
    frame = wp.media({ title: 'Seleccionar fondo de la agenda', button: { text: 'Usar esta imagen' }, multiple: false });
    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      $('#sa_background_id').val(attachment.id);
      $('#sa-background-preview').html('<img src="' + attachment.url + '" alt="">');
    });
    frame.open();
  });
  $('#sa-remove-background').on('click', function (event) { event.preventDefault(); $('#sa_background_id').val(''); $('#sa-background-preview').empty(); });
});
