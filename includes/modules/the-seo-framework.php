<?php
/**
 * Module: The SEO Framework – translated static front page.
 *
 * On a translated static front page TSF uses the global "Homepage" title and
 * description (written in the default language). This uses the SEO title /
 * description set on the translated page instead.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'THE_SEO_FRAMEWORK_VERSION' ) && ! function_exists( 'tsf' ) && ! function_exists( 'the_seo_framework' ) ) {
	return;
}

/**
 * The translated front page currently being displayed, or 0.
 *
 * @return int
 */
function bogoext_tsf_translated_front_page() {
	if ( bogoext_is_default_locale() || ! is_front_page() || is_home() ) {
		return 0;
	}

	$post_id = get_queried_object_id();

	return $post_id ? (int) $post_id : 0;
}

add_filter( 'the_seo_framework_title_from_custom_field', 'bogoext_tsf_title', 10, 2 );

function bogoext_tsf_title( $title, $args = null ) {
	$post_id = bogoext_tsf_translated_front_page();

	if ( $post_id ) {
		$custom = (string) get_post_meta( $post_id, '_genesis_title', true );

		if ( '' !== $custom ) {
			return $custom;
		}
	}

	return $title;
}

add_filter( 'the_seo_framework_meta_render_data', 'bogoext_tsf_description', 10, 1 );

function bogoext_tsf_description( $tags ) {
	$post_id = bogoext_tsf_translated_front_page();

	if ( ! $post_id || ! is_array( $tags ) ) {
		return $tags;
	}

	$custom = (string) get_post_meta( $post_id, '_genesis_description', true );

	if ( '' !== $custom && isset( $tags['description']['attributes'] ) ) {
		$tags['description']['attributes']['content'] = $custom;
	}

	return $tags;
}
