<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'key'         => 'responsive',
		'post_title'  => 'Demo : Responsive',
		'post_status' => 'publish',
		'post_type'   => FOOGALLERY_CPT_GALLERY,
		'items'       => array( 'demo1', 'demo2', 'demo3', 'demo4', 'demo5', 'demo6' ),
		'meta_input'  => array(
			FOOGALLERY_META_TEMPLATE => 'default',
			FOOGALLERY_META_SETTINGS => array_merge(
				foogallery_build_default_settings_for_gallery_template( 'default' ),
				foogallery_build_initial_mobile_settings( 'default' ),
				array(
					'foogallery_items_view' => 'preview',
				)
			),
		),
	), //Demo : Responsive
	array(
		'key'         => 'image-viewer',
		'post_title'  => 'Demo : Image Viewer',
		'post_status' => 'publish',
		'post_type'   => FOOGALLERY_CPT_GALLERY,
		'items'       => array( 'demo3', 'demo1', 'demo5', 'demo10', 'demo7', 'demo2' ),
		'meta_input'  => array(
			FOOGALLERY_META_TEMPLATE => 'image-viewer',
			FOOGALLERY_META_SETTINGS => array_merge(
				foogallery_build_default_settings_for_gallery_template( 'image-viewer' ),
				foogallery_build_initial_mobile_settings( 'image-viewer' ),
				array(
					'foogallery_items_view' => 'preview',
				)
			),
		),
	), //Demo : Image Viewer
	array(
		'key'         => 'justified',
		'post_title'  => 'Demo : Justified',
		'post_status' => 'publish',
		'post_type'   => FOOGALLERY_CPT_GALLERY,
		'items'       => array( 'demo2', 'demo1', 'demo3', 'demo4', 'demo5', 'demo6', 'demo7', 'demo8', 'demo9', 'demo10' ),
		'meta_input'  => array(
			FOOGALLERY_META_TEMPLATE => 'justified',
			FOOGALLERY_META_SETTINGS => array_merge(
				foogallery_build_default_settings_for_gallery_template( 'justified' ),
				foogallery_build_initial_mobile_settings( 'justified' ),
				array(
					'foogallery_items_view' => 'preview',
				)
			),
		),
	), //Demo : Justified
	array(
		'key'         => 'masonry',
		'post_title'  => 'Demo : Masonry',
		'post_status' => 'publish',
		'post_type'   => FOOGALLERY_CPT_GALLERY,
		'items'       => array( 'demo4', 'demo2', 'demo3', 'demo1', 'demo5', 'demo6', 'demo7', 'demo8', 'demo9', 'demo10' ),
		'meta_input'  => array(
			FOOGALLERY_META_TEMPLATE => 'masonry',
			FOOGALLERY_META_SETTINGS => array_merge(
				foogallery_build_default_settings_for_gallery_template( 'masonry' ),
				foogallery_build_initial_mobile_settings( 'masonry' ),
				array(
					'foogallery_items_view' => 'preview',
				)
			),
		),
	), //Demo : Masonry
	array(
		'key'         => 'portfolio',
		'post_title'  => 'Demo : Portfolio',
		'post_status' => 'publish',
		'post_type'   => FOOGALLERY_CPT_GALLERY,
		'items'       => array( 'demo5', 'demo1', 'demo2', 'demo3', 'demo4', 'demo6', 'demo7', 'demo8', 'demo9', 'demo10' ),
		'meta_input'  => array(
			FOOGALLERY_META_TEMPLATE => 'simple_portfolio',
			FOOGALLERY_META_SETTINGS => array_merge(
				foogallery_build_default_settings_for_gallery_template( 'simple_portfolio' ),
				foogallery_build_initial_mobile_settings( 'simple_portfolio' ),
				array(
					'foogallery_items_view' => 'preview',
				)
			),
		),
	), //Demo : Simple Portfolio
	array(
		'key'         => 'portfolio2',
		'post_title'  => 'Demo : Portfolio (Variation)',
		'post_status' => 'publish',
		'post_type'   => FOOGALLERY_CPT_GALLERY,
		'items'       => array( 'demo6', 'demo1', 'demo2', 'demo3', 'demo4', 'demo5', 'demo7', 'demo8', 'demo9', 'demo10' ),
		'meta_input'  => array(
			FOOGALLERY_META_TEMPLATE => 'simple_portfolio',
			FOOGALLERY_META_SETTINGS => array(
				'foogallery_items_view'         => 'preview',

				//General
				'simple_portfolio_thumbnail_dimensions'  => array(
					'width'  => '220',
					'height' => '180',
				),
				'simple_portfolio_thumbnail_link'        => 'image',
				'simple_portfolio_lightbox'              => 'foogallery',
				'simple_portfolio_align'                 => 'center',
				'simple_portfolio_gutter'                => '5',

				//Appearance
				'simple_portfolio_theme'                 => 'fg-transparent',              // options : 'fg-light', 'fg-dark', 'fg-custom'
				'simple_portfolio_border_size'           => '',       // options : '', 'fg-border-thin', 'fg-border-medium', 'fg-border-thick'
				'simple_portfolio_rounded_corners'       => 'fg-round-large',                     // options : '', 'fg-round-small', 'fg-round-medium', 'fg-round-large', 'fg-round-full'
				'simple_portfolio_drop_shadow'           => '',      // options : '', 'fg-shadow-outline', 'fg-shadow-small', 'fg-shadow-medium', 'fg-shadow-large'
				'simple_portfolio_inner_shadow'          => '',                     // options : '', 'fg-shadow-inset-small', 'fg-shadow-inset-medium', 'fg-shadow-inset-large'
				'simple_portfolio_loading_icon'          => 'fg-loading-default',   // options : '', 'fg-loading-default', 'fg-loading-bars', 'fg-loading-dots', 'fg-loading-partial', 'fg-loading-pulse', 'fg-loading-trail',

				'simple_portfolio_loaded_effect'         => 'fg-loaded-flip',    // options : 'fg-loaded-fade-in', 'fg-loaded-scale-up'
				'simple_portfolio_instagram'             => '',

				//Captions
				'simple_portfolio_captions_type'         => '',
				'simple_portfolio_caption_title_source'  => '',
				'simple_portfolio_caption_desc_source'   => 'none',
				'simple_portfolio_captions_limit_length' => 'clamp',
				'simple_portfolio_caption_title_clamp'   => '1',
				'simple_portfolio_caption_desc_clamp'    => '0',
				'simple_portfolio_caption_alignment'     => 'fg-c-c',

				//Hover Effects
				'simple_portfolio_hover_effect_caption_visibility' => 'fg-caption-always', // options : '', 'fg-caption-hover', 'fg-caption-always', 'fg-captions-bottom'
				'simple_portfolio_hover_effect_color'              => '',                   // options : '', 'fg-hover-colorize', 'fg-hover-grayscale
				'simple_portfolio_hover_effect_icon'               => 'fg-hover-zoom2',      // options : 'fg-hover-zoom', 'fg-hover-zoom2', 'fg-hover-zoom3', 'fg-hover-plus', 'fg-hover-circle-plus', 'fg-hover-eye', 'fg-hover-external'
				'simple_portfolio_hover_effect_preset'             => 'fg-custom',
				'simple_portfolio_hover_effect_scale'              => 'fg-hover-zoomed',    // options : '', 'fg-hover-scale', 'fg-hover-zoomed'
				'simple_portfolio_hover_effect_transition'         => 'fg-hover-fade',      // options : 'fg-hover-instant', 'fg-hover-fade', 'fg-hover-slide-up', 'fg-hover-slide-down', 'fg-hover-slide-left', 'fg-hover-slide-right', 'fg-hover-push'
				'simple_portfolio_hover_effect_type'               => 'normal',             // options : 'none', 'normal', 'preset'
				'simple_portfolio_caption_invert_color'            => '',                   // options : '', 'fg-light-overlays', 'fg-transparent-overlays'

				//Filtering
				'simple_portfolio_filtering_type' => '',

				//Paging
				'simple_portfolio_paging_type' => '',

				//Advanced
				'simple_portfolio_state' => 'no',
				'simple_portfolio_custom_settings' => '',
				'simple_portfolio_lazyload' => '',

				//Video
				'simple_portfolio_video_autoplay'    => 'yes',
				'simple_portfolio_video_hover_icon'  => 'fg-video-default',
				'simple_portfolio_video_size'        => '640x360',
				'simple_portfolio_video_sticky_icon' => '',
			),
		),
	), //Demo : Simple Portfolio : Variation
	array(
		'key'         => 'thumbnail',
		'post_title'  => 'Demo : Single Thumbnail',
		'post_status' => 'publish',
		'post_type'   => FOOGALLERY_CPT_GALLERY,
		'items'       => array( 'demo10', 'demo4', 'demo3', 'demo2', 'demo1', 'demo5', 'demo6', 'demo7', 'demo8', 'demo9' ),
		'meta_input'  => array(
			FOOGALLERY_META_TEMPLATE => 'thumbnail',
			FOOGALLERY_META_SETTINGS => array_merge(
				foogallery_build_default_settings_for_gallery_template( 'thumbnail' ),
				foogallery_build_initial_mobile_settings( 'thumbnail' ),
				array(
					'foogallery_items_view' => 'preview',
				)
			),
		),
	), //Demo : Single Thumbnail
    array(
        'key'         => 'carousel',
        'post_title'  => 'Demo : Carousel',
        'post_status' => 'publish',
        'post_type'   => FOOGALLERY_CPT_GALLERY,
        'items'       => array( 'demo7', 'demo1', 'demo2', 'demo3', 'demo4', 'demo5', 'demo7', 'demo8', 'demo9' ),
        'meta_input'  => array(
            FOOGALLERY_META_TEMPLATE => 'carousel',
            FOOGALLERY_META_SETTINGS => array_merge(
                foogallery_build_default_settings_for_gallery_template( 'carousel' ),
                foogallery_build_initial_mobile_settings( 'carousel' ),
                array(
                    'foogallery_items_view' => 'preview',
                )
            ),
        ),
    ), //Demo : Carousel
	array(
        'key'         => 'carousel2',
        'post_title'  => 'Demo : Carousel Logo Scroller',
        'post_status' => 'publish',
        'post_type'   => FOOGALLERY_CPT_GALLERY,
        'items'       => array( 'demo8', 'demo1', 'demo2', 'demo3', 'demo4', 'demo5', 'demo6', 'demo7', 'demo9' ),
        'meta_input'  => array(
            FOOGALLERY_META_TEMPLATE => 'carousel',
            FOOGALLERY_META_SETTINGS => array(
                'foogallery_items_view'         => 'preview',
                'carousel_autoplay_interaction'=> 'pause',
                'carousel_autoplay_time' => '2',
                'carousel_centerOnClick'=> 'true',
                'carousel_maxItems'=> '7',
                'carousel_scale'=> '0',
                'carousel_gutter'=>
                    array(
                        'min'=> '20',
                        'max'=> '20',
                        'units'=> 'px',
                    ),
                'carousel_inverted'=> '',
				'carousel_show_pagination' => 'fg-carousel-hide-pagination',
				'carousel_show_progress' => 'fg-carousel-hide-progress-bar',

                //General
                'carousel_thumbnail_dimensions'  => array(
                    'width'  => '100',
                    'height' => '100',
                ),
                'carousel_thumbnail_link'                => 'none',
                'carousel_lightbox'                      => 'foogallery',


                //Appearance
                'carousel_theme'                 => 'fg-light',              // options : 'fg-light', 'fg-dark', 'fg-custom'
                'carousel_border_size'           => '',       // options : '', 'fg-border-thin', 'fg-border-medium', 'fg-border-thick'
                'carousel_rounded_corners'       => 'fg-round-medium',                     // options : '', 'fg-round-small', 'fg-round-medium', 'fg-round-large', 'fg-round-full'
                'carousel_drop_shadow'           => '',      // options : '', 'fg-shadow-outline', 'fg-shadow-small', 'fg-shadow-medium', 'fg-shadow-large'
                'carousel_inner_shadow'          => '',                     // options : '', 'fg-shadow-inset-small', 'fg-shadow-inset-medium', 'fg-shadow-inset-large'
                'carousel_loading_icon'          => 'fg-loading-bars',   // options : '', 'fg-loading-default', 'fg-loading-bars', 'fg-loading-dots', 'fg-loading-partial', 'fg-loading-pulse', 'fg-loading-trail',

                'carousel_loaded_effect'         => 'fg-loaded-flip',    // options : 'fg-loaded-fade-in', 'fg-loaded-scale-up'
                'carousel_instagram'             => '',

                //Captions
                'carousel_captions_type'         => '',
                'carousel_caption_title_source'  => '',
                'carousel_caption_desc_source'   => 'none',
                'carousel_captions_limit_length' => 'clamp',
				'carousel_caption_title_clamp'   => '1',
				'carousel_caption_desc_clamp'    => '1',
				'carousel_caption_visibility_no_hover_effect' => '',

                //Hover Effects
                'carousel_hover_effect_caption_visibility' => '', // options : '', 'fg-caption-hover', 'fg-caption-always', 'fg-captions-bottom'
                'carousel_hover_effect_color'              => '',                   // options : '', 'fg-hover-colorize', 'fg-hover-grayscale
                'carousel_hover_effect_icon'               => '',      // options : 'fg-hover-zoom', 'fg-hover-zoom2', 'fg-hover-zoom3', 'fg-hover-plus', 'fg-hover-circle-plus', 'fg-hover-eye', 'fg-hover-external'
                'carousel_hover_effect_preset'             => '',
                'carousel_hover_effect_scale'              => '',    // options : '', 'fg-hover-scale', 'fg-hover-zoomed'
                'carousel_hover_effect_transition'         => '',      // options : 'fg-hover-instant', 'fg-hover-fade', 'fg-hover-slide-up', 'fg-hover-slide-down', 'fg-hover-slide-left', 'fg-hover-slide-right', 'fg-hover-push'
                'carousel_hover_effect_type'               => 'none',             // options : 'none', 'normal', 'preset'
                'carousel_caption_invert_color'            => '',                   // options : '', 'fg-light-overlays', 'fg-transparent-overlays'

                //Filtering
                'carousel_filtering_type' => '',

                //Advanced
                'carousel_state' => 'no',
                'carousel_custom_settings' => '',
                'carousel_lazyload' => '',

                //Video
                'carousel_video_autoplay'    => 'yes',
                'carousel_video_hover_icon'  => 'fg-video-default',
                'carousel_video_size'        => '640x360',
                'carousel_video_sticky_icon' => '',
            ),
        ),
    ), //Demo : Carousel Logo Scroller
);
