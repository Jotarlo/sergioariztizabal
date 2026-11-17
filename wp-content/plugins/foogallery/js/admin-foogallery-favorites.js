/* global jQuery */

( function ( $ ) {
	'use strict';

	$( function () {
		const namespace = '.foogalleryFavorites';
		const button = '.foogallery-favorite';
		const fields = () => $( '[data-foogallery-favorites]' );
		let $shared = $( '#foogallery-shared-favorites' );

		function ensureShared() {
			const $fields = fields();
			if ( $shared.length || ! $fields.length ) return;
			$shared = $(
				'<input type="hidden" id="foogallery-shared-favorites">'
			)
				.attr(
					'name',
					(
						$fields.first().attr( 'name' ) ||
						'_foogallery_settings[favorite_ids]'
					).replace( /\[[^\]]+\]$/, '[favorite_ids]' )
				)
				.val( $fields.first().val() || '' );
			const $form = $fields.first().closest( 'form' );
			$shared.appendTo( $form.length ? $form : $( 'body' ) );
		}

		ensureShared();

		function sync( value ) {
			ensureShared();
			$shared.val( value );
			fields().val( value );
		}

		function field() {
			const template = $( '#FooGallerySettings_GalleryTemplate' ).val();
			return fields()
				.filter( function () {
					return this.dataset.foogalleryFavorites === template;
				} )
				.first();
		}

		function refresh() {
			const $field = field();
			if ( $field.length && $shared.length && $field.val() !== $shared.val() ) {
				$field.val( $shared.val() );
			}
			const ids = new Set( String( $field.val() || '' ).split( ',' ) );
			$( '.foogallery-attachments-list li[data-attachment-id]' ).each(
				function () {
					const $item = $( this );
					let $button = $item.find( button );
					if ( ! $field.length ) {
						$button.remove();
						return;
					}
					if ( ! $button.length ) {
						$button = $(
							'<a class="favorite foogallery-favorite" href="#" role="button"><span class="dashicons" aria-hidden="true"></span></a>'
						);
						$item.find( '.attachment-preview' ).append( $button );
					}
					const selected = ids.has(
						String( $item.data( 'attachment-id' ) )
					);
					const label = $field.attr(
						selected ? 'data-selected-label' : 'data-label'
					);
					$button.attr( {
						'aria-pressed': String( selected ),
						'aria-label': label,
						title: label,
					} );
					$button.attr(
						'aria-disabled',
						String( !! $field.prop( 'disabled' ) )
					);
					$button
						.find( '.dashicons' )
						.toggleClass( 'dashicons-star-filled', selected )
						.toggleClass( 'dashicons-star-empty', ! selected );
				}
			);
		}

		if ( $shared.length ) sync( $shared.val() || '' );
		$( document )
			.off( namespace )
			.on( 'click' + namespace, button, function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				const $field = field();
				if ( ! $field.length || $field.prop( 'disabled' ) ) {
					return;
				}
				const id = String(
					$( this )
						.closest( '[data-attachment-id]' )
						.data( 'attachment-id' )
				);
				const ids = new Set(
					String( $field.val() || '' )
						.split( ',' )
						.filter( Boolean )
				);
				if ( ids.has( id ) ) {
					ids.delete( id );
				} else {
					ids.add( id );
				}
				sync( Array.from( ids ).join( ',' ) );
				$field.trigger( 'change' );
				refresh();
			} )
			.on( 'keydown' + namespace, button, function ( event ) {
				if ( event.key === ' ' ) {
					event.preventDefault();
					$( this ).trigger( 'click' );
				}
			} )
			.on(
				'change' + namespace,
				'[data-foogallery-favorites]',
				function () {
					sync( $( this ).val() || '' );
					refresh();
				}
			)
			.on(
				'change' + namespace,
				'#FooGallerySettings_GalleryTemplate',
				function () {
					window.setTimeout( refresh, 0 );
				}
			)
			.on( 'click' + namespace, '.foogallery-template-card', function () {
				window.setTimeout( refresh, 0 );
			} );

		$( 'body' )
			.off( namespace )
			.on(
				'foogallery-attachments-changed' + namespace + ' foogallery-gallery-settings-loaded' + namespace + ' foogallery-gallery-template-changed' + namespace,
				function () {
					ensureShared();
					window.setTimeout( refresh, 0 );
				}
			);
		refresh();
	} );
} )( jQuery );
