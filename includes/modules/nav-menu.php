<?php
/**
 * Module: translated menus.
 *
 * Classic menus (Appearance → Menus)
 *  - per-language title, description and (for custom links) URL fields on
 *    every menu item,
 *  - items pointing to posts/pages automatically link to the published
 *    translation and use its title (also fixes the "current item" highlight),
 *  - custom links to own content are localized automatically,
 *  - translations are hidden from the "Add menu items" boxes.
 *
 * Block themes (core/navigation)
 *  - links to posts/pages/terms/own URLs are rewritten to the current language.
 *
 * Saving only touches real menu items of the submitted menu and every
 * field is sanitized.
 */

defined( 'ABSPATH' ) || exit;

const BOGOEXT_MENU_META = '_bogoext_i18n';

/**
 * Per-language data of a menu item.
 *
 * @param int $item_id Menu item ID.
 * @return array locale => [ title, description, url ]
 */
function bogoext_menu_item_i18n( $item_id ) {
	$data = get_post_meta( $item_id, BOGOEXT_MENU_META, true );

	return is_array( $data ) ? $data : array();
}

/* ---------------------------------------------------------------------
 * Admin: fields
 * ------------------------------------------------------------------- */

add_action( 'wp_nav_menu_item_custom_fields', 'bogoext_menu_item_fields', 20, 2 );

