<?php
/**
 * Module: dropdown language switcher.
 *
 *   [bogo-dropdown]                       – popup on hover/click
 *   [bogo-dropdown style="toggle"]        – expands in place (mobile menus)
 *   [bogo-dropdown style="list"]          – bare <ul> of links, no CSS/JS, no button
 *   [bogo-dropdown compact="1"]           – short codes (EN, DE …)
 *   [bogo-dropdown flags="1"]             – Bogo's flag emoji before names
 *   [bogo-dropdown hide_missing="0"]      – also list languages without a translation (as plain text)
 *
 * All attributes are whitelisted/escaped; the toggle is a real <button>
 * with aria-expanded, closes on Escape and on outside click.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'bogo-dropdown', 'bogoext_dropdown_shortcode' );

add_action( 'wp_enqueue_scripts', static function () {
	wp_register_style( 'bogoext-switcher', BOGOEXT_URL . 'assets/css/switcher.css', array(), BOGOEXT_VERSION );
	wp_register_script( 'bogoext-switcher', BOGOEXT_URL . 'assets/js/switcher.js', array(), BOGOEXT_VERSION, array(
		'in_footer' => true,
		'strategy'  => 'defer',
	) );
} );

/**
 * Switcher data: only languages that have a target URL.
 *
 * @param bool $hide_missing Skip languages without a translation.
 * @return array
 */
function bogoext_switcher_links( $hide_missing = true ) {
	$links   = array();
	$current = get_locale();

	foreach ( (array) bogo_language_switcher_links() as $link ) {
		if ( empty( $link['locale'] ) ) {
			continue;
		}

		if ( $hide_missing && empty( $link['href'] ) && $link['locale'] !== $current ) {
			continue;
		}

		$name = ! empty( $link['native_name'] ) ? $link['native_name'] : ( $link['title'] ?? $link['locale'] );

		$links[] = array(
			'locale'     => $link['locale'],
			'lang'       => $link['lang'] ?? bogo_language_tag( $link['locale'] ),
			'href'       => $link['href'] ?? '',
			'name'       => $name,
			'short'      => bogoext_language_code( $link['locale'] ),
			'title'      => $link['title'] ?? $name,
			'is_current' => $link['locale'] === $current,
		);
	}

	return apply_filters( 'bogoext_switcher_links', $links );
}

