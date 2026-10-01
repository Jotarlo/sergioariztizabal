<?php
/**
 * Map popup bubble HTML: shared between initial map data (plain search text only in JSON)
 * and AJAX delivery of full HTML when a marker popup opens.
 *
 * @package OpenUserMapPlugin
 */

namespace OpenUserMapPlugin\Base;

/**
 * Builds location bubble markup and resolves a map $location row for AJAX.
 */
class LocationMapBubbleBuilder {

	/**
	 * Remove vote / star-rating UI from bubble HTML so it does not pollute marker search text.
	 *
	 * @param string $html Fragment of bubble HTML.
	 * @return string
	 */
	private static function strip_vote_and_rating_markup_for_search( $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return $html;
		}

		$previous = libxml_use_internal_errors( true );
		$doc      = new \DOMDocument();
		// loadHTML tolerates typical bubble markup; loadXML would fail on HTML entities / loose tags.
		$wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="oum-search-plain-root">' . $html . '</div></body></html>';
		$loaded = @$doc->loadHTML( $wrapped );

		if ( ! $loaded ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
			return $html;
		}

		$xpath = new \DOMXPath( $doc );
		// XPath 1: class is a single string; contains() matches substrings like "oum_vote_button_wrap".
		$selectors = array(
			'//div[contains(@class, "oum_star_rating_wrap")]',
			'//div[contains(@class, "oum_vote_button_wrap")]',
		);
		foreach ( $selectors as $selector ) {
			$nodes = $xpath->query( $selector );
			if ( ! $nodes ) {
				continue;
			}
			// Remove from deepest nested first so parent removal does not break iteration.
			$to_remove = array();
			foreach ( $nodes as $node ) {
				$to_remove[] = $node;
			}
			foreach ( $to_remove as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}

		$root = $doc->getElementById( 'oum-search-plain-root' );
		$out  = '';
		if ( $root ) {
			foreach ( $root->childNodes as $child ) {
				$out .= $doc->saveHTML( $child );
			}
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $out !== '' ? $out : $html;
	}

	/**
	 * Plain text for marker search: no vote/star UI, word boundaries between block elements.
	 *
	 * @param string $html Final bubble HTML (after oum_location_bubble_content).
	 * @return string
	 */
	public static function plain_search_text( $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return '';
		}

		$html = self::strip_vote_and_rating_markup_for_search( $html );

		// Separate adjacent tags so strip_tags() does not glue words (e.g. "21:27Bean", "2026Features").
		$html = preg_replace( '/>\s*</', '> <', $html );
		$html = preg_replace( '/<br\s*\/?>/i', ' ', $html );

		$plain = wp_strip_all_tags( html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		// Drop stray star characters that can appear outside removed blocks (e.g. custom HTML).
		$plain = str_replace( array( '★', '☆' ), '', $plain );
		$plain = preg_replace( '/\s+/', ' ', $plain );
		return trim( $plain );
	}

	/**
	 * Full bubble HTML for one map location row (same filters as legacy inline output).
	 *
	 * @param array  $location Same shape as entries in $locations_list from partial-map-init.php.
	 * @param string $plugin_url Plugin URL for assets.
	 * @return string
	 */
	public static function build_html( array $location, $plugin_url ) {
		if ( get_option( 'oum_enable_location_date' ) === 'on' ) {
			$date_tag = '<div class="oum_location_date">' . wp_kses_post( $location['date'] ) . '</div>';
		} else {
			$date_tag = '';
		}

		$name_tag = '';
		if ( get_option( 'oum_enable_title', 'on' ) === 'on' ) {
			$title_wrapper_content = '';
			$title_wrapper_content .= '<h3 class="oum_location_name">' . esc_attr( $location['title'] ) . '</h3>';
			if ( get_option( 'oum_enable_category_icons_in_title', 'on' ) === 'on' && isset( $location['post_id'] ) && $location['post_id'] ) {
				$category_icons = oum_get_location_value( 'type_icons', $location['post_id'] );
				if ( $category_icons ) {
					$title_wrapper_content .= $category_icons;
				}
			}
			$name_tag = '<div class="oum_location_title">' . $title_wrapper_content . '</div>';
		}

		$media_tag = '';

		if ( isset( $location['images'] ) && ! empty( $location['images'] ) ) {
			$oum_popup_image_size = get_option( 'oum_popup_image_size' ) ? get_option( 'oum_popup_image_size' ) : 'original';
			$media_tag  = '<div class="oum-carousel popup-image-size-' . esc_attr( $oum_popup_image_size ) . '">';
			$media_tag .= '<div class="oum-carousel-inner">';
			foreach ( $location['images'] as $index => $image_url ) {
				$active_class = ( $index === 0 ) ? ' active' : '';
				$media_tag   .= '<div class="oum-carousel-item' . $active_class . '">';
				$media_tag   .= '<img class="skip-lazy" src="' . esc_url_raw( $image_url ) . '" alt="' . esc_attr( $location['title'] ) . '">';
				$media_tag   .= '</div>';
			}
			$media_tag .= '</div>';
			$media_tag .= '</div>';
		}

		if ( function_exists( 'oum_fs' ) && oum_fs()->is__premium_only() && oum_fs()->can_use_premium_code() ) {
			if ( ! empty( $location['video'] ) ) {
				$url         = esc_url_raw( trim( (string) $location['video'] ) );
				$video_embed = wp_oembed_get( $url );
				if ( ! $video_embed ) {
					$video_embed = sprintf(
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a>',
						esc_url( $url )
					);
				}
				$media_tag = '<div class="oum_location_video">' . $video_embed . '</div>';
			}
		}

		$media_tag = apply_filters( 'oum_location_bubble_image', $media_tag, $location );

		$audio_tag = $location['audio'] ? '<audio controls="controls" style="width:100%"><source type="audio/mp4" src="' . $location['audio'] . '"><source type="audio/mpeg" src="' . $location['audio'] . '"><source type="audio/wav" src="' . $location['audio'] . '"></audio>' : '';

		$address_tag     = '';
		$oum_enable_gmaps_link = get_option( 'oum_enable_gmaps_link', 'on' );
		if ( get_option( 'oum_enable_address', 'on' ) === 'on' ) {
			$address_tag = ( $location['address'] && ! get_option( 'oum_hide_address' ) ) ? esc_attr( $location['address'] ) : '';
			if ( ( $oum_enable_gmaps_link === 'on' ) && $address_tag ) {
				$address_tag = '<a title="' . __( 'go to Google Maps', 'open-user-map' ) . '" href="https://www.google.com/maps/search/?api=1&amp;query=' . esc_attr( $location['lat'] ) . '%2C' . esc_attr( $location['lng'] ) . '" target="_blank">' . $address_tag . '</a>';
			}
		}
		$address_tag = ( $address_tag !== '' ) ? '<div class="oum_location_address">' . $address_tag . '</div>' : '';

		if ( get_option( 'oum_enable_description', 'on' ) === 'on' ) {
			$description_tag = '<div class="oum_location_description">' . wp_kses_post( $location['text'] ) . '</div>';
		} else {
			$description_tag = '';
		}

		$custom_fields = '';
		if ( isset( $location['custom_fields'] ) && is_array( $location['custom_fields'] ) ) {
			$fields_html = array();
			foreach ( $location['custom_fields'] as $custom_field ) {
				// Private fields may be included in the map payload for editors only; never show them in the popup.
				if ( ! empty( $custom_field['private'] ) ) {
					continue;
				}
				if ( empty( $custom_field['val'] ) ) {
					continue;
				}
				if ( $custom_field['fieldtype'] === 'opening_hours' ) {
					$field_html = LocationController::format_opening_hours_for_display(
						$custom_field['val'],
						$custom_field,
						$plugin_url
					);
				} else {
					$field_html = '<div data-custom-field-label="' . esc_attr( $custom_field['label'] ) . '" class="oum_custom_field  oum_custom_field_type_' . esc_attr( $custom_field['fieldtype'] ) . '">';
					if ( is_array( $custom_field['val'] ) ) {
						$values = array_map(
							function ( $x ) {
								return '<span data-value="' . esc_attr( $x ) . '">' . esc_html( $x ) . '</span>';
							},
							$custom_field['val']
						);
						$field_html .= '<strong>' . esc_html( $custom_field['label'] ) . ':</strong> ' . implode( ' ', $values );
					} elseif ( strpos( $custom_field['val'], '|' ) !== false ) {
						$field_html .= '<strong>' . esc_html( $custom_field['label'] ) . ':</strong> ';
						$entries        = array_map( 'trim', explode( '|', $custom_field['val'] ) );
						$formatted_entries = array();
						foreach ( $entries as $entry ) {
							if ( filter_var( $entry, FILTER_VALIDATE_URL ) ) {
								$formatted_entries[] = sprintf(
									'<a href="%s">%s</a>',
									esc_url( $entry ),
									esc_html( $entry )
								);
							} elseif ( $custom_field['fieldtype'] === 'email' && is_email( $entry ) ) {
								$formatted_entries[] = sprintf(
									'<a target="_blank" href="mailto:%s">%s</a>',
									esc_attr( $entry ),
									esc_html( $entry )
								);
							} else {
								$formatted_entries[] = sprintf(
									'<span data-value="%s">%s</span>',
									esc_attr( $entry ),
									esc_html( $entry )
								);
							}
						}
						$field_html .= implode( ' ', $formatted_entries );
					} else {
						$value = $custom_field['val'];
						if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
							if ( ! empty( $custom_field['uselabelastextoption'] ) ) {
								$field_html .= sprintf(
									'<a href="%s">%s</a>',
									esc_url( $value ),
									esc_html( $custom_field['label'] )
								);
							} else {
								$field_html .= sprintf(
									'<strong>%s:</strong> <a href="%s">%s</a>',
									esc_html( $custom_field['label'] ),
									esc_url( $value ),
									esc_html( $value )
								);
							}
						} elseif ( $custom_field['fieldtype'] === 'email' && is_email( $value ) ) {
							$field_html .= sprintf(
								'<strong>%s:</strong> <a target="_blank" href="mailto:%s">%s</a>',
								esc_html( $custom_field['label'] ),
								esc_attr( $value ),
								esc_html( $value )
							);
						} else {
							$field_html .= sprintf(
								'<strong>%s:</strong> <span data-value="%s">%s</span>',
								esc_html( $custom_field['label'] ),
								esc_attr( $value ),
								esc_html( $value )
							);
						}
					}
					$field_html .= '</div>';
				}
				$fields_html[] = $field_html;
			}
			if ( ! empty( $fields_html ) ) {
				$custom_fields = '<div class="oum_location_custom_fields">' . implode( '', $fields_html ) . '</div>';
			}
		}

		if ( get_option( 'oum_enable_single_page' ) ) {
			$link_tag = '<div class="oum_read_more"><a href="' . get_the_permalink( $location['post_id'] ) . '">' . __( 'Read more', 'open-user-map' ) . '</a></div>';
		} else {
			$link_tag = '';
		}

		$edit_button            = '<div class="edit-location-button-placeholder" data-post-id="' . esc_attr( $location['post_id'] ) . '"></div>';
		$additional_search_meta = '<div style="display: none">' . get_post_field( 'post_name', $location['post_id'] ) . '</div>';

		$vote_button = '';
		if ( function_exists( 'oum_fs' ) && oum_fs()->is__premium_only() && oum_fs()->can_use_premium_code() && get_option( 'oum_enable_vote_feature' ) === 'on' ) {
			$vote_type = get_option( 'oum_vote_type', 'upvote' );
			if ( $vote_type === 'star_rating' ) {
				$star_rating_avg   = isset( $location['star_rating_avg'] ) ? floatval( $location['star_rating_avg'] ) : 0;
				$star_rating_count = isset( $location['star_rating_count'] ) ? intval( $location['star_rating_count'] ) : 0;
				$vote_button         = '<div class="oum_star_rating_wrap">';
				$vote_button        .= '<div class="oum_star_rating" data-post-id="' . esc_attr( $location['post_id'] ) . '" data-average="' . esc_attr( $star_rating_avg ) . '" data-count="' . esc_attr( $star_rating_count ) . '">';
				$vote_button        .= '<div class="oum_stars">';
				for ( $i = 1; $i <= 5; $i++ ) {
					$vote_button .= '<span class="oum_star" data-rating="' . esc_attr( $i ) . '" aria-label="' . esc_attr( sprintf( __( 'Rate %d stars', 'open-user-map' ), $i ) ) . '">★</span>';
				}
				$vote_button .= '</div>';
				if ( $star_rating_count > 0 ) {
					$vote_button .= '<span class="oum_star_rating_count">(' . esc_html( $star_rating_count ) . ')</span>';
				} else {
					$vote_button .= '<span class="oum_star_rating_count" style="display: none;">(0)</span>';
				}
				$vote_button .= '</div></div>';
			} else {
				$votes                = isset( $location['votes'] ) ? intval( $location['votes'] ) : 0;
				$vote_label           = get_option( 'oum_vote_button_label', __( '👍', 'open-user-map' ) );
				$display_vote_label   = ! empty( trim( $vote_label ) ) ? $vote_label : __( '👍', 'open-user-map' );
				$vote_button          = '<div class="oum_vote_button_wrap">';
				$vote_button         .= '<button class="oum_vote_button" data-post-id="' . esc_attr( $location['post_id'] ) . '" data-votes="' . esc_attr( $votes ) . '" data-label="' . esc_attr( $display_vote_label ) . '">';
				$vote_button         .= '<span class="oum_vote_text">' . esc_html( $display_vote_label ) . '</span>';
				if ( $votes > 0 ) {
					$vote_button .= '<span class="oum_vote_count">' . esc_html( $votes ) . '</span>';
				}
				$vote_button .= '</button></div>';
			}
		}

		$content  = $media_tag;
		$content .= '<div class="oum_location_text">';
		$content .= $date_tag;
		$content .= $address_tag;
		$content .= $name_tag;
		$content .= $custom_fields;
		$content .= $description_tag;
		$content .= $audio_tag;
		$content .= '<div class="oum_location_text_bottom">' . $vote_button . $link_tag . '</div>';
		$content .= '</div>';
		$content .= $edit_button;
		$content .= $additional_search_meta;

		$content = str_replace( '\\', '', $content );

		return apply_filters( 'oum_location_bubble_content', $content, $location );
	}

