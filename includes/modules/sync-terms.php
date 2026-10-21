<?php
/**
 * Module (optional, off by default): keep taxonomy terms in sync.
 *
 * When the terms of a post in the DEFAULT language change (categories, tags,
 * product categories …), the same terms are assigned to all its
 * translations. Terms are shared between languages in Bogo – only their
 * names are translated – so the translations should normally match.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'set_object_terms', 'bogoext_sync_terms', 20, 4 );

function bogoext_sync_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
	static $running = false;

	if ( $running ) {
		return;
	}

	$post = get_post( $object_id );

	if ( ! $post || ! bogo_is_localizable_post_type( $post->post_type ) ) {
		return;
	}

	if ( ! bogo_is_default_locale( bogo_get_post_locale( $post->ID ) ) ) {
		return;
	}

	$taxonomies = (array) apply_filters(
		'bogoext_sync_taxonomies',
		get_object_taxonomies( $post->post_type ),
		$post
	);

	if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
		return;
	}

	$term_ids = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );

	if ( is_wp_error( $term_ids ) ) {
		return;
	}

	BogoExt_Translations::flush( $post->ID );

	$running = true;

	foreach ( BogoExt_Translations::get_group( $post->ID ) as $locale => $member ) {
		if ( $member['id'] !== $post->ID ) {
			wp_set_object_terms( $member['id'], array_map( 'intval', $term_ids ), $taxonomy, false );
		}
	}

	$running = false;
}
