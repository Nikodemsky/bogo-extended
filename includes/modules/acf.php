<?php
/**
 * Module: Advanced Custom Fields integration.
 *
 * - post_object / relationship / page_link fields return the translation
 *   of the selected post on secondary-language pages,
 * - link fields get a localized URL (and the translated title when the link
 *   title was the post title),
 * - post pickers in the admin list only default-language posts.
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
 * Post pickers list only default-language posts (translations are swapped
 * in automatically when rendering).
 */
function bogoext_acf_picker_originals( $args, $field, $post_id ) {
	if ( ! apply_filters( 'bogoext_acf_hide_translations', true, $field ) ) {
		return $args;
	}

	$args['meta_query'] = bogoext_merge_meta_query(
		$args['meta_query'] ?? array(),
		bogoext_default_locale_meta_clause()
	);

	return $args;
}
