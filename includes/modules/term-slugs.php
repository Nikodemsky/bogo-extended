<?php
/**
 * Translated term slugs (part of the "Term translations" module).
 *
 *   /category/aktualnosci/            default language
 *   /de/category/neuigkeiten/         German – translated slug
 *   /de/kategorie/neuigkeiten/        … together with a translated base
 *
 * Terms are shared between languages in Bogo, so the translated slug is an
 * alias stored in term meta (_bogoext_slug_{locale}):
 *  - term_link            builds links with the slug of the link's language,
 *  - request              maps an incoming translated slug to the real one
 *                         before WordPress queries the archive,
 *  - template_redirect    301 from the untranslated slug in that language,
 *  - language switcher and hreflang on term archives use each language's slug.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Locale of a URL according to its language prefix (default when none).
 *
 * @param string $url URL.
 * @return string
 */
function bogoext_url_locale( $url ) {
	static $langs = null;
	static $home  = null;

	if ( null === $langs ) {
		$langs = array();

		foreach ( bogo_available_locales() as $locale ) {
			if ( ! bogo_is_default_locale( $locale ) ) {
				$langs[ (string) bogo_lang_slug( $locale ) ] = $locale;
			}
		}

		$home = untrailingslashit( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ) );
	}

	$path = (string) wp_parse_url( $url, PHP_URL_PATH );

	if ( '' !== $home ) {
		if ( 0 !== strpos( $path, $home . '/' ) ) {
			return bogo_get_default_locale();
		}

		$path = substr( $path, strlen( $home ) );
	}

	if ( 0 === strpos( $path, '/index.php/' ) ) {
		$path = substr( $path, 10 );
	}

	$first = (string) strtok( ltrim( $path, '/' ), '/' );

	return $langs[ $first ] ?? bogo_get_default_locale();
}

/**
 * Whether slugs of this taxonomy are translated.
 */
function bogoext_term_slugs_enabled( $taxonomy ) {
	return in_array( $taxonomy, bogoext_localizable_taxonomies(), true );
}

/**
 * Path segment(s) of a term in a language, as used in its permalink
 * ("parent/child" for hierarchical rewrites, else "slug").
 *
 * @param WP_Term $term   Term.
 * @param string  $locale Locale.
 * @return string
 */
function bogoext_term_path( WP_Term $term, $locale ) {
	$taxonomy = get_taxonomy( $term->taxonomy );
	$ids      = array( $term->term_id );

	if ( $taxonomy && ! empty( $taxonomy->rewrite['hierarchical'] ) ) {
		$ids = array_merge( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ), $ids );
	}

	$segments = array();

	foreach ( $ids as $id ) {
		$translated = bogoext_term_slug( $id, $locale );

		if ( '' === $translated ) {
			$item       = $id === $term->term_id ? $term : get_term( $id, $term->taxonomy );
			$translated = $item instanceof WP_Term ? $item->slug : '';
		}

		$segments[] = $translated;
	}

	return implode( '/', $segments );
}

/**
 * Replaces the term path of one language with the one of another language.
 *
 * @return string
 */
function bogoext_term_swap_path( $url, WP_Term $term, $from_locale, $to_locale ) {
	$from = bogoext_term_path( $term, $from_locale );
	$to   = bogoext_term_path( $term, $to_locale );

	if ( $from === $to || '' === $from ) {
		return $url;
	}

	$parts = wp_parse_url( $url );

	if ( false === $parts || empty( $parts['path'] ) ) {
		return $url;
	}

	$path   = $parts['path'];
	$needle = '/' . $from;
	$pos    = strrpos( $path, $needle . '/' );

	if ( false === $pos && substr( $path, -strlen( $needle ) ) === $needle ) {
		$pos = strlen( $path ) - strlen( $needle );
	}

	if ( false === $pos ) {
		return $url;
	}

	$parts['path'] = substr_replace( $path, '/' . $to, $pos, strlen( $needle ) );

	return function_exists( 'bogoext_slugs_build_url' )
		? bogoext_slugs_build_url( $parts, $parts['path'] )
		: str_replace( $path, $parts['path'], $url );
}

/* ---------------------------------------------------------------------
 * Links
 * ------------------------------------------------------------------- */

add_filter( 'term_link', 'bogoext_term_slug_link', 25, 3 );

/**
 * WordPress built the link with the original slug; use the slug of the
 * language the link points to (its prefix was added by Bogo).
 */
function bogoext_term_slug_link( $link, $term, $taxonomy ) {
	if ( ! $term instanceof WP_Term || ! bogoext_term_slugs_enabled( $taxonomy ) ) {
		return $link;
	}

	$locale = bogoext_url_locale( $link );

	if ( bogo_is_default_locale( $locale ) ) {
		return $link;
	}

	return bogoext_term_swap_path( $link, $term, bogo_get_default_locale(), $locale );
}