	/**
	 * Build $location row for one post (mirrors partial-map-init.php loop) for lazy bubble AJAX.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $plugin_url Plugin base URL.
	 * @param string $marker_multicategories_icon_default Default multi icon URL.
	 * @return array|null
	 */
	public static function location_row_from_post_id( $post_id, $plugin_url, $marker_multicategories_icon_default ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'oum-location' || $post->post_status !== 'publish' ) {
			return null;
		}

		$location_meta = get_post_meta( $post_id, '_oum_location_key', true );
		if ( ! is_array( $location_meta ) ) {
			$location_meta = array();
		}
		if ( ! isset( $location_meta['lat'] ) || ! isset( $location_meta['lng'] ) ) {
			return null;
		}

		$name = str_replace( "'", "\'", strip_tags( $post->post_title ) );
		$address = isset( $location_meta['address'] ) ?
			str_replace( "'", "\'", ( preg_replace( '/\r|\n/', '', $location_meta['address'] ) ) ) : '';
		$text = isset( $location_meta['text'] ) ?
			str_replace( "'", "\'", str_replace( array( "\r\n", "\r", "\n" ), '<br>', $location_meta['text'] ) ) : '';
		$video = isset( $location_meta['video'] ) ? $location_meta['video'] : '';

		$image = get_post_meta( $post_id, '_oum_location_image', true );
		$images = array();
		if ( $image ) {
			$image_urls = explode( '|', $image );
			foreach ( $image_urls as $url ) {
				$url = trim( $url );
				if ( $url === '' ) {
					continue;
				}
				if ( stristr( $url, 'oum-useruploads' ) ) {
					$images[] = ( strpos( $url, 'http' ) !== 0 ) ? site_url() . $url : $url;
				} else {
					$abs = ( strpos( $url, 'http' ) !== 0 ) ? site_url() . $url : $url;
					$attachment_id = attachment_url_to_postid( $abs );
					if ( $attachment_id > 0 ) {
						$thumb = wp_get_attachment_image_url( $attachment_id, 'medium_large' );
						$images[] = $thumb ? $thumb : $abs;
					} else {
						$images[] = $abs;
					}
				}
			}
		}

