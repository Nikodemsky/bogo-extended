<?php
/**
 * Module: translated synced patterns (reusable blocks, post type wp_block).
 *
 * Patterns become translatable like posts (Bogo's "Add translation",
 * the translations column, the editor sidebar). Content keeps referencing
 * the default-language pattern; on secondary-language pages the block
 * renders the published translation instead.
 *
 * The `ref` attribute is swapped before WordPress renders the block, so its
 * own rendering and recursion protection are used.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'bogo_localizable_post_types', 'bogoext_patterns_localizable', 10, 1 );

function bogoext_patterns_localizable( $post_types ) {
	$post_types   = (array) $post_types;
	$post_types[] = 'wp_block';

	return array_values( array_unique( $post_types ) );
}

add_filter( 'render_block_data', 'bogoext_patterns_swap_ref', 10, 1 );

/**
 * @param array $parsed_block Block.
 * @return array
 */
function bogoext_patterns_swap_ref( $parsed_block ) {
	if ( 'core/block' !== ( $parsed_block['blockName'] ?? '' ) || empty( $parsed_block['attrs']['ref'] ) ) {
		return $parsed_block;
	}

	if ( ! bogoext_should_localize_output() ) {
		return $parsed_block;
	}

	$ref = (int) $parsed_block['attrs']['ref'];

	if ( ! bogo_is_default_locale( bogo_get_post_locale( $ref ) ) ) {
		return $parsed_block; // Already points to a specific language.
	}

	$translation_id = bogoext_get_translation_id( $ref, get_locale(), true );

	if ( $translation_id ) {
		$parsed_block['attrs']['ref'] = $translation_id;
	}

	return $parsed_block;
}
