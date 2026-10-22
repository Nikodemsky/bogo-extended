<?php
/**
 * Module: Advanced Custom Fields integration.
 *
 * - post_object / relationship / page_link fields return the translation
 *   of the selected post on secondary-language pages,
 * - link fields get a localized URL (and the translated title when the link
 *   title was the post title),
 * - post pickers in the admin list only default-language posts,
 * - optional "own relations per language" mode (setting acf_own_relations):
 *   pickers on a translated post list posts of that post's language,
 *   "Add translation" maps copied selections to existing translations and
 *   picker items from another language are labelled.
 *
 * Single and multiple values, IDs, objects and URLs are supported; picker
 * queries are extended, never replaced.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ACF' ) && ! function_exists( 'acf' ) ) {
	return;
}

foreach ( array( 'post_object', 'relationship', 'page_link' ) as $bogoext_acf_type ) {
	add_filter( "acf/format_value/type={$bogoext_acf_type}", 'bogoext_acf_format_posts', 20, 3 );
	add_filter( "acf/fields/{$bogoext_acf_type}/query", 'bogoext_acf_picker_originals', 20, 3 );
}
unset( $bogoext_acf_type );

add_filter( 'acf/format_value/type=link', 'bogoext_acf_format_link', 20, 3 );

// "Own relations per language" – every callback checks bogoext_acf_own_relations()
// per field, so the mode can also be switched on for single fields by filter.
add_filter( 'posts_where', 'bogoext_acf_locale_where', 10, 2 );
add_filter( 'bogo_duplicate_post', 'bogoext_acf_duplicate_remap', 20, 3 );

foreach ( array( 'post_object', 'relationship', 'page_link' ) as $bogoext_acf_type ) {
	add_filter( "acf/fields/{$bogoext_acf_type}/result", 'bogoext_acf_result_language', 20, 4 );
}
unset( $bogoext_acf_type );

/**
 * Maps a single formatted value to its translation.
 *
 * @param mixed  $value  WP_Post, ID or URL.
 * @param string $locale Locale.
 * @return mixed
 */
function bogoext_acf_translate_single( $value, $locale ) {
	if ( $value instanceof WP_Post ) {
		if ( ! bogo_is_localizable_post_type( $value->post_type ) || ! bogo_is_default_locale( bogo_get_post_locale( $value->ID ) ) ) {
			return $value;
		}

		$id = bogoext_get_translation_id( $value->ID, $locale, true );

		return $id ? get_post( $id ) : $value;
	}

	if ( is_numeric( $value ) ) {
		$post_id = (int) $value;
		$type    = get_post_type( $post_id );

		if ( ! $type || ! bogo_is_localizable_post_type( $type ) || ! bogo_is_default_locale( bogo_get_post_locale( $post_id ) ) ) {
			return $value;
		}

		$id = bogoext_get_translation_id( $post_id, $locale, true );

		return $id ? $id : $value;
	}

	if ( is_string( $value ) && '' !== $value ) {
		return bogoext_localize_url( $value, $locale ); // page_link returns URLs.
	}

	return $value;
}

function bogoext_acf_format_posts( $value, $post_id, $field ) {
	if ( empty( $value ) || ! bogoext_should_localize_output() ) {
		return $value;
	}

	$locale = get_locale();

	if ( is_array( $value ) ) {
		$ids = array();

		foreach ( $value as $item ) {
			if ( $item instanceof WP_Post ) {
				$ids[] = $item->ID;
			} elseif ( is_numeric( $item ) ) {
				$ids[] = (int) $item;
			}
		}

		BogoExt_Translations::prime( $ids );

		return array_map(
			static function ( $item ) use ( $locale ) {
				return bogoext_acf_translate_single( $item, $locale );
			},
			$value
		);
	}

	return bogoext_acf_translate_single( $value, $locale );
}