		$audio = get_post_meta( $post_id, '_oum_location_audio', true );
		$absolute_audio = ( isset( $audio ) && $audio !== '' && strpos( $audio, 'http' ) !== 0 ) ?
			site_url() . $audio : $audio;

		$custom_fields = array();
		$meta_custom_fields = isset( $location_meta['custom_fields'] ) ? $location_meta['custom_fields'] : false;
		$active_custom_fields = get_option( 'oum_custom_fields' );

		if ( is_array( $meta_custom_fields ) && is_array( $active_custom_fields ) ) {
			foreach ( $active_custom_fields as $index => $custom_field ) {
				if ( isset( $custom_field['private'] ) ) {
					continue;
				}
				if ( ! isset( $meta_custom_fields[ $index ] ) ) {
					continue;
				}
				$field_data = array(
					'index'                 => $index,
					'label'                 => $custom_field['label'],
					'val'                   => $meta_custom_fields[ $index ],
					'fieldtype'             => isset( $custom_field['fieldtype'] ) ? $custom_field['fieldtype'] : 'text',
					'uselabelastextoption' => isset( $custom_field['uselabelastextoption'] ) ?
						$custom_field['uselabelastextoption'] : false,
					'use12hour'             => isset( $custom_field['use12hour'] ) ?
						$custom_field['use12hour'] : false,
				);
				if ( isset( $custom_field['fieldtype'] ) && $custom_field['fieldtype'] === 'opening_hours' ) {
					$opening_hours_data = json_decode( $meta_custom_fields[ $index ], true );
					$field_data['open_now'] = LocationController::calculate_open_now( $opening_hours_data );
				}
				$custom_fields[] = $field_data;
			}
		}

