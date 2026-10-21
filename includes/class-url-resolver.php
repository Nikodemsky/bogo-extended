<?php
/**
 * Turns internal URLs into the URL of the same content in another language.

 */

defined( 'ABSPATH' ) || exit;

final class BogoExt_URL_Resolver {

	/**
	 * Request cache: url => post ID.
	 *
	 * @var array
	 */
	private static $post_ids = array();

	/**
	 * Locale forced into the next WP_Query built by url_to_postid().
	 *
	 * @var string
	 */
	private static $forced_lang = '';

	/**
	 * Returns the localized version of an internal URL, or null when there is
	 * nothing to change.
	 *
	 * Only links that point to content in the DEFAULT language are touched –
	 * a link that deliberately points to another translation is respected.
	 *
	 * @param string $url    URL as written in the content.
	 * @param string $locale Target locale (defaults to the current one).
	 * @return string|null
	 */
	public static function localize( $url, $locale = '' ) {
		$locale = $locale ? $locale : get_locale();

		if ( bogo_is_default_locale( $locale ) || ! is_string( $url ) || '' === trim( $url ) ) {
			return null;
		}

		$parts = self::split( $url );

		if ( ! $parts ) {
			return null;
		}

		list( $base, $suffix ) = $parts;

		// Home page.
		if ( self::is_home_url( $base ) ) {
			return bogo_url( trailingslashit( set_url_scheme( get_option( 'home' ) ) ), $locale ) . $suffix;
		}

		$post_id = self::url_to_post_id( $base );

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post || ! bogo_is_localizable_post_type( $post->post_type ) ) {
			return null;
		}

		$source_locale = bogo_get_post_locale( $post_id );

		if ( $source_locale === $locale || ! bogo_is_default_locale( $source_locale ) ) {
			return null;
		}

		$translation_id = bogoext_get_translation_id( $post_id, $locale, true );

		if ( ! $translation_id ) {
			return null;
		}

		$permalink = get_permalink( $translation_id );

		return $permalink ? $permalink . $suffix : null;
	}

	/**
	 * Resolves an internal URL to a post ID, respecting the language prefix
	 * in the URL (url_to_postid() alone would search in the current language).
	 *
	 * @param string $url Absolute URL without query/fragment.
	 * @return int
	 */
	public static function url_to_post_id( $url ) {
		if ( isset( self::$post_ids[ $url ] ) ) {
			return self::$post_ids[ $url ];
		}

		$cache_key = 'u2p_' . md5( $url );
		$cached    = wp_cache_get( $cache_key, 'bogoext' );

		if ( false !== $cached ) {
			return self::$post_ids[ $url ] = (int) $cached;
		}

		$lang   = bogo_get_lang_from_url( $url );
		$locale = $lang ? bogo_get_closest_locale( $lang ) : '';

		self::$forced_lang = $locale ? $locale : bogo_get_default_locale();

		// url_to_postid() compares against home_url(), which Bogo prefixes
		// with the current language on the front end – use the raw home.
		$home_filter = has_filter( 'home_url', 'bogo_home_url' );

		if ( false !== $home_filter ) {
			remove_filter( 'home_url', 'bogo_home_url', $home_filter );
		}

		add_action( 'parse_query', array( __CLASS__, 'force_lang' ), 1 );
		$post_id = url_to_postid( $url );
		remove_action( 'parse_query', array( __CLASS__, 'force_lang' ), 1 );

		if ( false !== $home_filter ) {
			add_filter( 'home_url', 'bogo_home_url', $home_filter, 1 );
		}

		self::$forced_lang = '';

		$post_id = (int) $post_id;

		wp_cache_set( $cache_key, $post_id, 'bogoext', HOUR_IN_SECONDS );

		return self::$post_ids[ $url ] = $post_id;
	}

	/**
	 * @internal
	 * @param WP_Query $query Query.
	 */
	public static function force_lang( $query ) {
		if ( self::$forced_lang && empty( $query->query_vars['lang'] ) ) {
			$query->query_vars['lang'] = self::$forced_lang;
		}

		// Only the first query belongs to url_to_postid().
		remove_action( 'parse_query', array( __CLASS__, 'force_lang' ), 1 );
	}

	/**
	 * Splits a URL into [ absolute base URL, "?query#fragment" suffix ].
	 * Returns null for anything that is not an internal content link.
	 *
	 * @param string $url URL.
	 * @return array|null
	 */
	private static function split( $url ) {
		$url = trim( html_entity_decode( $url, ENT_QUOTES ) );

		if ( '' === $url || '#' === $url[0] ) {
			return null;
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		if ( $scheme && ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
			return null; // mailto:, tel:, javascript: ...
		}

		// Raw option: home_url() is already language-prefixed by Bogo on the front end.
		$home = wp_parse_url( get_option( 'home' ) );

		// Protocol-relative or root-relative URLs.
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( $home['scheme'] ?? 'https' ) . ':' . $url;
		} elseif ( '/' === $url[0] ) {
			$url = ( $home['scheme'] ?? 'https' ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . $url;
		} elseif ( ! $scheme ) {
			return null; // Relative paths like "page/" are ambiguous.
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! $host || strtolower( $host ) !== strtolower( $home['host'] ?? '' ) ) {
			return null;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( preg_match( '#/(wp-admin|wp-content|wp-includes|wp-json)(/|$)|/wp-[a-z-]+\.php$|/feed/?$|\.[a-z0-9]{2,5}$#i', $path ) ) {
			return null;
		}

		$suffix = '';
		$cut    = strcspn( $url, '?#' );

		if ( $cut < strlen( $url ) ) {
			$suffix = substr( $url, $cut );
			$url    = substr( $url, 0, $cut );
		}

		// Plain permalinks (?p=123 / ?page_id=) – keep the query for url_to_postid().
		if ( preg_match( '/^\?(p|page_id)=\d+/', $suffix ) ) {
			return array( $url . $suffix, '' );
		}

		return array( $url, $suffix );
	}

	/**
	 * Whether the URL is the site home (in any language).
	 */
	private static function is_home_url( $url ) {
		$home = untrailingslashit( set_url_scheme( get_option( 'home' ), 'http' ) );
		$url  = untrailingslashit( set_url_scheme( $url, 'http' ) );

		if ( $url === $home ) {
			return true;
		}

		$lang = bogo_get_lang_from_url( $url . '/' );

		return $lang && $url === $home . '/' . $lang;
	}
}

/**
 * Public helper: localized URL or the original URL.
 *
 * @param string $url    URL.
 * @param string $locale Target locale.
 * @return string
 */
function bogoext_localize_url( $url, $locale = '' ) {
	$localized = BogoExt_URL_Resolver::localize( $url, $locale );

	return null === $localized ? $url : $localized;
}