function bogoext_menu_item_fields( $item_id, $menu_item ) {
	$languages = bogoext_secondary_languages();

	if ( ! $languages ) {
		return;
	}

	$data      = bogoext_menu_item_i18n( $item_id );
	$is_custom = 'custom' === $menu_item->type;
	$has_value = false;

	foreach ( $data as $fields ) {
		if ( array_filter( (array) $fields ) ) {
			$has_value = true;
			break;
		}
	}

	$label = $menu_item->title ? $menu_item->title : $menu_item->post_title;

	printf( '<details class="bogoext-i18n description-wide"%s>', $has_value ? ' open' : '' );
	printf( '<summary>%s</summary>', esc_html__( 'Translations', 'bogo-extended' ) );
	printf( '<input type="hidden" name="bogoext_menu_i18n_present[%d]" value="1" />', (int) $item_id );

	foreach ( $languages as $locale => $name ) {
		$fields      = $data[ $locale ] ?? array();
		$placeholder = $label;

		if ( 'post_type' === $menu_item->type ) {
			$translation_id = bogoext_get_translation_id( (int) $menu_item->object_id, $locale, false );
			$placeholder    = $translation_id ? get_the_title( $translation_id ) : $label;
		}

		$base = sprintf( 'bogoext_menu_i18n[%d][%s]', (int) $item_id, $locale );

		echo '<div class="bogoext-i18n__row" data-locale="' . esc_attr( $locale ) . '">';
		echo bogoext_badge( $locale, array( 'status' => 'draft', 'title' => $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="bogoext-i18n__fields">';

		printf(
			'<input type="text" name="%1$s[title]" value="%2$s" placeholder="%3$s" aria-label="%4$s" />',
			esc_attr( $base ),
			esc_attr( $fields['title'] ?? '' ),
			esc_attr( $placeholder ),
			/* translators: %s: language name */
			esc_attr( sprintf( __( 'Navigation label (%s)', 'bogo-extended' ), $name ) )
		);

		if ( $is_custom ) {
			printf(
				'<input type="url" name="%1$s[url]" value="%2$s" placeholder="%3$s" aria-label="%4$s" />',
				esc_attr( $base ),
				esc_attr( $fields['url'] ?? '' ),
				esc_attr__( 'URL (empty = automatic)', 'bogo-extended' ),
				/* translators: %s: language name */
				esc_attr( sprintf( __( 'URL (%s)', 'bogo-extended' ), $name ) )
			);
		}

		printf(
			'<textarea name="%1$s[description]" rows="2" placeholder="%2$s" aria-label="%3$s">%4$s</textarea>',
			esc_attr( $base ),
			esc_attr__( 'Description (optional)', 'bogo-extended' ),
			/* translators: %s: language name */
			esc_attr( sprintf( __( 'Description (%s)', 'bogo-extended' ), $name ) ),
			esc_textarea( $fields['description'] ?? '' )
		);

		echo '</div></div>';
	}

	echo '</details>';
}

add_action( 'admin_enqueue_scripts', static function ( $hook_suffix ) {
	if ( 'nav-menus.php' === $hook_suffix ) {
		bogoext_enqueue_admin_style();
	}
} );

/* ---------------------------------------------------------------------
 * Admin: save
 * ------------------------------------------------------------------- */

add_action( 'wp_update_nav_menu_item', 'bogoext_menu_item_save', 10, 2 );

function bogoext_menu_item_save( $menu_id, $item_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below.
	if ( empty( $_POST['bogoext_menu_i18n_present'][ $item_id ] ) ) {
		return;
	}

	$nonce = isset( $_POST['update-nav-menu-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['update-nav-menu-nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'update-nav_menu' ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	if ( 'nav_menu_item' !== get_post_type( $item_id ) ) {
		return;
	}

	$raw = isset( $_POST['bogoext_menu_i18n'][ $item_id ] ) && is_array( $_POST['bogoext_menu_i18n'][ $item_id ] )
		? wp_unslash( $_POST['bogoext_menu_i18n'][ $item_id ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field.
		: array();
	// phpcs:enable

	$clean = array();

	foreach ( bogoext_secondary_languages() as $locale => $name ) {
		$fields = isset( $raw[ $locale ] ) && is_array( $raw[ $locale ] ) ? $raw[ $locale ] : array();

		$entry = array_filter( array(
			'title'       => bogoext_sanitize_line( $fields['title'] ?? '' ),
			'description' => sanitize_textarea_field( is_scalar( $fields['description'] ?? '' ) ? (string) ( $fields['description'] ?? '' ) : '' ),
			'url'         => esc_url_raw( is_scalar( $fields['url'] ?? '' ) ? (string) ( $fields['url'] ?? '' ) : '' ),
		), 'strlen' );

		if ( $entry ) {
			$clean[ $locale ] = $entry;
		}
	}

	if ( $clean ) {
		update_post_meta( $item_id, BOGOEXT_MENU_META, $clean );
	} else {
		delete_post_meta( $item_id, BOGOEXT_MENU_META );
	}
}

/* ---------------------------------------------------------------------
 * Admin: hide translations in "Add menu items"
 * ------------------------------------------------------------------- */

add_action( 'parse_query', 'bogoext_menu_metabox_originals', 5 );

/**
 * Runs before Bogo's own parse_query (priority 10) and asks it to filter by
 * the default language.
 *
 * @param WP_Query $query Query.
 */
function bogoext_menu_metabox_originals( $query ) {
	global $pagenow;

	if ( ! is_admin() ) {
		return;
	}

	$ajax_action = wp_doing_ajax() && isset( $_REQUEST['action'] ) // phpcs:ignore WordPress.Security.NonceVerification
		? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
		: '';

	if ( 'nav-menus.php' !== $pagenow && ! in_array( $ajax_action, array( 'menu-get-metabox', 'menu-quick-search' ), true ) ) {
		return;
	}

	$post_type = $query->get( 'post_type' );

	if ( is_string( $post_type ) && bogo_is_localizable_post_type( $post_type ) && '' === (string) $query->get( 'lang' ) ) {
		$query->query_vars['lang'] = bogoext_default_locale();
	}
}

/* ---------------------------------------------------------------------
 * Front end: classic menus
 * ------------------------------------------------------------------- */

add_filter( 'wp_get_nav_menu_items', 'bogoext_menu_localize_items', 20, 3 );

function bogoext_menu_localize_items( $items, $menu, $args ) {
	if ( ! bogoext_should_localize_output() || empty( $items ) ) {
		return $items;
	}

	$locale = get_locale();

	$object_ids = array();

	foreach ( $items as $item ) {
		if ( 'post_type' === $item->type ) {
			$object_ids[] = (int) $item->object_id;
		}
	}

	BogoExt_Translations::prime( $object_ids );

	foreach ( $items as $item ) {
		$i18n = bogoext_menu_item_i18n( $item->db_id ?? $item->ID );
		$i18n = $i18n[ $locale ] ?? array();

		switch ( $item->type ) {
			case 'post_type':
				$source_id = (int) $item->object_id;

				if ( bogo_is_default_locale( bogo_get_post_locale( $source_id ) ) ) {
					$translation_id = bogoext_get_translation_id( $source_id, $locale, true );

					if ( $translation_id ) {
						$item->url       = get_permalink( $translation_id );
						$item->object_id = (string) $translation_id;

						if ( empty( $i18n['title'] ) ) {
							$item->title = get_the_title( $translation_id );
						}
					}
				}
				break;

			case 'taxonomy':
				if ( empty( $i18n['title'] ) && '' !== (string) $item->post_title && function_exists( 'bogoext_term_field' ) ) {
					$name = bogoext_term_field( (int) $item->object_id, $locale, 'name' );

					if ( '' !== $name ) {
						$item->title = $name;
					}
				}
				break;

			case 'custom':
				$item->url = ! empty( $i18n['url'] ) ? $i18n['url'] : bogoext_localize_url( $item->url, $locale );
				break;
		}

		if ( ! empty( $i18n['title'] ) ) {
			$item->title = $i18n['title'];
		}

		if ( ! empty( $i18n['description'] ) ) {
			$item->description = $i18n['description'];
		}
	}

	return $items;
}

/* ---------------------------------------------------------------------
 * Front end: block navigation (core/navigation-link, navigation-submenu)
 * ------------------------------------------------------------------- */

add_filter( 'render_block_core/navigation-link', 'bogoext_menu_localize_nav_block', 10, 2 );
add_filter( 'render_block_core/navigation-submenu', 'bogoext_menu_localize_nav_block', 10, 2 );

function bogoext_menu_localize_nav_block( $html, $block ) {
	if ( ! bogoext_should_localize_output() || '' === $html || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$locale = get_locale();
	$attrs  = $block['attrs'] ?? array();
	$kind   = $attrs['kind'] ?? '';
	$id     = (int) ( $attrs['id'] ?? 0 );
	$label  = isset( $attrs['label'] ) ? wp_strip_all_tags( (string) $attrs['label'] ) : '';

	$new_url   = '';
	$new_label = '';
	$is_current = false;

	if ( 'post-type' === $kind && $id && bogo_is_default_locale( bogo_get_post_locale( $id ) ) ) {
		$translation_id = bogoext_get_translation_id( $id, $locale, true );

		if ( $translation_id ) {
			$new_url    = get_permalink( $translation_id );
			$is_current = get_queried_object_id() === $translation_id;

			if ( '' === $label || get_post_field( 'post_title', $id, 'raw' ) === $label ) {
				$new_label = get_the_title( $translation_id );
			}
		}
	} elseif ( 'taxonomy' === $kind && $id ) {
		$term_url = get_term_link( $id );

		if ( $term_url && ! is_wp_error( $term_url ) ) {
			$new_url = $term_url; // Bogo adds the language prefix to term links.
		}

		if ( function_exists( 'bogoext_get_term_untranslated' ) ) {
			$original = bogoext_get_term_untranslated( $id );
			$name     = bogoext_term_field( $id, $locale, 'name' );

			if ( $original && '' !== $name && ( '' === $label || $label === $original->name ) ) {
				$new_label = $name;
			}
		}
	} elseif ( ! empty( $attrs['url'] ) ) {
		$localized = BogoExt_URL_Resolver::localize( (string) $attrs['url'], $locale );
		$new_url   = $localized ? $localized : '';
	}

	if ( ! $new_url && ! $new_label ) {
		return $html;
	}

	$processor = new WP_HTML_Tag_Processor( $html );

	if ( $is_current && $processor->next_tag( 'li' ) ) {
		$processor->add_class( 'current-menu-item' );
	}

	if ( $new_url && $processor->next_tag( 'a' ) ) {
		$processor->set_attribute( 'href', $new_url );

		if ( $is_current ) {
			$processor->set_attribute( 'aria-current', 'page' );
		}
	}

	$html = $processor->get_updated_html();

	if ( $new_label ) {
		$html = preg_replace(
			'#(<span class="wp-block-navigation-item__label">).*?(</span>)#s',
			'${1}' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), esc_html( $new_label ) ) . '${2}',
			$html,
			1
		);
	}

	return $html;
}