		$marker_icon      = get_option( 'oum_marker_icon' ) ? get_option( 'oum_marker_icon' ) : 'default';
		$marker_user_icon = get_option( 'oum_marker_user_icon' );
		$multi_icon       = get_option( 'oum_marker_multicategories_icon' ) ? get_option( 'oum_marker_multicategories_icon' ) : $marker_multicategories_icon_default;

		$get_default_icon = function () use ( $marker_icon, $marker_user_icon, $plugin_url ) {
			if ( $marker_icon === 'user1' && $marker_user_icon ) {
				return esc_url( $marker_user_icon );
			} elseif ( $marker_icon ) {
				return esc_url( $plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $marker_icon ) . '-2x.png';
			}
			return esc_url( $plugin_url ) . 'src/leaflet/images/marker-icon_default-2x.png';
		};

		$location_types = false;
		if ( function_exists( 'oum_fs' ) && oum_fs()->is__premium_only() && oum_fs()->can_use_premium_code() ) {
			$terms = get_the_terms( $post_id, 'oum-type' );
			$location_types = ( $terms && ! is_wp_error( $terms ) ) ? $terms : false;
		}

		if ( $location_types && is_array( $location_types ) ) {
			if ( count( $location_types ) > 1 ) {
				$icon = esc_url( $multi_icon );
			} elseif ( count( $location_types ) === 1 ) {
				$type       = $location_types[0];
				$cat_icon   = get_term_meta( $type->term_id, 'oum_marker_icon', true );
				$cat_user_icon = get_term_meta( $type->term_id, 'oum_marker_user_icon', true );
				if ( $cat_icon === 'user1' && $cat_user_icon ) {
					$icon = esc_url( $cat_user_icon );
				} elseif ( $cat_icon ) {
					$icon = esc_url( $plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $cat_icon ) . '-2x.png';
				} else {
					$icon = $get_default_icon();
				}
			} else {
				$icon = $get_default_icon();
			}
		} else {
			$icon = $get_default_icon();
		}

