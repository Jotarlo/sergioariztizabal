(function () {
	'use strict';

	var selectButton = document.getElementById('sa-ul-select-image');
	var removeButton = document.getElementById('sa-ul-remove-image');
	var imageIdField = document.getElementById('sa-ul-image-id');
	var preview = document.getElementById('sa-ul-image-preview');
	var frame;

	if (!selectButton || !imageIdField || !preview || typeof wp === 'undefined' || !wp.media) {
		return;
	}

	selectButton.addEventListener('click', function (event) {
		event.preventDefault();

		if (frame) {
			frame.open();
			return;
		}

		frame = wp.media({
			title: 'Seleccionar foto del lanzamiento',
			button: { text: 'Usar esta foto' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

			imageIdField.value = attachment.id;
			preview.innerHTML = '<img src="' + imageUrl.replace(/"/g, '&quot;') + '" alt="Vista previa de la foto seleccionada" />';
			if (removeButton) {
				removeButton.hidden = false;
			}
		});

		frame.open();
	});

	if (removeButton) {
		removeButton.addEventListener('click', function (event) {
			event.preventDefault();
			imageIdField.value = '';
			preview.innerHTML = '';
			removeButton.hidden = true;
		});
	}
}());
