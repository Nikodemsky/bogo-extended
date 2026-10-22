<?php
/**
 * Module: translatable ACF options pages (ACF PRO).
 *
 * Every options page (its "post_id", usually "options") gets a separate set
 * of values per secondary language, stored by ACF itself as
 * "{post_id}_{locale}_{field}" options, e.g. options_de_DE_phone.
 *
 *  - front end: get_field( 'phone', 'option' ) on a German page reads
 *    options_de_DE, through ACF's own "acf/validate_post_id" filter,
 *  - admin: language tabs on the options page (?bogoext_lang=de_DE), the form
 *    loads and saves that language,
 *  - a language (or a field added later) without saved values falls back to
 *    the default language, so an untranslated options page behaves exactly as
 *    before; the first save of a language creates its own copy,
 *  - fields marked "Shared by all languages" always use the default value
 *    and are edited in the default language only.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ACF' ) && ! function_exists( 'acf' ) ) {
	return;
}

const BOGOEXT_ACF_OPT_PARAM = 'bogoext_lang';

add_filter( 'acf/validate_post_id', 'bogoext_acf_opt_validate_post_id', 20, 2 );
add_filter( 'acf/pre_load_value', 'bogoext_acf_opt_pre_load_value', 10, 3 );
add_filter( 'acf/pre_load_reference', 'bogoext_acf_opt_pre_load_reference', 10, 3 );
add_filter( 'acf/prepare_field', 'bogoext_acf_opt_prepare_field', 20 );
add_action( 'acf/render_field_settings', 'bogoext_acf_opt_field_setting', 20 );
add_action( 'all_admin_notices', 'bogoext_acf_opt_tabs' );
add_action( 'admin_post_bogoext_acf_opt_reset', 'bogoext_acf_opt_reset' );

/* ---------------------------------------------------------------------
 * Which options pages and IDs
 * ------------------------------------------------------------------- */

/**
 * All options page storage IDs: post_id => list of page titles.
 * Numeric post_ids (values stored on a post) are not handled here.
 *
 * @return array
 */
function bogoext_acf_opt_all_ids() {
	static $ids = null;

	if ( null !== $ids ) {
		return $ids;
	}

	$list  = array();
	$pages = function_exists( 'acf_get_options_pages' ) ? acf_get_options_pages() : false;

	foreach ( (array) $pages as $page ) {
		if ( empty( $page['post_id'] ) || is_numeric( $page['post_id'] ) ) {
			continue;
		}

		$id = 'option' === $page['post_id'] ? 'options' : (string) $page['post_id'];

		$list[ $id ][] = wp_strip_all_tags( (string) ( $page['menu_title'] ?? $page['page_title'] ?? $page['menu_slug'] ) );
	}

	if ( did_action( 'acf/init' ) ) {
		$ids = $list; // Pages are complete once ACF has initialised.
	}

	return $list;
}

/**
 * Translated options page IDs (all minus those switched off in settings).
 *
 * @return string[] id => id
 */
function bogoext_acf_opt_ids() {
	static $ids = null;

	if ( null !== $ids ) {
		return $ids;
	}

	$excluded = (array) bogoext_get_setting( 'acf_options_excluded' );
	$list     = array();

	foreach ( array_keys( bogoext_acf_opt_all_ids() ) as $id ) {
		if ( ! in_array( $id, $excluded, true ) ) {
			$list[ $id ] = $id;
		}
	}

	$list = (array) apply_filters( 'bogoext_acf_translatable_options', $list );

	if ( did_action( 'acf/init' ) ) {
		$ids = $list;
	}

	return $list;
}

/**
 * Localized ID → [ base ID, locale ], or null.
 *
 * @param mixed $post_id ACF post ID.
 * @return array|null
 */
