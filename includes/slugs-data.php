<?php
/**
 * Translated URL bases – data model, list of translatable objects and
 * validation. Loaded on every request (small); the runtime part lives in
 * modules/slugs.php.
 *
 * Option `bogoext_slugs`:
 * [
 *   'pt'  => [ post_type => [ locale => [ 'base' => 'projekte', 'archive' => 'projekte-archiv' ] ] ],
 *   'tax' => [ taxonomy  => [ locale => [ 'base' => 'kategorie' ] ] ],
 * ]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Raw option.
 *
 * @return array
 */
function bogoext_slugs_option() {
	$option = get_option( 'bogoext_slugs', array() );

	return is_array( $option ) ? $option : array();
}

/**
 * Post types whose URL base can be translated: localizable, public,
 * with their own rewrite slug (posts and pages have none).
 *
 * @return WP_Post_Type[] name => object
 */
function bogoext_slugs_post_types() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$types = array();

	foreach ( bogo_localizable_post_types() as $name ) {
		$object = get_post_type_object( $name );

		if ( ! $object || in_array( $name, array( 'post', 'page', 'attachment', 'wp_block' ), true ) ) {
			continue;
		}

		if ( ! $object->publicly_queryable || empty( $object->rewrite['slug'] ) ) {
			continue;
		}

		$types[ $name ] = $object;
	}

	// Post types are registered on `init` – cache only after that.
	if ( did_action( 'wp_loaded' ) ) {
		$cache = $types;
	}

	return $types;
}

/**
 * Taxonomies whose URL base can be translated: attached to localizable
 * post types, public, with a rewrite slug.
 *
 * @return WP_Taxonomy[] name => object
 */
function bogoext_slugs_taxonomies() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$taxonomies = array();

	foreach ( get_object_taxonomies( bogo_localizable_post_types(), 'objects' ) as $name => $object ) {
		if ( 'post_format' === $name || ! $object->publicly_queryable || empty( $object->rewrite['slug'] ) ) {
			continue;
		}

		$taxonomies[ $name ] = $object;
	}

	if ( did_action( 'wp_loaded' ) ) {
		$cache = $taxonomies;
	}

	return $taxonomies;
}

/**
 * Archive slug of a post type ('' = no archive).
 *
 * @param WP_Post_Type $object Post type.
 * @return string
 */
function bogoext_slugs_archive_slug( $object ) {
	if ( ! $object->has_archive ) {
		return '';
	}

	return true === $object->has_archive ? (string) $object->rewrite['slug'] : (string) $object->has_archive;
}

/**
 * Sanitizes a base like "Nasze Projekty/2024" → "nasze-projekty/2024".
 *
 * @param mixed $value Raw value.
 * @return string
 */
function bogoext_slugs_sanitize_base( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$parts = array_filter( array_map(
		static function ( $part ) {
			return sanitize_title( $part );
		},
		explode( '/', trim( (string) $value, " /\t\n\r" ) )
	), 'strlen' );

	return implode( '/', $parts );
}

/**
 * Words that must never be used as a base.
 *
 * @return string[]
 */
function bogoext_slugs_reserved() {
	global $wp_rewrite;

	$reserved = array(
		'feed', 'rdf', 'rss', 'rss2', 'atom', 'page', 'comments', 'search', 'author', 'embed',
		'trackback', 'attachment', 'wp-admin', 'wp-content', 'wp-includes', 'wp-json',
		'wp-login.php', 'type', 'date', 'sitemap', 'wp-sitemap.xml',
		rest_get_url_prefix(),
	);

	if ( $wp_rewrite ) {
		$reserved[] = $wp_rewrite->pagination_base;
		$reserved[] = $wp_rewrite->comments_base;
		$reserved[] = $wp_rewrite->search_base;
		$reserved[] = $wp_rewrite->author_base;
	}

	foreach ( bogo_available_locales() as $locale ) {
		$reserved[] = bogo_lang_slug( $locale );
	}

	return array_values( array_unique( array_filter( array_map( 'strval', $reserved ) ) ) );
}

/**
 * Validates submitted bases. Invalid entries are dropped (the previous
 * value is kept) and reported with add_settings_error().
 *
 * @param mixed $input Raw input.
 * @return array
 */
