<?php
/**
 * Module: safer "Add translation" (post duplication).
 *
 * Bogo core passes the original post's raw fields to wp_insert_post() and
 * add_post_meta(), both of which run wp_unslash(). Every backslash is lost:
 * block attributes such as "\u003c" become "u003c", JSON stored in meta
 * (Elementor, ACF blocks, page builders) is corrupted.
 *
 * Here everything that goes through an unslashing API is slashed once, and
 * editor/housekeeping meta is not copied to the translation.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'bogo_duplicate_post', 'bogoext_duplicate_post_prepare', 999, 3 );

/**
 * @param array   $postarr       Data passed to wp_insert_post().
 * @param WP_Post $original_post Source post.
 * @param string  $locale        Target locale.
 * @return array
 */
function bogoext_duplicate_post_prepare( $postarr, $original_post, $locale ) {
	foreach ( array( 'post_content', 'post_title', 'post_excerpt', 'post_content_filtered' ) as $field ) {
		if ( isset( $postarr[ $field ] ) && is_string( $postarr[ $field ] ) ) {
			$postarr[ $field ] = wp_slash( $postarr[ $field ] );
		}
	}

	if ( ! empty( $postarr['meta_input'] ) && is_array( $postarr['meta_input'] ) ) {
		$excluded = apply_filters( 'bogoext_duplicate_excluded_meta', array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_old_date',
			'_wp_trash_meta_status',
			'_wp_trash_meta_time',
			'_wp_desired_post_slug',
			'_encloseme',
			'_pingme',
		), $original_post, $locale );

		foreach ( $postarr['meta_input'] as $key => $values ) {
			if ( in_array( $key, $excluded, true ) ) {
				unset( $postarr['meta_input'][ $key ] );
				continue;
			}

			// add_post_meta() unslashes strings and arrays (recursively).
			$postarr['meta_input'][ $key ] = array_map(
				static function ( $value ) {
					return ( is_string( $value ) || is_array( $value ) ) ? wp_slash( $value ) : $value;
				},
				(array) $values
			);
		}
	}

	return $postarr;
}
