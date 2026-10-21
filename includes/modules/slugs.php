<?php
/**
 * Module: translated URL bases of custom post types and taxonomies.
 *
 *   /realizacje/projekt-x/        (default language)
 *   /de/projekte/projekt-x/       instead of /de/realizacje/projekt-x/
 *   /de/kategorie/neuigkeiten/    instead of /de/category/…
 *
 * How it works (and why it costs practically nothing at runtime):
 *  - Rewrite rules: extra rules per language are added to the rules Bogo
 *    already generates. They are stored by WordPress in the `rewrite_rules`
 *    option and rebuilt only when the bases or the module setting change.
 *  - Links: post_type_link / post_type_archive_link / term_link swap one
 *    path segment (a string replacement, no queries).
 *  - Old addresses (/de/realizacje/…) answer with a 301 to the new ones.
 *  - Language switcher and hreflang on archive pages get the correct base
 *    for every language.
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'permalink_structure' ) ) {
	return; // Plain permalinks (?p=123) have no bases to translate.
}

/**
 * Normalized map of everything that has a translated base.
 *
 * @param bool $reset Rebuild the cache.
 * @return array List of [ kind, name, field, with_front, original, by_locale[] ].
 */
function bogoext_slugs_entries( $reset = false ) {
	static $entries = null;

	if ( null !== $entries && ! $reset ) {
		return $entries;
	}

	$option = bogoext_slugs_option();
	$list   = array();

	foreach ( bogoext_slugs_post_types() as $name => $object ) {
		$with_front = ! empty( $object->rewrite['with_front'] );
		$base       = (string) $object->rewrite['slug'];
		$archive    = bogoext_slugs_archive_slug( $object );
		$by_locale  = array();
		$by_archive = array();

		foreach ( (array) ( $option['pt'][ $name ] ?? array() ) as $locale => $fields ) {
			if ( ! empty( $fields['base'] ) ) {
				$by_locale[ $locale ] = $fields['base'];
			}

			if ( ! empty( $fields['archive'] ) ) {
				$by_archive[ $locale ] = $fields['archive'];
			} elseif ( ! empty( $fields['base'] ) && $archive === $base ) {
				$by_archive[ $locale ] = $fields['base'];
			}
		}

		if ( $by_locale ) {
			$list[] = array( 'pt', $name, 'base', $with_front, $base, $by_locale );
		}

		if ( $archive && $archive !== $base && $by_archive ) {
			$list[] = array( 'pt', $name, 'archive', $with_front, $archive, $by_archive );
		}
	}

	foreach ( bogoext_slugs_taxonomies() as $name => $object ) {
		$by_locale = array();

		foreach ( (array) ( $option['tax'][ $name ] ?? array() ) as $locale => $fields ) {
			if ( ! empty( $fields['base'] ) ) {
				$by_locale[ $locale ] = $fields['base'];
			}
		}

		if ( $by_locale ) {
			$list[] = array( 'tax', $name, 'base', ! empty( $object->rewrite['with_front'] ), (string) $object->rewrite['slug'], $by_locale );
		}
	}

	if ( did_action( 'wp_loaded' ) ) {
		$entries = $list;
	}

	return $list;
}

/**
 * Translated base of a post type / taxonomy for a locale ('' if none).
 */
function bogoext_slugs_get( $kind, $name, $locale, $field = 'base' ) {
	$option = bogoext_slugs_option();

	return (string) ( $option[ $kind ][ $name ][ $locale ][ $field ] ?? '' );
}

/* ---------------------------------------------------------------------
 * Rewrite rules
 * ------------------------------------------------------------------- */

add_filter( 'rewrite_rules_array', 'bogoext_slugs_rewrite_rules', 20 );

/**
 * @param array $rules Rules (Bogo's language rules already included).
 * @return array
 */
