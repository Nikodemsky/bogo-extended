<?php
/**
 * Module: correct hreflang output.
 *
 * Bogo core prints <link rel="alternate" hreflang> for every translation,
 * including drafts, pending and private posts (leaking their URLs to search
 * engines) and prints a full set of alternates on 404 and search pages.
 * This module keeps only published translations, adds x-default and stays
 * silent where alternates make no sense.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'bogo_rel_alternate_hreflang', 'bogoext_filter_hreflang', 20, 1 );

/**
 * @param array $languages List of [ hreflang, href ].
 * @return array
 */
function bogoext_filter_hreflang( $languages ) {
	if ( is_404() || is_search() || is_preview() ) {
		return array();
	}

	$default_locale = bogoext_default_locale();

	$post_id = ( is_singular() || is_home() ) ? get_queried_object_id() : 0;

	if ( $post_id ) {
		$current = get_post( $post_id );

		if ( ! $current || ! bogo_is_localizable_post_type( $current->post_type ) ) {
			return (array) $languages;
		}

		$group = BogoExt_Translations::get_group( $post_id );
		$out   = array();

		foreach ( $group as $locale => $member ) {
			if ( 'publish' !== $member['status'] || ! bogo_is_available_locale( $locale ) ) {
				continue;
			}

			$href = get_permalink( $member['id'] );

			if ( ! $href ) {
				continue;
			}

			$out[] = array(
				'hreflang' => bogo_language_tag( $locale ),
				'href'     => $href,
			);

			if ( $locale === $default_locale ) {
				$x_default = $href;
			}
		}

		// A single language version needs no alternates at all.
		if ( count( $out ) < 2 ) {
			return array();
		}

		if ( ! empty( $x_default ) ) {
			$out[] = array(
				'hreflang' => 'x-default',
				'href'     => $x_default,
			);
		}

		return apply_filters( 'bogoext_hreflang', $out );
	}

	// Archives / front page with latest posts: keep Bogo's list, add x-default.
	$languages = (array) $languages;
	$tag       = bogo_language_tag( $default_locale );

	foreach ( $languages as $language ) {
		if ( isset( $language['hreflang'], $language['href'] ) && $language['hreflang'] === $tag ) {
			$languages[] = array(
				'hreflang' => 'x-default',
				'href'     => $language['href'],
			);
			break;
		}
	}

	return apply_filters( 'bogoext_hreflang', $languages );
}

/*
 * Language switcher links: Bogo core already hides unpublished translations,
 * but on search and 404 pages it links to "the same URL in another language",
 * which usually doesn't exist. Point those to the language home instead.
 */
add_filter( 'bogo_language_switcher_links', 'bogoext_switcher_links_fallback', 20, 1 );

function bogoext_switcher_links_fallback( $links ) {
	if ( ! is_404() && ! is_search() ) {
		return $links;
	}

	foreach ( (array) $links as $i => $link ) {
		if ( ! empty( $link['locale'] ) ) {
			$links[ $i ]['href'] = bogo_url( trailingslashit( get_option( 'home' ) ), $link['locale'] );
		}
	}

	return $links;
}