function bogoext_acf_format_link( $value, $post_id, $field ) {
	if ( empty( $value ) || ! bogoext_should_localize_output() ) {
		return $value;
	}

	$locale = get_locale();

	if ( is_string( $value ) ) { // return_format = url
		return bogoext_localize_url( $value, $locale );
	}

	if ( ! is_array( $value ) || empty( $value['url'] ) ) {
		return $value;
	}

	$localized = BogoExt_URL_Resolver::localize( $value['url'], $locale );

	if ( ! $localized ) {
		return $value;
	}

	$source_id = BogoExt_URL_Resolver::url_to_post_id( strtok( $value['url'], '?#' ) );
	$target_id = $source_id ? bogoext_get_translation_id( $source_id, $locale, true ) : 0;

	// Replace the title only when it was the (untranslated) post title.
	if ( $source_id && $target_id && isset( $value['title'] )
		&& trim( (string) $value['title'] ) === trim( get_post_field( 'post_title', $source_id, 'raw' ) ) ) {
		$value['title'] = get_the_title( $target_id );
	}

	$value['url'] = $localized;

	return $value;
}

/**
 * Whether a field works in "own relations per language" mode.
 *
 * @param array      $field   ACF field.
 * @param int|string $post_id ACF post ID of the edited object.
 * @return bool
 */
function bogoext_acf_own_relations( $field, $post_id ) {
	return (bool) apply_filters(
		'bogoext_acf_own_relations',
		bogoext_module_enabled( 'acf_own_relations' ),
		$field,
		$post_id
	);
}

/**
 * Locale of the object a field value belongs to; '' when it is not a post of
 * a translatable type (untranslated options pages, terms, users…).
 *
 * @param int|string $post_id ACF post ID ("123", "term_5", "options"…).
 * @return string
 */
function bogoext_acf_object_locale( $post_id ) {
	// Translated ACF options page, e.g. "options_de_DE" (module acf_options).
	$options = function_exists( 'bogoext_acf_opt_parse' ) ? bogoext_acf_opt_parse( $post_id ) : null;

	if ( $options ) {
		return $options[1];
	}

	if ( function_exists( 'acf_decode_post_id' ) ) {
		$decoded = acf_decode_post_id( $post_id );

		if ( empty( $decoded['type'] ) || 'post' !== $decoded['type'] ) {
			return '';
		}

		$post_id = $decoded['id'];
	}

	$post_id = (int) $post_id;
	$type    = $post_id > 0 ? get_post_type( $post_id ) : false;

	if ( ! $type || ! bogo_is_localizable_post_type( $type ) ) {
		return '';
	}

	return (string) bogo_get_post_locale( $post_id );
}

/**
 * Post pickers list only default-language posts (translations are swapped
 * in automatically when rendering). In "own relations" mode a picker on a
 * translated post lists posts of that post's language instead.
 */
function bogoext_acf_picker_originals( $args, $field, $post_id ) {
	if ( ! apply_filters( 'bogoext_acf_hide_translations', true, $field ) ) {
		return $args;
	}

	$locale = bogoext_acf_own_relations( $field, $post_id ) ? bogoext_acf_object_locale( $post_id ) : '';

	if ( '' !== $locale && ! bogo_is_default_locale( $locale ) ) {
		$args['bogoext_acf_locale']         = $locale;
		$args['bogo_suppress_locale_query'] = true;

		return $args;
	}

	$args['meta_query'] = bogoext_merge_meta_query(
		$args['meta_query'] ?? array(),
		bogoext_default_locale_meta_clause()
	);

	return $args;
}

/**
 * Picker query in a secondary language: posts of translatable types in that
 * language plus all posts of types that are not translated at all.
 *
 * @param string   $where WHERE clause.
 * @param WP_Query $query Query.
 * @return string
 */
function bogoext_acf_locale_where( $where, $query ) {
	$locale = $query->get( 'bogoext_acf_locale' );

	if ( ! is_string( $locale ) || '' === $locale || ! bogo_is_available_locale( $locale ) ) {
		return $where;
	}

	global $wpdb;

	$types = array_values( array_filter( (array) bogo_localizable_post_types(), 'post_type_exists' ) );

	if ( ! $types ) {
		return $where;
	}

	$in = implode( ', ', array_fill( 0, count( $types ), '%s' ) );

	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders -- placeholders built above.
	$where .= $wpdb->prepare(
		" AND ( {$wpdb->posts}.post_type NOT IN ( {$in} ) OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS bogoext_acf_l WHERE bogoext_acf_l.post_id = {$wpdb->posts}.ID AND bogoext_acf_l.meta_key = '_locale' AND bogoext_acf_l.meta_value = %s ) )",
		array_merge( $types, array( $locale ) )
	);

	return $where;
}