function bogoext_slugs_rewrite_rules( $rules ) {
	global $wp_rewrite;

	if ( ! function_exists( 'bogo_generate_rewrite_rules' ) || ! function_exists( 'bogo_add_lang_to_permastruct' ) ) {
		return $rules;
	}

	$tag_index = array_search( '%lang%', (array) $wp_rewrite->rewritecode, true );

	if ( false === $tag_index ) {
		return $rules;
	}

	$original_regex = $wp_rewrite->rewritereplace[ $tag_index ];
	$extra          = array();
	$option         = bogoext_slugs_option();

	foreach ( bogoext_secondary_languages() as $locale => $name ) {
		$lang = bogo_lang_slug( $locale );

		if ( '' === (string) $lang ) {
			continue;
		}

		// Rules of this pass match only this language.
		$wp_rewrite->rewritereplace[ $tag_index ] = '(' . $lang . ')';

		foreach ( bogoext_slugs_post_types() as $type => $object ) {
			$fields = $option['pt'][ $type ][ $locale ] ?? array();
			$slug   = (string) $object->rewrite['slug'];

			// Archive rules first, otherwise "feed"/"page" would be taken for a post slug.
			$archive = bogoext_slugs_archive_slug( $object );

			if ( $archive ) {
				$translated = ! empty( $fields['archive'] ) ? $fields['archive'] : ( ( $archive === $slug && ! empty( $fields['base'] ) ) ? $fields['base'] : '' );

				if ( $translated ) {
					$extra += bogoext_slugs_archive_rules( $type, $object, $translated, $lang );
				}
			}

			if ( ! empty( $fields['base'] ) ) {
				$struct = bogoext_slugs_replace_base( (string) $wp_rewrite->get_extra_permastruct( $type ), $slug, $fields['base'] );

				if ( $struct ) {
					$extra += bogo_generate_rewrite_rules( bogo_add_lang_to_permastruct( $struct ), $object->rewrite );
				}
			}
		}

		foreach ( bogoext_slugs_taxonomies() as $taxonomy => $object ) {
			$base = $option['tax'][ $taxonomy ][ $locale ]['base'] ?? '';

			if ( $base ) {
				$struct = bogoext_slugs_replace_base( (string) $wp_rewrite->get_extra_permastruct( $taxonomy ), (string) $object->rewrite['slug'], $base );

				if ( $struct ) {
					$extra += bogo_generate_rewrite_rules( bogo_add_lang_to_permastruct( $struct ), $object->rewrite );
				}
			}
		}
	}

	$wp_rewrite->rewritereplace[ $tag_index ] = $original_regex;

	// Ours first – they are more specific than Bogo's generic language rules.
	return $extra + $rules;
}

/**
 * Replaces the static base in a permastruct ("/blog/realizacje/%realizacje%").
 *
 * @return string '' when the base was not found.
 */
function bogoext_slugs_replace_base( $struct, $old, $new ) {
	$needle = '/' . trim( $old, '/' ) . '/';
	$pos    = strpos( $struct, $needle );

	if ( false === $pos ) {
		return '';
	}

	return substr_replace( $struct, '/' . trim( $new, '/' ) . '/', $pos, strlen( $needle ) );
}

/**
 * Archive, feed and pagination rules (mirrors Bogo's own archive rules).
 */
function bogoext_slugs_archive_rules( $type, $object, $archive, $lang ) {
	global $wp_rewrite;

	$path = ! empty( $object->rewrite['with_front'] )
		? substr( $wp_rewrite->front, 1 ) . $archive
		: $wp_rewrite->root . $archive;

	$rules = array(
		"({$lang})/{$path}/?$" => 'index.php?lang=$matches[1]&post_type=' . $type,
	);

	if ( ! empty( $object->rewrite['feeds'] ) && $wp_rewrite->feeds ) {
		$feeds = '(' . trim( implode( '|', $wp_rewrite->feeds ) ) . ')';

		$rules[ "({$lang})/{$path}/feed/{$feeds}/?$" ] = 'index.php?lang=$matches[1]&post_type=' . $type . '&feed=$matches[2]';
		$rules[ "({$lang})/{$path}/{$feeds}/?$" ]      = 'index.php?lang=$matches[1]&post_type=' . $type . '&feed=$matches[2]';
	}

	if ( ! empty( $object->rewrite['pages'] ) ) {
		$rules[ "({$lang})/{$path}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$" ] = 'index.php?lang=$matches[1]&post_type=' . $type . '&paged=$matches[2]';
	}

	return $rules;
}

/* ---------------------------------------------------------------------
 * URL normalization – the single place that swaps bases
 * ------------------------------------------------------------------- */

/**
 * Precomputed lookup tables (built once per request, after wp_loaded).
 *
 * @return array{home:string,langs:array,targets:array,candidates:array}
 */
