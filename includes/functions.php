<?php
/**
 * Shared helper functions.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site default locale (as defined by Bogo).
 *
 * @return string
 */
function bogoext_default_locale() {
	return bogo_get_default_locale();
}

/**
 * Whether the given locale (or the current one) is the default locale.
 *
 * @param string|null $locale Locale code. Null = current request locale.
 * @return bool
 */
function bogoext_is_default_locale( $locale = null ) {
	if ( null === $locale ) {
		$locale = get_locale();
	}

	return bogo_is_default_locale( $locale );
}

/**
 * Whether we are rendering a public (front-end) request in a non-default
 * language – the only situation where content must be swapped.
 *
 * @return bool
 */
function bogoext_should_localize_output() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}

	return ! bogoext_is_default_locale();
}

/**
 * Human readable language name for a locale.
 *
 * @param string $locale Locale code.
 * @return string
 */
function bogoext_language_name( $locale ) {
	$name = bogo_get_language( $locale );

	return $name ? $name : $locale;
}

/**
 * Short uppercase code used on badges (e.g. "DE", "PT-BR").
 *
 * @param string $locale Locale code.
 * @return string
 */
function bogoext_language_code( $locale ) {
	$slug = bogo_lang_slug( $locale );

	return strtoupper( $slug ? $slug : $locale );
}

/**
 * Returns the ID of the translation of a post in a locale.
 *
 * @param int    $post_id        Post ID.
 * @param string $locale         Target locale.
 * @param bool   $published_only Only return published translations.
 * @return int Post ID or 0.
 */
function bogoext_get_translation_id( $post_id, $locale, $published_only = true ) {
	$group = BogoExt_Translations::get_group( $post_id );

	if ( empty( $group[ $locale ] ) ) {
		return 0;
	}

	if ( $published_only && 'publish' !== $group[ $locale ]['status'] ) {
		return 0;
	}

	return (int) $group[ $locale ]['id'];
}

/**
 * Meta query clause matching posts in the default locale
 * (posts without `_locale` belong to the default locale in Bogo).
 *
 * @return array
 */
function bogoext_default_locale_meta_clause() {
	return array(
		'relation' => 'OR',
		array(
			'key'     => '_locale',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'   => '_locale',
			'value' => '',
		),
		array(
			'key'   => '_locale',
			'value' => bogoext_default_locale(),
		),
	);
}

/**
 * Adds a clause to an existing meta_query without destroying it.
 *
 * @param mixed $meta_query Existing meta query.
 * @param array $clause     Clause to add.
 * @return array
 */
function bogoext_merge_meta_query( $meta_query, array $clause ) {
	if ( empty( $meta_query ) || ! is_array( $meta_query ) ) {
		return array( $clause );
	}

	return array(
		'relation' => 'AND',
		$meta_query,
		$clause,
	);
}

/**
 * Locales the current user may work with (Bogo's "accessible locales").
 *
 * @return string[]
 */
function bogoext_user_locales() {
	return array_keys( bogo_available_languages( array(
		'current_user_can_access' => true,
	) ) );
}

/**
 * All available languages: locale => name.
 *
 * @return array
 */
function bogoext_languages() {
	return bogo_available_languages();
}

/**
 * Non-default languages: locale => name.
 *
 * @return array
 */
function bogoext_secondary_languages() {
	$languages = bogo_available_languages();
	unset( $languages[ bogoext_default_locale() ] );

	return $languages;
}

/**
 * Sanitizes a translation text field (single line).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function bogoext_sanitize_line( $value ) {
	return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
}

/**
 * Sanitizes a translation text area that may contain HTML.
 * Users without `unfiltered_html` get the same limited set of tags
 * WordPress allows in term descriptions.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function bogoext_sanitize_html( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = (string) $value;

	return current_user_can( 'unfiltered_html' )
		? wp_kses_post( $value )
		: wp_kses_data( $value );
}

/**
 * Inline status badge used in admin screens.
 *
 * @param string $locale Locale.
 * @param array  $args   {
 *     @type string $status  publish|draft|pending|future|private|missing|current.
 *     @type string $href    Optional link.
 *     @type string $title   Tooltip.
 *     @type array  $data    data-* attributes.
 *     @type string $target  Link target.
 * }
 * @return string HTML.
 */
function bogoext_badge( $locale, array $args = array() ) {
	$args = wp_parse_args( $args, array(
		'status' => 'missing',
		'href'   => '',
		'title'  => '',
		'data'   => array(),
		'target' => '',
	) );

	$class = 'bogoext-badge';

	foreach ( preg_split( '/\s+/', trim( (string) $args['status'] ) ) as $state ) {
		$state = sanitize_html_class( $state );

		if ( '' !== $state ) {
			$class .= ' is-' . $state;
		}
	}

	$atts = sprintf(
		' class="%1$s" title="%2$s" lang="%3$s"',
		esc_attr( $class ),
		esc_attr( $args['title'] ),
		esc_attr( bogo_language_tag( $locale ) )
	);

	foreach ( (array) $args['data'] as $key => $value ) {
		$atts .= sprintf( ' data-%1$s="%2$s"', esc_attr( sanitize_key( $key ) ), esc_attr( $value ) );
	}

	$label = esc_html( bogoext_language_code( $locale ) );

	if ( $args['href'] ) {
		$target = $args['target'] ? sprintf( ' target="%s" rel="noopener"', esc_attr( $args['target'] ) ) : '';

		return sprintf( '<a href="%1$s"%2$s%3$s>%4$s</a>', esc_url( $args['href'] ), $atts, $target, $label );
	}

	if ( ! empty( $args['data'] ) ) {
		return sprintf( '<button type="button"%1$s>%2$s</button>', $atts, $label );
	}

	return sprintf( '<span%1$s>%2$s</span>', $atts, $label );
}

/**
 * Human readable post status for badges.
 *
 * @param string $status Post status.
 * @return string
 */
function bogoext_status_label( $status ) {
	$object = get_post_status_object( $status );

	return $object ? $object->label : $status;
}

/**
 * Enqueues the shared admin stylesheet.
 */
function bogoext_enqueue_admin_style() {
	wp_enqueue_style(
		'bogoext-admin',
		BOGOEXT_URL . 'assets/css/admin.css',
		array(),
		BOGOEXT_VERSION
	);
}

/**
 * Adds the custom post types selected in the settings to Bogo's list of
 * translatable post types.
 *
 * @param array $post_types Post types.
 * @return array
 */
function bogoext_localizable_post_types( $post_types ) {
	$selected = (array) bogoext_get_setting( 'post_types' );

	return array_values( array_unique( array_merge( (array) $post_types, array_map( 'strval', $selected ) ) ) );
}

/**
 * Post types that can be offered as translatable in the settings.
 *
 * @return WP_Post_Type[] name => object
 */
function bogoext_selectable_post_types() {
	$excluded = array(
		'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
		'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
		'wp_navigation', 'wp_font_family', 'wp_font_face', 'wpcf7_contact_form',
	);

	$types = array();

	foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $name => $object ) {
		if ( in_array( $name, $excluded, true ) || 0 === strpos( $name, 'acf-' ) ) {
			continue;
		}

		$types[ $name ] = $object;
	}

	return $types;
}
