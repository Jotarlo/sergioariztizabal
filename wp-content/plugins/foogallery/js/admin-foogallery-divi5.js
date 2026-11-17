/* Native Divi 5 API usage adapted from Elegant Themes' GPL2 module examples. */
( function ( window, document ) {
	'use strict';

	// Capture synchronously: currentScript is null once the async request resolves.
	const configUrl = new URL( document.currentScript.src ).searchParams.get(
		'config'
	);
	const configPromise = fetch( configUrl, {
		credentials: 'same-origin',
	} ).then( async ( response ) => {
		if ( ! response.ok ) {
			throw new Error( 'FooGallery configuration unavailable' );
		}
		const result = await response.json();
		if ( ! result.success ) {
			throw new Error( 'FooGallery configuration denied' );
		}
		return result.data;
	} );
	// Handle rejection even when the store hook has not fired yet.
	configPromise.catch( () => {} );
	let registered = false;
	window.vendor.wp.hooks.addAction(
		'divi.moduleLibrary.registerModuleLibraryStore.after',
		'foogallery/native-module',
		async () => {
			try {
				const config = await configPromise;
				if ( registered ) {
					return;
				}
				const React = window.vendor.React;
				const api = window.divi.module;
				const h = React.createElement;
				config.metadata.attributes.galleryId.settings.innerContent.item.component.props.options =
					config.options;

				function styles( props ) {
					return h(
						api.StyleContainer,
						{
							mode: props.mode,
							state: props.state,
							noStyleTag: props.noStyleTag,
						},
						props.elements.style( {
							attrName: 'module',
							styleProps: {
								defaultPrintedStyleAttrs:
									props.defaultPrintedStyleAttrs?.module
										?.decoration,
								disabledOn: {
									disabledModuleVisibility:
										props.settings
											?.disabledModuleVisibility,
								},
							},
						} ),
						h( api.CssStyle, {
							selector: props.orderClass,
							attr: props.attrs.css,
							cssFields: {},
						} )
					);
				}

				function scriptData( props ) {
					return h(
						React.Fragment,
						null,
						props.elements.scriptData( { attrName: 'module' } )
					);
				}

				function edit( props ) {
					const value =
						props.attrs.galleryId?.innerContent?.desktop?.value;
					const valid = /^[1-9][0-9]*$/.test( String( value ) );
					const preview = new URL( config.previewUrl );
					preview.searchParams.set( 'gallery_id', value );
					return h(
						api.ModuleContainer,
						{
							attrs: props.attrs,
							elements: props.elements,
							id: props.id,
							name: props.name,
							isLooped: props.isLooped,
							loopIndex: props.loopIndex,
							stylesComponent: styles,
							scriptDataComponent: scriptData,
						},
						props.elements.styleComponents( {
							attrName: 'module',
						} ),
						valid
							? h( 'iframe', {
									key: String( value ),
									src: preview.href,
									title: config.metadata.title,
									style: {
										width: '100%',
										minHeight: '300px',
										border: 0,
									},
									onLoad: ( event ) => {
										const frame = event.currentTarget;
										const body =
											frame.contentDocument?.body;
										if ( body ) {
											const resize = () => {
												frame.style.height =
													Math.max(
														100,
														body.scrollHeight
													) + 'px';
											};
											resize();
											if ( window.ResizeObserver ) {
												const observer =
													new window.ResizeObserver(
														resize
													);
												observer.observe( body );
												frame.contentWindow.addEventListener(
													'unload',
													() => observer.disconnect(),
													{ once: true }
												);
											}
										}
									},
							  } )
							: h( 'p', null, config.placeholder )
					);
				}

				window.divi.moduleLibrary.registerModule( config.metadata, {
					conversionOutline: config.conversionOutline,
					renderers: { edit },
					callbacks: {
						content: {
							elements: window.divi.moduleUtils.elementsCallbacks,
						},
					},
				} );
				registered = true;
			} catch ( error ) {
				// Keep an unavailable/expired editor session from breaking the builder.
				window.console?.error( error );
			}
		}
	);
} )( window, document );