function bogoext_slugs_runtime() {
	global $wp_rewrite;

	static $runtime = null;

	if ( null !== $runtime ) {
		return $runtime;
	}

	$data = array(
		'home'       => untrailingslashit( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ) ),
		'langs'      => array(), // URL prefix => locale
		'targets'    => array(), // locale => [ "/base" => true ]
		'candidates' => array(), // locale => [ [ from, to ], … ] longest first
	);

	$default = bogo_get_default_locale();
	$front   = trim( (string) $wp_rewrite->front, '/' );
	$entries = bogoext_slugs_entries();

	foreach ( bogo_available_locales() as $locale ) {
		$slug = (string) bogo_lang_slug( $locale );

		if ( '' !== $slug && $locale !== $default ) {
			$data['langs'][ $slug ] = $locale;
		}

		$targets    = array();
		$candidates = array();

		foreach ( $entries as $entry ) {
			list( , , , $with_front, $original, $by_locale ) = $entry;
			$lead   = ( $with_front && $front ) ? '/' . $front : '';
			$target = $by_locale[ $locale ] ?? $original;

			$targets[ $lead . '/' . $target ] = true;

			foreach ( array_unique( array_merge( array( $original ), array_values( $by_locale ) ) ) as $variant ) {
				if ( $variant !== $target ) {
					$candidates[] = array( $lead . '/' . $variant, $lead . '/' . $target );
				}
			}
		}

		usort( $candidates, static function ( $a, $b ) {
			return strlen( $b[0] ) - strlen( $a[0] );
		} );

		$data['targets'][ $locale ]    = $targets;
		$data['candidates'][ $locale ] = $candidates;
	}

	if ( did_action( 'wp_loaded' ) ) {
		$runtime = $data;
	}

	return $data;
}

/**
 * Makes sure a URL uses the base of the language it is in:
 *   /de/realizacje/x  → /de/projekte/x
 *   /projekte/        → /realizacje/   (Bogo's switcher on a German archive)
 *
 * Pure string work on precomputed tables – no queries.
 *
 * @param string $url URL.
 * @return string
 */
function bogoext_slugs_normalize_url( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return $url;
	}

	$rt = bogoext_slugs_runtime();

	if ( ! $rt['candidates'] ) {
		return $url;
	}

	$parts = wp_parse_url( $url );

	if ( false === $parts || empty( $parts['path'] ) ) {
		return $url;
	}

	$path   = $parts['path'];
	$prefix = $rt['home'];

	if ( '' !== $prefix && 0 !== strpos( $path, $prefix . '/' ) ) {
		return $url;
	}

	$rest = substr( $path, strlen( $prefix ) ); // "/de/realizacje/x/"

	if ( 0 === strpos( $rest, '/index.php/' ) ) {
		$prefix .= '/index.php';
		$rest    = substr( $rest, 10 );
	}

	$locale = bogo_get_default_locale();
	$slash  = strpos( $rest, '/', 1 );
	$first  = false === $slash ? substr( $rest, 1 ) : substr( $rest, 1, $slash - 1 );

	if ( isset( $rt['langs'][ $first ] ) ) {
		$locale  = $rt['langs'][ $first ];
		$prefix .= '/' . $first;
		$rest    = substr( $rest, strlen( $first ) + 1 );
	}

	if ( empty( $rt['candidates'][ $locale ] ) ) {
		return $url;
	}

	$is_at = static function ( $segment ) use ( $rest ) {
		return $rest === $segment || 0 === strpos( $rest, $segment . '/' );
	};

	// Already in the right form for this language? Leave it.
	foreach ( $rt['targets'][ $locale ] as $segment => $unused ) {
		if ( $is_at( $segment ) ) {
			return $url;
		}
	}

	foreach ( $rt['candidates'][ $locale ] as $candidate ) {
		if ( $is_at( $candidate[0] ) ) {
			return bogoext_slugs_build_url( $parts, $prefix . $candidate[1] . substr( $rest, strlen( $candidate[0] ) ) );
		}
	}

	return $url;
}

/**
 * Rebuilds a URL from wp_parse_url() parts with a new path.
 */