function bogoext_acf_opt_parse( $post_id ) {
	static $map = null;

	if ( ! is_string( $post_id ) || false === strpos( $post_id, '_' ) ) {
		return null;
	}

	if ( null === $map || ! did_action( 'acf/init' ) ) {
		$map = array();

		foreach ( bogoext_acf_opt_ids() as $id ) {
			foreach ( bogoext_secondary_languages() as $locale => $name ) {
				$map[ $id . '_' . $locale ] = array( $id, $locale );
			}
		}
	}

	return $map[ $post_id ] ?? null;
}

/**
 * Language of option values for this request: the tab on an options page in
 * the admin, the current language elsewhere. '' = default language.
 *
 * @return string
 */
function bogoext_acf_opt_locale() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- language selection only.
		$locale = isset( $_GET[ BOGOEXT_ACF_OPT_PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ BOGOEXT_ACF_OPT_PARAM ] ) ) : '';

		if ( '' === $locale || bogo_is_default_locale( $locale ) || ! in_array( $locale, bogoext_user_locales(), true ) ) {
			return '';
		}

		return $locale;
	}

	return bogoext_should_localize_output() ? get_locale() : '';
}

/**
 * "options" → "options_de_DE" when values of a secondary language are needed.
 */
function bogoext_acf_opt_validate_post_id( $post_id, $original ) {
	if ( ! is_string( $post_id ) ) {
		return $post_id;
	}

	$ids = bogoext_acf_opt_ids();

	if ( ! isset( $ids[ $post_id ] ) ) {
		return $post_id;
	}

	$locale = bogoext_acf_opt_locale();

	if ( '' === $locale || ! bogo_is_available_locale( $locale ) ) {
		return $post_id;
	}

	return $post_id . '_' . $locale;
}

/* ---------------------------------------------------------------------
 * Fallback to the default language
 * ------------------------------------------------------------------- */

/**
 * Top-level field a (sub) field belongs to.
 *
 * @param array $field Field.
 * @return array
 */
function bogoext_acf_opt_root_field( $field ) {
	$root = $field;

	for ( $depth = 0; $depth < 20 && ! empty( $root['parent'] ); $depth++ ) {
		$parent = acf_get_field( $root['parent'] );

		if ( ! $parent ) {
			break; // The parent is the field group.
		}

		$root = $parent;
	}

	return $root;
}

/**
 * Whether a field marked as shared by all languages.
 */
function bogoext_acf_opt_is_shared( $field ) {
	return ! empty( $field['bogoext_shared'] );
}

/**
 * Whether a field of a localized options ID is read from the default
 * language: shared fields, and fields this language has no value of yet
 * (decided per top-level field, so a repeater is never mixed row by row).
 *
 * @param string $localized Localized ID, e.g. options_de_DE.
 * @param array  $field     Field.
 * @return bool
 */
function bogoext_acf_opt_use_base( $localized, $field ) {
	static $cache = array();

	$root = bogoext_acf_opt_root_field( $field );

	if ( bogoext_acf_opt_is_shared( $root ) ) {
		return true;
	}

	$key = $localized . '|' . ( $root['key'] ?? '' );

	if ( ! isset( $cache[ $key ] ) ) {
		// Top-level names are never prefixed (no row index), so the stored
		// option of the root field tells whether the language has a value.
		$cache[ $key ] = null === get_option( $localized . '_' . $root['name'], null );
	}

	return $cache[ $key ];
}

function bogoext_acf_opt_pre_load_value( $value, $post_id, $field ) {
	if ( null !== $value ) {
		return $value;
	}

	$parsed = bogoext_acf_opt_parse( $post_id );

	if ( ! $parsed || empty( $field['name'] ) || ! bogoext_acf_opt_use_base( $post_id, $field ) ) {
		return $value;
	}

	return acf_get_value( $parsed[0], $field );
}

/**
 * get_field( 'name', 'option' ) finds the field through the stored reference
 * ("_options_de_DE_name" → field key). A language without its own value has
 * no reference yet – the default language's one is used (keys are the same).
 */