/* ---------------------------------------------------------------------
 * Incoming requests
 * ------------------------------------------------------------------- */

add_filter( 'request', 'bogoext_term_slug_request', 5 );

/**
 * /de/kategorie/neuigkeiten/ → category_name=aktualnosci
 *
 * @param array $query_vars Query vars.
 * @return array
 */
function bogoext_term_slug_request( $query_vars ) {
	if ( is_admin() || empty( $query_vars['lang'] ) ) {
		return $query_vars;
	}

	$locale = bogo_get_closest_locale( (string) $query_vars['lang'] );

	if ( ! $locale || bogo_is_default_locale( $locale ) ) {
		return $query_vars;
	}

	foreach ( bogoext_localizable_taxonomies() as $taxonomy ) {
		$var = bogoext_term_query_var( $taxonomy );

		if ( '' === $var || empty( $query_vars[ $var ] ) || ! is_string( $query_vars[ $var ] ) ) {
			continue;
		}

		$segments = explode( '/', $query_vars[ $var ] );

		foreach ( $segments as $i => $segment ) {
			$term = bogoext_term_by_translated_slug( $taxonomy, $locale, $segment )
				?: bogoext_term_by_translated_slug( $taxonomy, $locale, sanitize_title( $segment ) );

			if ( $term ) {
				$segments[ $i ] = $term->slug;
			}
		}

		$query_vars[ $var ] = implode( '/', $segments );
	}

	return $query_vars;
}

/**
 * Query var of a taxonomy in pretty permalinks.
 */
function bogoext_term_query_var( $taxonomy ) {
	if ( 'category' === $taxonomy ) {
		return 'category_name';
	}

	if ( 'post_tag' === $taxonomy ) {
		return 'tag';
	}

	$object = get_taxonomy( $taxonomy );

	return ( $object && $object->query_var ) ? (string) $object->query_var : '';
}

/* ---------------------------------------------------------------------
 * 301 from the untranslated slug: /de/category/aktualnosci/ → …/neuigkeiten/
 * ------------------------------------------------------------------- */

add_action( 'template_redirect', 'bogoext_term_slug_redirect', 6 );

function bogoext_term_slug_redirect() {
	if ( ! ( is_category() || is_tag() || is_tax() ) || is_feed() || is_preview() ) {
		return;
	}

	$term = get_queried_object();

	if ( ! $term instanceof WP_Term || ! bogoext_term_slugs_enabled( $term->taxonomy ) ) {
		return;
	}

	$locale = get_locale();

	if ( bogo_is_default_locale( $locale ) || '' === bogoext_term_slug( $term->term_id, $locale ) ) {
		return;
	}

	if ( empty( $_SERVER['REQUEST_URI'] ) || empty( $_SERVER['HTTP_HOST'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared and passed to wp_safe_redirect().
	$current = ( is_ssl() ? 'https://' : 'http://' ) . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] );
	$target  = bogoext_term_swap_path( $current, $term, bogo_get_default_locale(), $locale );

	if ( $target !== $current ) {
		wp_safe_redirect( $target, 301, 'Bogo Extended' );
		exit;
	}
}

/* ---------------------------------------------------------------------
 * Language switcher and hreflang on term archives
 * ------------------------------------------------------------------- */

add_filter( 'bogo_language_switcher_links', 'bogoext_term_slug_switcher', 16 );

function bogoext_term_slug_switcher( $links ) {
	$term = bogoext_term_slug_queried_term();

	if ( ! $term ) {
		return $links;
	}

	$current = get_locale();

	foreach ( (array) $links as $i => $link ) {
		if ( ! empty( $link['href'] ) && ! empty( $link['locale'] ) ) {
			$links[ $i ]['href'] = bogoext_term_swap_path( $link['href'], $term, $current, $link['locale'] );
		}
	}

	return $links;
}

add_filter( 'bogo_rel_alternate_hreflang', 'bogoext_term_slug_hreflang', 11 );

function bogoext_term_slug_hreflang( $languages ) {
	$term = bogoext_term_slug_queried_term();

	if ( ! $term ) {
		return $languages;
	}

	$current = get_locale();

	foreach ( (array) $languages as $i => $language ) {
		if ( ! empty( $language['href'] ) ) {
			$languages[ $i ]['href'] = bogoext_term_swap_path( $language['href'], $term, $current, bogoext_url_locale( $language['href'] ) );
		}
	}

	return $languages;
}

/**
 * Queried term on a term archive with translatable slugs, or null.
 */
function bogoext_term_slug_queried_term() {
	if ( ! ( is_category() || is_tag() || is_tax() ) ) {
		return null;
	}

	$term = get_queried_object();

	return ( $term instanceof WP_Term && bogoext_term_slugs_enabled( $term->taxonomy ) ) ? $term : null;
}
