/* global jQuery */
/*
 * Pop-out gallery preview window.
 *
 * The gallery editor broadcasts its unsaved preview request on a channel named
 * after the gallery. This window replays that request against the same AJAX
 * endpoint and renders the result with its own viewport.
 */
( function ( $, window ) {
	'use strict';

	const config = window.FooGalleryPreviewWindow || {};
	const strings = config.strings || {};
	const galleryId = Number( config.galleryId ) || 0;
	const $content = $( '.foogallery-preview-window-content' );
	const $wrapper = $content.find( '.foogallery-preview-wrapper' );
	const $container = $content.find( '.foogallery_preview_container' );
	const viewports = [ 'desktop', 'tablet', 'mobile' ];
	let viewport = 'desktop';
	const $status = $( '.foogallery-preview-window-status' );
	const $width = $( '.foogallery-preview-window-width' );
	let channel = null;
	let request = null;
	let pendingRender = null;
	let requestId = 0;
	let helloTimer = 0;
	let auto = true;
	let stale = false;
	let failed = false;
	let editorConnected = false;
	let activeRevision = -1;
	let manage = false;

	function showMode() {
		$( '.foogallery-preview-window-auto' )
			.prop( 'checked', auto )
			.attr( 'aria-checked', auto ? 'true' : 'false' );
		let label = strings.live;
		if ( failed ) {
			label = strings.failed;
		} else if ( stale ) {
			label = strings.stale;
		} else if ( ! auto ) {
			label = strings.manual;
		}
		setStatus( label, auto && ! stale && ! failed ? 'live' : '' );
	}

	function cancelRender() {
		requestId++;
		// Ignore the active result without freeing its slot before it finishes.
		pendingRender = null;
		$content.removeClass( 'is-loading' );
	}

	function setStatus( text, state ) {
		$status
			.text( text || '' )
			.toggleClass( 'is-live', 'live' === state )
			.toggleClass( 'is-busy', 'busy' === state );
	}

	function showWidth() {
		$width.text( $wrapper.get( 0 ).clientWidth + 'px' );
	}

	function setBackground( color ) {
		if ( ! /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test( color ) ) {
			return;
		}
		window.document.body.style.setProperty(
			'--foogallery-preview-background',
			color
		);
		try {
			window.localStorage.setItem( 'foogalleryPreviewBackground', color );
		} catch {
			// Storage is optional; the choice just won't be remembered.
		}
	}

	// Mirrors the editor: narrow the wrapper and tell FooGallery to use mobile options.
	function setViewport( next ) {
		if ( viewports.indexOf( next ) === -1 ) {
			return;
		}
		const changed = next !== viewport;
		viewport = next;
		$wrapper
			.removeClass( 'viewport-desktop viewport-tablet viewport-mobile' )
			.addClass( 'viewport-' + viewport );
		$( '.foogallery-preview-window-viewport' ).each( function () {
			const active = $( this ).data( 'viewport' ) === viewport;
			$( this )
				.toggleClass( 'is-active', active )
				.attr( 'aria-pressed', active ? 'true' : 'false' );
		} );
		try {
			window.localStorage.setItem(
				'foogalleryPreviewViewport',
				viewport
			);
		} catch {
			// Storage is optional; the choice just won't be remembered.
		}
		if ( changed && auto && ! manage ) {
			initGallery();
		} else if ( changed ) {
			stale = true;
			showMode();
		}
		// The wrapper animates its width, so measure once it settles.
		window.setTimeout( showWidth, 350 );
	}

	function destroyGallery() {
		const $gallery = $container.find( '.foogallery' );
		if ( $gallery.length && $.fn.foogallery ) {
			try {
				$gallery.foogallery( 'destroy' );
			} catch {
				// A half-initialised gallery has nothing to tear down.
			}
		}
		window[ 'foogallery-gallery-' + galleryId + '_items' ] = null;
	}

	function initGallery() {
		const $gallery = $container.find( '.foogallery' );
		if ( ! $gallery.length || ! $gallery.data( 'fg-common-fields' ) ) {
			return;
		}
		// Mobile options are read while the template is constructed.
		if ( window.FooGallery ) {
			window.FooGallery.isMobile = 'mobile' === viewport;
		}
		$gallery.foogallery( {}, function () {
			// Without a lightbox, thumbnails would navigate away from the preview.
			if ( ! $gallery.data( 'foogallery-lightbox' ) ) {
				$container
					.find( '.fg-thumb' )
					.off( 'click.foogallery' )
					.on( 'click', function ( event ) {
						event.preventDefault();
					} );
			}
		} );
	}

	function render(
		data,
		editorRequestId,
		snapshot,
		snapshotStale,
		revision
	) {
		activeRevision = revision;
		const id = ++requestId;
		if ( request ) {
			pendingRender = [
				data,
				editorRequestId,
				snapshot,
				snapshotStale,
				revision,
			];
			return;
		}
		setStatus( strings.loading, 'busy' );
		$content.addClass( 'is-loading' );
		request = $.ajax( {
			type: 'POST',
			url: config.ajaxUrl,
			data,
			cache: false,
		} )
			.done( function ( html ) {
				if ( id !== requestId ) {
					return;
				}
				destroyGallery();
				$container.html( html );
				initGallery();
				if ( ! snapshot || ! snapshotStale ) {
					stale = false;
					failed = false;
				}
				showMode();
				if ( snapshot && ! snapshotStale ) {
					channel.postMessage( {
						type: 'snapshot-complete',
						revision,
					} );
				} else if ( editorRequestId !== undefined && ! snapshot ) {
					channel.postMessage( {
						type: 'preview-complete',
						requestId: editorRequestId,
						revision,
					} );
				}
			} )
			.fail( function ( xhr, textStatus ) {
				if ( id === requestId && 'abort' !== textStatus ) {
					stale = true;
					failed = true;
					showMode();
					if ( snapshot ) {
						channel.postMessage( {
							type: 'snapshot-failed',
							revision,
						} );
					} else if ( editorRequestId !== undefined ) {
						channel.postMessage( {
							type: 'preview-failed',
							requestId: editorRequestId,
							revision,
						} );
					}
				}
			} )
			.always( function () {
				$content.removeClass( 'is-loading' );
				request = null;
				if ( pendingRender ) {
					const next = pendingRender;
					pendingRender = null;
					render( ...next );
				}
			} );
	}

	// Ask the editor for its latest unsaved data. Keep the last render if offline.
	function hello( explicit ) {
		window.clearTimeout( helloTimer );
		editorConnected = false;
		channel.postMessage( { type: explicit ? 'refresh' : 'hello' } );
		helloTimer = window.setTimeout( function () {
			if ( ! editorConnected ) {
				setStatus( strings.noEditor );
			}
		}, 2000 );
	}

	$( function () {
		let saved = '';
		let background = '#ffffff';
		try {
			saved = window.localStorage.getItem( 'foogalleryPreviewViewport' );
			const stored = window.localStorage.getItem(
				'foogalleryPreviewBackground'
			);
			if ( /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test( stored ) ) {
				background = stored;
			}
		} catch {
			// Storage is optional.
		}
		setBackground( background );
		$( '#foogallery-preview-window-background' )
			.val( background )
			.wpColorPicker( {
				change( event, ui ) {
					setBackground( ui.color.toString() );
				},
				clear() {
					setBackground( '#ffffff' );
				},
			} )
			.closest( '.wp-picker-container' )
			.find( '.wp-color-result-text' )
			.text( strings.background );
		setViewport( saved || 'desktop' );
		showWidth();
		$( window ).on( 'resize', showWidth );
		$( '.foogallery-preview-window-viewport' ).on( 'click', function () {
			setViewport( $( this ).data( 'viewport' ) );
		} );

		if ( ! galleryId || ! window.BroadcastChannel ) {
			setStatus( strings.unsupported );
			return;
		}

		channel = new window.BroadcastChannel(
			'foogallery-preview-' + galleryId
		);
		channel.onmessage = function ( event ) {
			const message = event.data || {};
			if ( 'state' === message.type ) {
				editorConnected = true;
				window.clearTimeout( helloTimer );
				const wasAuto = auto;
				auto = !! message.auto;
				manage = !! message.manage;
				stale = !! message.stale;
				failed = !! message.failed;
				if (
					manage ||
					( wasAuto && ! auto ) ||
					( stale && message.revision > activeRevision )
				) {
					cancelRender();
				}
				showMode();
			} else if (
				'preview' === message.type &&
				! manage &&
				Array.isArray( message.data ) &&
				( auto || message.explicit )
			) {
				editorConnected = true;
				window.clearTimeout( helloTimer );
				render(
					message.data,
					message.requestId,
					message.snapshot,
					message.snapshotStale,
					message.revision
				);
			} else if ( 'editor-closed' === message.type ) {
				setStatus( strings.reconnecting, 'busy' );
			} else if ( 'ping' === message.type ) {
				// A freshly loaded editor is looking for an open window.
				hello( false );
			}
		};

		// Lets the editor resume its inline preview. Unload messages can be lost,
		// so a heartbeat tells the editor the window is still open.
		$( window ).on( 'pagehide', function () {
			channel.postMessage( { type: 'window-closed' } );
		} );
		window.setInterval( function () {
			channel.postMessage( { type: 'alive' } );
		}, 2000 );

		$( '.foogallery-preview-window-auto' ).on( 'change', function () {
			auto = this.checked;
			if ( ! auto ) {
				cancelRender();
			}
			showMode();
			channel.postMessage( { type: 'set-auto', enabled: auto } );
		} );
		$( '.foogallery-preview-window-refresh' ).on( 'click', function () {
			hello( true );
		} );
		setStatus( strings.waiting, 'busy' );
		hello( false );
	} );
} )( jQuery, window );