function bogoext_acf_opt_pre_load_reference( $reference, $field_name, $post_id ) {
	if ( null !== $reference ) {
		return $reference;
	}

	$parsed = bogoext_acf_opt_parse( $post_id );

	if ( ! $parsed || null !== get_option( '_' . $post_id . '_' . $field_name, null ) ) {
		return $reference;
	}

	$base = acf_get_reference( $field_name, $parsed[0] );

	return $base ? $base : $reference;
}

/* ---------------------------------------------------------------------
 * Admin: field setting, shared fields, language tabs, reset
 * ------------------------------------------------------------------- */

function bogoext_acf_opt_field_setting( $field ) {
	if ( in_array( $field['type'] ?? '', array( 'tab', 'accordion', 'message' ), true ) ) {
		return;
	}

	acf_render_field_setting( $field, array(
		'label'        => __( 'Shared by all languages', 'bogo-extended' ),
		'instructions' => __( 'Options pages only (Bogo Extended): one value for every language, edited in the default language. Applies to top-level fields; sub fields follow their parent.', 'bogo-extended' ),
		'name'         => 'bogoext_shared',
		'type'         => 'true_false',
		'ui'           => 1,
	), true );
}

/**
 * Options page in a secondary language: shared fields become a note
 * (and are not submitted, so the default value is never overwritten).
 */
function bogoext_acf_opt_prepare_field( $field ) {
	if ( ! $field || ! bogoext_acf_opt_is_shared( $field ) || '' === bogoext_acf_opt_locale() || ! bogoext_acf_opt_current_page() ) {
		return $field;
	}

	$root = bogoext_acf_opt_root_field( $field );

	if ( ( $root['key'] ?? '' ) !== ( $field['key'] ?? '' ) ) {
		return $field; // Only top-level fields are shared.
	}

	return array_merge( $field, array(
		'type'         => 'message',
		'message'      => esc_html__( 'Shared by all languages – edit this field in the default language.', 'bogo-extended' ),
		'instructions' => '',
		'required'     => 0,
		'esc_html'     => 0,
		'new_lines'    => '',
	) );
}

/**
 * Options page being displayed (with a translated post_id), or null.
 *
 * @return array|null
 */
function bogoext_acf_opt_current_page() {
	global $plugin_page;

	if ( ! is_admin() || empty( $plugin_page ) || ! function_exists( 'acf_get_options_page' ) ) {
		return null;
	}

	$page = acf_get_options_page( $plugin_page );

	if ( ! $page || empty( $page['post_id'] ) || is_numeric( $page['post_id'] ) ) {
		return null;
	}

	$id = 'option' === $page['post_id'] ? 'options' : (string) $page['post_id'];

	if ( ! isset( bogoext_acf_opt_ids()[ $id ] ) ) {
		return null;
	}

	$page['bogoext_id'] = $id;

	return $page;
}

/**
 * Whether a language has saved values of an options ID.
 */
function bogoext_acf_opt_has_values( $id, $locale ) {
	global $wpdb;

	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT 1 FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1",
		$wpdb->esc_like( $id . '_' . $locale . '_' ) . '%'
	) );
}

