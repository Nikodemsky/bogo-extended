<?php
/**
 * Module: automatic link localization.
 *
 * On pages in a secondary language, links that point to posts/pages in the
 * DEFAULT language are rewritten to their published translation (query
 * string and #anchor are kept). Links that deliberately point to another
 * language, external links, files, anchors, mailto: etc. are left alone.
 * Opt out per link with class="no-localize" or data-bogo-keep.
 *
 * Links are found with WordPress' HTML tag processor (not regular
 * expressions) and resolved live, with a request-level cache.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'the_content', 'bogoext_localize_content_links', 20 );
add_filter( 'widget_text_content', 'bogoext_localize_content_links', 20 );
add_filter( 'widget_block_content', 'bogoext_localize_content_links', 20 );

/**
 * @param string $content HTML.
 * @return string
 */
function bogoext_localize_content_links( $content ) {
	if ( ! is_string( $content ) || '' === $content || false === stripos( $content, '<a' ) ) {
		return $content;
	}

	if ( ! bogoext_should_localize_output() || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $content;
	}

	$locale    = get_locale();
	$processor = new WP_HTML_Tag_Processor( $content );
	$changed   = false;

	while ( $processor->next_tag( 'a' ) ) {
		$href = $processor->get_attribute( 'href' );

		if ( ! is_string( $href ) || '' === $href ) {
			continue;
		}

		if ( null !== $processor->get_attribute( 'hreflang' )
			|| null !== $processor->get_attribute( 'data-bogo-keep' )
			|| $processor->has_class( 'no-localize' ) ) {
			continue;
		}

		$localized = BogoExt_URL_Resolver::localize( $href, $locale );

		if ( $localized && $localized !== $href ) {
			$processor->set_attribute( 'href', $localized );
			$changed = true;
		}
	}

	return $changed ? $processor->get_updated_html() : $content;
}

/*
 * Link picker (classic editor link modal, ACF link field):
 * show only default-language content – the link is localized at render time.
 */
add_filter( 'wp_link_query_args', 'bogoext_link_query_originals' );

function bogoext_link_query_originals( $query ) {
	if ( ! apply_filters( 'bogoext_hide_translations_in_link_picker', true ) ) {
		return $query;
	}

	$query['meta_query'] = bogoext_merge_meta_query(
		$query['meta_query'] ?? array(),
		bogoext_default_locale_meta_clause()
	);

	return $query;
}