/**
 * Picker item / selected value from another language than the edited post
 * gets a language label, e.g. "Cardiology (PL)" on an English post.
 */
function bogoext_acf_result_language( $title, $post, $field, $post_id ) {
	if ( ! $post instanceof WP_Post || ! bogo_is_localizable_post_type( $post->post_type ) || ! bogoext_acf_own_relations( $field, $post_id ) ) {
		return $title;
	}

	$locale = bogoext_acf_object_locale( $post_id );

	if ( '' === $locale ) {
		return $title;
	}

	$item_locale = (string) bogo_get_post_locale( $post->ID );

	if ( $item_locale === $locale ) {
		return $title;
	}

	return $title . ' (' . strtoupper( bogoext_language_code( $item_locale ) ) . ')';
}

/**
 * "Add translation": selections copied from the source post point to their
 * published translations in the new language, so the translation starts from
 * the same relations, only localized. Posts without a published translation
 * stay as they are – on the front end they are still swapped for their
 * translation as soon as it is published.
 *
 * @param array   $postarr       Data for wp_insert_post() (meta_input = key => values).
 * @param WP_Post $original_post Source post.
 * @param string  $locale        Target locale.
 * @return array
 */
function bogoext_acf_duplicate_remap( $postarr, $original_post, $locale ) {
	if ( empty( $postarr['meta_input'] ) || ! is_array( $postarr['meta_input'] ) || ! function_exists( 'acf_get_field' ) ) {
		return $postarr;
	}

	$meta = &$postarr['meta_input'];

	foreach ( $meta as $key => $values ) {
		if ( '' === $key || '_' === $key[0] || empty( $meta[ '_' . $key ] ) ) {
			continue;
		}

		$reference = reset( $meta[ '_' . $key ] );

		if ( ! is_string( $reference ) || 0 !== strpos( $reference, 'field_' ) ) {
			continue;
		}

		$field = acf_get_field( $reference );

		if ( ! $field || ! in_array( $field['type'], array( 'post_object', 'relationship', 'page_link' ), true ) ) {
			continue;
		}

		if ( ! bogoext_acf_own_relations( $field, $original_post->ID )
			|| ! apply_filters( 'bogoext_acf_remap_on_duplicate', true, $field, $original_post, $locale ) ) {
			continue;
		}

		foreach ( (array) $values as $i => $value ) {
			$meta[ $key ][ $i ] = bogoext_acf_remap_value( $value, $locale );
		}
	}

	unset( $meta );

	return $postarr;
}

/**
 * Maps stored IDs (single or array) to translations in a locale.
 *
 * @param mixed  $value  Stored value (ID, array of IDs, URL).
 * @param string $locale Locale.
 * @return mixed
 */
function bogoext_acf_remap_value( $value, $locale ) {
	if ( is_array( $value ) ) {
		BogoExt_Translations::prime( array_map( 'intval', array_filter( $value, 'is_numeric' ) ) );

		foreach ( $value as $i => $item ) {
			$value[ $i ] = bogoext_acf_remap_value( $item, $locale );
		}

		return $value;
	}

	if ( ! is_numeric( $value ) || (int) $value <= 0 ) {
		return $value; // page_link URLs, empty values.
	}

	$type = get_post_type( (int) $value );

	if ( ! $type || ! bogo_is_localizable_post_type( $type ) || bogo_get_post_locale( (int) $value ) === $locale ) {
		return $value;
	}

	$id = bogoext_get_translation_id( (int) $value, $locale, true );

	if ( ! $id ) {
		return $value;
	}

	return is_int( $value ) ? $id : (string) $id;
}