function bogoext_slugs_sanitize_option( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$previous = bogoext_slugs_option();
	$clean    = array( 'pt' => array(), 'tax' => array() );
	$reserved = bogoext_slugs_reserved();
	$locales  = array_keys( bogoext_secondary_languages() );

	$items = array(); // [ kind, name, field, original ]

	foreach ( bogoext_slugs_post_types() as $name => $object ) {
		$items[] = array( 'pt', $name, 'base', (string) $object->rewrite['slug'], $object->labels->name );

		$archive = bogoext_slugs_archive_slug( $object );

		if ( $archive && $archive !== $object->rewrite['slug'] ) {
			$items[] = array( 'pt', $name, 'archive', $archive, $object->labels->name );
		}
	}

	foreach ( bogoext_slugs_taxonomies() as $name => $object ) {
		$items[] = array( 'tax', $name, 'base', (string) $object->rewrite['slug'], $object->labels->name );
	}

	foreach ( $locales as $locale ) {
		$used = array(); // base => label, all bases that will exist under /{lang}/

		// First pass: originals of items without translation keep their base.
		$values = array();

		foreach ( $items as $item ) {
			list( $kind, $name, $field, $original, $label ) = $item;
			$raw   = $input[ $kind ][ $name ][ $locale ][ $field ] ?? '';
			$value = bogoext_slugs_sanitize_base( $raw );

			if ( $value === $original ) {
				$value = ''; // Same as the original – nothing to translate.
			}

			$values[ $kind . '|' . $name . '|' . $field ] = $value;
		}

		foreach ( $items as $item ) {
			list( $kind, $name, $field, $original, $label ) = $item;
			$key   = $kind . '|' . $name . '|' . $field;
			$value = $values[ $key ];
			$base  = '' === $value ? $original : $value;

			$error = '';

			if ( '' !== $value ) {
				$first = strtok( $value, '/' );

				if ( in_array( $value, $reserved, true ) || in_array( $first, $reserved, true ) ) {
					/* translators: 1: base, 2: language */
					$error = sprintf( __( '"%1$s" (%2$s) is a reserved word.', 'bogo-extended' ), $value, bogoext_language_name( $locale ) );
				} elseif ( function_exists( 'bogo_get_page_by_path' ) && bogo_get_page_by_path( $value, $locale ) ) {
					/* translators: 1: base, 2: language */
					$error = sprintf( __( '"%1$s" (%2$s) is already the address of a page in this language.', 'bogo-extended' ), $value, bogoext_language_name( $locale ) );
				}
			}

			if ( ! $error && isset( $used[ $base ] ) && $used[ $base ] !== $key ) {
				/* translators: 1: base, 2: language */
				$error = sprintf( __( '"%1$s" (%2$s) is used twice – every base must be unique within a language.', 'bogo-extended' ), $base, bogoext_language_name( $locale ) );
			}

			if ( $error ) {
				add_settings_error( 'bogoext_slugs', 'bogoext_slugs_' . md5( $key . $locale ), $error );

				// Keep the previous (valid) value, if any.
				$value = (string) ( $previous[ $kind ][ $name ][ $locale ][ $field ] ?? '' );
				$base  = '' === $value ? $original : $value;
			}

			$used[ $base ] = $key;

			if ( '' !== $value ) {
				$clean[ $kind ][ $name ][ $locale ][ $field ] = $value;
			}
		}
	}

	return $clean;
}

/*
 * Rewrite rules are rebuilt lazily (WordPress regenerates them on the next
 * request when the option is missing) – only when something actually changed.
 * Never on regular page loads.
 */
add_action( 'update_option_bogoext_slugs', 'bogoext_slugs_schedule_flush', 10, 2 );
add_action( 'add_option_bogoext_slugs', 'bogoext_slugs_schedule_flush', 10, 0 );

function bogoext_slugs_schedule_flush( $old = null, $new = null ) {
	if ( null === $old || $old !== $new ) {
		delete_option( 'rewrite_rules' );
	}
}

add_action( 'update_option_bogoext_settings', static function ( $old, $new ) {
	$defaults = bogoext_default_settings();
	$old      = array_merge( $defaults, (array) $old );
	$new      = array_merge( $defaults, (array) $new );

	$old['post_types'] = (array) $old['post_types'];
	$new['post_types'] = (array) $new['post_types'];
	sort( $old['post_types'] );
	sort( $new['post_types'] );

	// Bogo adds language-prefixed rules for translatable post types.
	if ( ! empty( $old['slugs'] ) !== ! empty( $new['slugs'] ) || $old['post_types'] !== $new['post_types'] ) {
		delete_option( 'rewrite_rules' );
	}
}, 10, 2 );