function bogoext_slugs_build_url( array $parts, $path ) {
	$url = '';

	if ( isset( $parts['scheme'] ) ) {
		$url .= $parts['scheme'] . ':';
	}

	if ( isset( $parts['host'] ) ) {
		$url .= '//' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	}

	$url .= $path;

	if ( isset( $parts['query'] ) ) {
		$url .= '?' . $parts['query'];
	}

	if ( isset( $parts['fragment'] ) ) {
		$url .= '#' . $parts['fragment'];
	}

	return $url;
}

/* ---------------------------------------------------------------------
 * Link filters (after Bogo has added the language prefix at priority 10)
 * ------------------------------------------------------------------- */

add_filter( 'post_type_link', 'bogoext_slugs_post_type_link', 20, 2 );

function bogoext_slugs_post_type_link( $permalink, $post ) {
	if ( ! isset( bogoext_slugs_post_types()[ $post->post_type ] ) ) {
		return $permalink;
	}

	return bogoext_slugs_normalize_url( $permalink );
}

add_filter( 'post_type_archive_link', 'bogoext_slugs_archive_link', 20, 2 );

function bogoext_slugs_archive_link( $link, $post_type ) {
	if ( ! isset( bogoext_slugs_post_types()[ $post_type ] ) ) {
		return $link;
	}

	return bogoext_slugs_normalize_url( $link );
}

add_filter( 'term_link', 'bogoext_slugs_term_link', 20, 3 );

function bogoext_slugs_term_link( $link, $term, $taxonomy ) {
	if ( ! isset( bogoext_slugs_taxonomies()[ $taxonomy ] ) ) {
		return $link;
	}

	return bogoext_slugs_normalize_url( $link );
}

/* ---------------------------------------------------------------------
 * Language switcher + hreflang on archives
 * (Bogo builds them by swapping the language prefix of the current URL,
 * which would keep the base of the current language.)
 * ------------------------------------------------------------------- */

add_filter( 'bogo_language_switcher_links', 'bogoext_slugs_switcher_links', 15 );

function bogoext_slugs_switcher_links( $links ) {
	if ( is_singular() ) {
		return $links; // Built from get_permalink() – already correct.
	}

	foreach ( (array) $links as $i => $link ) {
		if ( ! empty( $link['href'] ) ) {
			$links[ $i ]['href'] = bogoext_slugs_normalize_url( $link['href'] );
		}
	}

	return $links;
}

add_filter( 'bogo_rel_alternate_hreflang', 'bogoext_slugs_hreflang', 10 );

function bogoext_slugs_hreflang( $languages ) {
	if ( is_singular() ) {
		return $languages;
	}

	foreach ( (array) $languages as $i => $language ) {
		if ( ! empty( $language['href'] ) ) {
			$languages[ $i ]['href'] = bogoext_slugs_normalize_url( $language['href'] );
		}
	}

	return $languages;
}

/* ---------------------------------------------------------------------
 * 301 from old addresses (/de/realizacje/x → /de/projekte/x)
 * ------------------------------------------------------------------- */

add_action( 'template_redirect', 'bogoext_slugs_redirect', 5 );

function bogoext_slugs_redirect() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_preview() ) {
		return;
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || empty( $_SERVER['REQUEST_URI'] ) || empty( $_SERVER['HTTP_HOST'] ) ) {
		return;
	}

	$relevant = false;

	if ( is_singular() ) {
		$relevant = isset( bogoext_slugs_post_types()[ get_post_type( get_queried_object_id() ) ] );
	} elseif ( is_post_type_archive() ) {
		$type     = get_query_var( 'post_type' );
		$relevant = is_string( $type ) && isset( bogoext_slugs_post_types()[ $type ] );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term     = get_queried_object();
		$relevant = $term instanceof WP_Term && isset( bogoext_slugs_taxonomies()[ $term->taxonomy ] );
	}

	if ( ! $relevant ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared and passed to wp_safe_redirect().
	$current = ( is_ssl() ? 'https://' : 'http://' ) . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] );

	// Only language-prefixed addresses; default-language URLs are never touched.
	if ( ! bogo_get_lang_from_url( $current ) ) {
		return;
	}

	$target = bogoext_slugs_normalize_url( $current );

	if ( $target !== $current ) {
		wp_safe_redirect( $target, 301, 'Bogo Extended' );
		exit;
	}
}