		$oum_location_date_type = get_option( 'oum_location_date_type', 'modified' );
		if ( $oum_location_date_type === 'created' ) {
			$date = get_the_date( '', $post_id );
		} else {
			$date = get_the_modified_date( '', $post_id );
		}

		$location = array(
			'post_id'             => $post_id,
			'date'                => $date,
			'title'               => $name,
			'address'             => $address,
			'lat'                 => $location_meta['lat'],
			'lng'                 => $location_meta['lng'],
			'zoom'                => isset( $location_meta['zoom'] ) ? $location_meta['zoom'] : '12',
			'text'                => $text,
			'images'              => $images,
			'audio'               => $absolute_audio,
			'video'               => $video,
			'icon'                => $icon,
			'custom_fields'       => $custom_fields,
			'author_id'           => get_post_field( 'post_author', $post_id ),
			'votes'               => isset( $location_meta['votes'] ) ? intval( $location_meta['votes'] ) : 0,
			'star_rating_avg'     => isset( $location_meta['star_rating_avg'] ) ? floatval( $location_meta['star_rating_avg'] ) : 0,
			'star_rating_count'   => isset( $location_meta['star_rating_count'] ) ? intval( $location_meta['star_rating_count'] ) : 0,
		);

		if ( $location_types && is_array( $location_types ) && count( $location_types ) > 0 ) {
			foreach ( $location_types as $term ) {
				$location['types'][] = (string) $term->term_taxonomy_id;
			}
		}

		return $location;
	}
}