function bogoext_acf_opt_tabs() {
	$page = bogoext_acf_opt_current_page();

	if ( ! $page ) {
		return;
	}

	$current = bogoext_acf_opt_locale();
	$current = '' === $current ? bogoext_default_locale() : $current;
	$base    = remove_query_arg( array( BOGOEXT_ACF_OPT_PARAM, 'message', 'bogoext_reset' ) );
	$locales = array_intersect( array_keys( bogoext_languages() ), bogoext_user_locales() );

	// Default language first.
	usort( $locales, static function ( $a, $b ) {
		return (int) bogo_is_default_locale( $b ) - (int) bogo_is_default_locale( $a );
	} );

	echo '<div class="notice notice-info bogoext-acf-opt"><nav class="bogoext-acf-opt__tabs" aria-label="' . esc_attr__( 'Language of the values', 'bogo-extended' ) . '">';

	foreach ( $locales as $locale ) {
		$is_default = bogo_is_default_locale( $locale );
		$url        = $is_default ? $base : add_query_arg( BOGOEXT_ACF_OPT_PARAM, $locale, $base );
		$status     = $is_default ? '' : ( bogoext_acf_opt_has_values( $page['bogoext_id'], $locale ) ? ' is-own' : ' is-inherited' );

		printf(
			'<a href="%1$s" class="bogoext-acf-opt__tab%2$s%3$s"%4$s>%5$s</a>',
			esc_url( $url ),
			$locale === $current ? ' is-current' : '',
			esc_attr( $status ),
			$locale === $current ? ' aria-current="page"' : '',
			esc_html( bogoext_language_name( $locale ) )
		);
	}

	echo '</nav><p>';

	if ( bogo_is_default_locale( $current ) ) {
		esc_html_e( 'You are editing the values of the default language. Languages without their own values use them.', 'bogo-extended' );
	} elseif ( bogoext_acf_opt_has_values( $page['bogoext_id'], $current ) ) {
		printf(
			/* translators: %s: language name */
			esc_html__( 'You are editing the values for %s. Fields added later and fields shared by all languages use the default language.', 'bogo-extended' ),
			'<strong>' . esc_html( bogoext_language_name( $current ) ) . '</strong>'
		);

		$reset = wp_nonce_url( add_query_arg( array(
			'action' => 'bogoext_acf_opt_reset',
			'id'     => $page['bogoext_id'],
			'locale' => $current,
			'back'   => rawurlencode( $base ),
		), admin_url( 'admin-post.php' ) ), 'bogoext_acf_opt_reset_' . $page['bogoext_id'] . '_' . $current );

		printf(
			' <a href="%1$s" class="bogoext-acf-opt__reset" onclick="return confirm(%2$s);">%3$s</a>',
			esc_url( $reset ),
			esc_attr( wp_json_encode( __( 'Delete all values of this language? The default language values will be used again.', 'bogo-extended' ) ) ),
			esc_html__( 'Delete this language\'s values', 'bogo-extended' )
		);
	} else {
		printf(
			/* translators: %s: language name */
			esc_html__( '%s has no values of its own yet – the form shows the default language values, and so does the site. Saving creates a separate copy for this language.', 'bogo-extended' ),
			'<strong>' . esc_html( bogoext_language_name( $current ) ) . '</strong>'
		);
	}

	echo '</p></div>';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	if ( ! empty( $_GET['bogoext_reset'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The values of this language were deleted.', 'bogo-extended' ) . '</p></div>';
	}

	bogoext_enqueue_admin_style();
}

/**
 * Deletes all values of one language of an options ID.
 */
function bogoext_acf_opt_reset() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
	$id     = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
	$locale = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : '';
	$back   = isset( $_GET['back'] ) ? esc_url_raw( rawurldecode( wp_unslash( $_GET['back'] ) ) ) : admin_url();
	// phpcs:enable

	check_admin_referer( 'bogoext_acf_opt_reset_' . $id . '_' . $locale );

	$capability = 'manage_options';

	foreach ( (array) ( function_exists( 'acf_get_options_pages' ) ? acf_get_options_pages() : array() ) as $page ) {
		if ( ( 'option' === $page['post_id'] ? 'options' : (string) $page['post_id'] ) === $id ) {
			$capability = $page['capability'];
			break;
		}
	}

	if ( ! current_user_can( $capability ) || ! isset( bogoext_acf_opt_ids()[ $id ] )
		|| bogo_is_default_locale( $locale ) || ! in_array( $locale, bogoext_user_locales(), true ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'bogo-extended' ), 403 );
	}

	global $wpdb;

	$prefix = $id . '_' . $locale . '_';
	$names  = $wpdb->get_col( $wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( $prefix ) . '%',
		$wpdb->esc_like( '_' . $prefix ) . '%'
	) );

	foreach ( $names as $name ) {
		delete_option( $name );
	}

	wp_safe_redirect( add_query_arg( array( BOGOEXT_ACF_OPT_PARAM => $locale, 'bogoext_reset' => 1 ), $back ) );
	exit;
}