/**
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function bogoext_dropdown_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'style'        => 'popup',
		'compact'      => '0',
		'flags'        => '0',
		'hide_missing' => '1',
		'class'        => '',
	), $atts, 'bogo-dropdown' );

	$style        = in_array( $atts['style'], array( 'popup', 'toggle', 'list' ), true ) ? $atts['style'] : 'popup';
	$compact      = in_array( (string) $atts['compact'], array( '1', 'true', 'yes' ), true );
	$flags        = in_array( (string) $atts['flags'], array( '1', 'true', 'yes' ), true );
	$hide_missing = ! in_array( (string) $atts['hide_missing'], array( '0', 'false', 'no' ), true );

	$links = bogoext_switcher_links( $hide_missing );

	if ( count( $links ) < 2 ) {
		return '';
	}

	if ( 'list' === $style ) {
		return bogoext_plain_list_html( $links, $atts, $compact, $flags );
	}

	wp_enqueue_style( 'bogoext-switcher' );
	wp_enqueue_script( 'bogoext-switcher' );

	static $instance = 0;
	$instance++;
	$list_id = 'bogoext-dropdown-' . $instance;

	$current = null;

	foreach ( $links as $link ) {
		if ( $link['is_current'] ) {
			$current = $link;
			break;
		}
	}

	if ( ! $current ) {
		$current = $links[0];
	}

	$columns = count( $links ) >= 9 ? 3 : ( count( $links ) >= 5 ? 2 : 1 );

	$classes = array( 'bogoext-dropdown', 'bogo-dropdown', 'is-style-' . $style );

	foreach ( preg_split( '/\s+/', (string) $atts['class'] ) as $extra ) {
		$extra = sanitize_html_class( $extra );

		if ( $extra ) {
			$classes[] = $extra;
		}
	}

	$flag = static function ( $locale ) use ( $flags ) {
		if ( ! $flags ) {
			return '';
		}

		$country = bogo_get_country_code( $locale );

		return sprintf(
			'<span class="bogoflags bogoflags-%s" aria-hidden="true"></span>',
			esc_attr( $country ? strtolower( $country ) : 'zz' )
		);
	};

	$label = static function ( $link ) use ( $compact ) {
		return esc_html( $compact ? $link['short'] : $link['name'] );
	};

	$html  = sprintf( '<div class="%s">', esc_attr( implode( ' ', $classes ) ) );
	$html .= sprintf(
		'<button type="button" class="bogoext-dropdown__button" aria-expanded="false" aria-controls="%1$s" aria-label="%2$s">%3$s<span class="bogoext-dropdown__current" lang="%4$s">%5$s</span></button>',
		esc_attr( $list_id ),
		/* translators: %s: current language name */
		esc_attr( sprintf( __( 'Language: %s', 'bogo-extended' ), $current['name'] ) ),
		$flag( $current['locale'] ),
		esc_attr( $current['lang'] ),
		$label( $current )
	);
	$html .= sprintf( '<ul id="%1$s" class="bogoext-dropdown__links has-columns-%2$d">', esc_attr( $list_id ), (int) $columns );

	foreach ( $links as $link ) {
		$html .= sprintf( '<li class="%s">', $link['is_current'] ? 'is-current' : '' );

		$inner = $flag( $link['locale'] ) . '<span>' . $label( $link ) . '</span>';

		if ( $link['href'] ) {
			$html .= sprintf(
				'<a href="%1$s" hreflang="%2$s" lang="%2$s" title="%3$s"%4$s>%5$s</a>',
				esc_url( $link['href'] ),
				esc_attr( $link['lang'] ),
				esc_attr( $link['title'] ),
				$link['is_current'] ? ' aria-current="page"' : '',
				$inner
			);
		} else {
			$html .= sprintf( '<span class="is-unavailable" lang="%1$s">%2$s</span>', esc_attr( $link['lang'] ), $inner );
		}

		$html .= '</li>';
	}

	$html .= '</ul></div>';

	return apply_filters( 'bogoext_dropdown_html', $html, $links, $atts );
}

/**
 * Plain list variant: a bare <ul> with no plugin CSS or JS enqueued,
 * no wrapper, no button and no inline styles – the theme decides the look.
 *
 * @param array $links   Switcher data from bogoext_switcher_links().
 * @param array $atts    Normalised shortcode attributes.
 * @param bool  $compact Show short codes instead of names.
 * @param bool  $flags   Prepend Bogo's flag markup.
 * @return string
 */
function bogoext_plain_list_html( $links, $atts, $compact, $flags ) {
	$classes = array( 'bogoext-list' );

	foreach ( preg_split( '/\s+/', (string) $atts['class'] ) as $extra ) {
		$extra = sanitize_html_class( $extra );

		if ( $extra ) {
			$classes[] = $extra;
		}
	}

	$html = sprintf( '<ul class="%s">', esc_attr( implode( ' ', $classes ) ) );

	foreach ( $links as $link ) {
		$label = esc_html( $compact ? $link['short'] : $link['name'] );

		if ( $flags ) {
			$country = bogo_get_country_code( $link['locale'] );
			$label   = sprintf(
				'<span class="bogoflags bogoflags-%s" aria-hidden="true"></span> ',
				esc_attr( $country ? strtolower( $country ) : 'zz' )
			) . $label;
		}

		$html .= $link['is_current'] ? '<li class="is-current">' : '<li>';

		if ( $link['href'] ) {
			$html .= sprintf(
				'<a href="%1$s" hreflang="%2$s" lang="%2$s"%3$s>%4$s</a>',
				esc_url( $link['href'] ),
				esc_attr( $link['lang'] ),
				$link['is_current'] ? ' aria-current="page"' : '',
				$label
			);
		} else {
			$html .= sprintf( '<span lang="%1$s">%2$s</span>', esc_attr( $link['lang'] ), $label );
		}

		$html .= '</li>';
	}

	$html .= '</ul>';

	return apply_filters( 'bogoext_dropdown_html', $html, $links, $atts );
}
