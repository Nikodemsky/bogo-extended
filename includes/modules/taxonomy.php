<?php
/**
 * Module: translated term names and descriptions.
 *
 * Bogo core can translate term NAMES on its "Terms Translation" screen.
 * This module adds per-language name + description fields directly on the
 * term edit screen of the selected taxonomies. When a field is empty, Bogo's
 * own translation (or the original) is used, so both mechanisms coexist.
 *
 * Input is sanitized like WordPress' own term fields (limited HTML in
 * descriptions for users without unfiltered_html).
 */

defined( 'ABSPATH' ) || exit;

const BOGOEXT_TERM_META = '_bogoext_i18n';

/** Prefix of the per-language slug meta: _bogoext_slug_de_DE (indexed lookups). */
const BOGOEXT_TERM_SLUG_META = '_bogoext_slug_';

/**
 * Taxonomies with translation fields.
 *
 * @return string[]
 */
function bogoext_localizable_taxonomies() {
	static $cache = null;

	if ( null === $cache || ! did_action( 'init' ) ) {
		$list = (array) bogoext_get_setting( 'taxonomy_list' );
		$list = array_values( array_filter( $list, 'taxonomy_exists' ) );
		$list = (array) apply_filters( 'bogoext_localizable_taxonomies', $list );

		if ( ! did_action( 'init' ) ) {
			return $list;
		}

		$cache = $list;
	}

	return $cache;
}

/**
 * Stored translations of a term.
 *
 * @param int $term_id Term ID.
 * @return array locale => [ name, description ]
 */
function bogoext_term_i18n( $term_id ) {
	$data = get_term_meta( $term_id, BOGOEXT_TERM_META, true );

	return is_array( $data ) ? $data : array();
}

/**
 * A translated field of a term ('' when not translated).
 *
 * @param int    $term_id Term ID.
 * @param string $locale  Locale.
 * @param string $field   name|description.
 * @return string
 */
function bogoext_term_field( $term_id, $locale, $field ) {
	$data = bogoext_term_i18n( $term_id );

	return isset( $data[ $locale ][ $field ] ) ? (string) $data[ $locale ][ $field ] : '';
}

/**
 * The term exactly as stored in the database (no translation filters).
 *
 * @param int $term_id Term ID.
 * @return WP_Term|null
 */
function bogoext_get_term_untranslated( $term_id ) {
	$filters = array( 'bogo_get_term_filter', 'bogoext_filter_get_term' );
	$removed = array();

	foreach ( $filters as $callback ) {
		$priority = has_filter( 'get_term', $callback );

		if ( false !== $priority ) {
			remove_filter( 'get_term', $callback, $priority );
			$removed[ $callback ] = $priority;
		}
	}

	$term = get_term( $term_id );

	foreach ( $removed as $callback => $priority ) {
		add_filter( 'get_term', $callback, $priority, 2 );
	}

	return ( $term instanceof WP_Term ) ? $term : null;
}

/* ---------------------------------------------------------------------
 * Front end
 * ------------------------------------------------------------------- */

add_filter( 'get_term', 'bogoext_filter_get_term', 20, 2 );

/**
 * Runs after Bogo's own term filter (priority 10).
 *
 * @param WP_Term|mixed $term     Term.
 * @param string        $taxonomy Taxonomy.
 * @return WP_Term|mixed
 */
function bogoext_filter_get_term( $term, $taxonomy ) {
	if ( ! ( $term instanceof WP_Term ) || ! bogoext_should_localize_output() ) {
		return $term;
	}

	if ( ! in_array( $taxonomy, bogoext_localizable_taxonomies(), true ) ) {
		return $term;
	}

	$locale = get_locale();
	$name   = bogoext_term_field( $term->term_id, $locale, 'name' );
	$desc   = bogoext_term_field( $term->term_id, $locale, 'description' );

	if ( '' !== $name ) {
		$term->name = $name;
	}

	if ( '' !== $desc ) {
		$term->description = $desc;
	}

	return $term;
}

/* ---------------------------------------------------------------------
 * Admin: fields + save
 * ------------------------------------------------------------------- */

add_action( 'admin_init', 'bogoext_term_register_hooks' );

function bogoext_term_register_hooks() {
	foreach ( bogoext_localizable_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_edit_form_fields", 'bogoext_term_fields', 20, 2 );
		add_action( "edited_{$taxonomy}", 'bogoext_term_save', 10, 1 );
	}
}

add_action( 'admin_enqueue_scripts', static function ( $hook_suffix ) {
	if ( 'term.php' === $hook_suffix ) {
		bogoext_enqueue_admin_style();
	}
} );

function bogoext_term_fields( $term, $taxonomy ) {
	$languages = bogoext_secondary_languages();

	if ( ! $languages ) {
		return;
	}

	$data = bogoext_term_i18n( $term->term_id );

	echo '<tr class="form-field bogoext-term-i18n"><th scope="row">' . esc_html__( 'Translations', 'bogo-extended' ) . '</th><td>';
	wp_nonce_field( 'bogoext_term_' . $term->term_id, 'bogoext_term_nonce' );

	foreach ( $languages as $locale => $name ) {
		$fields = $data[ $locale ] ?? array();
		$base   = 'bogoext_term_i18n[' . $locale . ']';

		echo '<div class="bogoext-i18n__row">';
		echo bogoext_badge( $locale, array( 'status' => 'draft', 'title' => $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="bogoext-i18n__fields">';

		printf(
			'<input type="text" name="%1$s[name]" value="%2$s" placeholder="%3$s" aria-label="%4$s" />',
			esc_attr( $base ),
			esc_attr( $fields['name'] ?? '' ),
			esc_attr( $term->name ),
			/* translators: %s: language name */
			esc_attr( sprintf( __( 'Name (%s)', 'bogo-extended' ), $name ) )
		);

		printf(
			'<label class="bogoext-i18n__slug"><span>%1$s</span><input type="text" name="%2$s[slug]" value="%3$s" placeholder="%4$s" aria-label="%5$s" /></label>',
			esc_html__( 'Slug', 'bogo-extended' ),
			esc_attr( $base ),
			esc_attr( urldecode( bogoext_term_slug( $term->term_id, $locale ) ) ),
			esc_attr( urldecode( $term->slug ) ),
			/* translators: %s: language name */
			esc_attr( sprintf( __( 'Slug (%s)', 'bogo-extended' ), $name ) )
		);

		printf(
			'<textarea name="%1$s[description]" rows="3" placeholder="%2$s" aria-label="%2$s">%3$s</textarea>',
			esc_attr( $base ),
			/* translators: %s: language name */
			esc_attr( sprintf( __( 'Description (%s)', 'bogo-extended' ), $name ) ),
			esc_textarea( $fields['description'] ?? '' )
		);

		echo '</div></div>';
	}

	echo '<p class="description">' . esc_html__( 'Leave empty to use the original (or Bogo\'s "Terms Translation").', 'bogo-extended' ) . '</p>';
	echo '</td></tr>';
}

function bogoext_term_save( $term_id ) {
	$nonce = isset( $_POST['bogoext_term_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['bogoext_term_nonce'] ) ) : '';

	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bogoext_term_' . $term_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}

	$raw   = isset( $_POST['bogoext_term_i18n'] ) && is_array( $_POST['bogoext_term_i18n'] )
		? wp_unslash( $_POST['bogoext_term_i18n'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field.
		: array();
	$clean = array();

	foreach ( bogoext_secondary_languages() as $locale => $name ) {
		$fields = isset( $raw[ $locale ] ) && is_array( $raw[ $locale ] ) ? $raw[ $locale ] : array();
		$entry  = array_filter( array(
			'name'        => bogoext_sanitize_line( $fields['name'] ?? '' ),
			'description' => bogoext_sanitize_html( $fields['description'] ?? '' ),
		), 'strlen' );

		if ( $entry ) {
			$clean[ $locale ] = $entry;
		}
	}

	if ( $clean ) {
		update_term_meta( $term_id, BOGOEXT_TERM_META, $clean );
	} else {
		delete_term_meta( $term_id, BOGOEXT_TERM_META );
	}

	// Slugs.
	$term   = get_term( $term_id );
	$errors = array();

	foreach ( bogoext_secondary_languages() as $locale => $name ) {
		$fields = isset( $raw[ $locale ] ) && is_array( $raw[ $locale ] ) ? $raw[ $locale ] : array();
		$slug   = isset( $fields['slug'] ) && is_scalar( $fields['slug'] ) ? sanitize_title( (string) $fields['slug'] ) : '';

		if ( '' !== $slug && $term instanceof WP_Term && $slug !== $term->slug ) {
			$conflict = bogoext_term_slug_conflict( $term, $slug, $locale );

			if ( $conflict ) {
				$errors[] = sprintf(
					/* translators: 1: slug, 2: language, 3: other term name */
					__( 'Slug "%1$s" (%2$s) was not saved – it is already used by "%3$s" in this language.', 'bogo-extended' ),
					urldecode( $slug ),
					$name,
					$conflict->name
				);
				continue;
			}

			update_term_meta( $term_id, BOGOEXT_TERM_SLUG_META . $locale, $slug );
		} else {
			delete_term_meta( $term_id, BOGOEXT_TERM_SLUG_META . $locale );
		}
	}

	if ( $errors ) {
		set_transient( 'bogoext_term_errors_' . get_current_user_id(), $errors, 120 );
	}

}

/**
 * Translated slug of a term ('' when not translated).
 *
 * @param int    $term_id Term ID.
 * @param string $locale  Locale.
 * @return string
 */
function bogoext_term_slug( $term_id, $locale ) {
	if ( bogo_is_default_locale( $locale ) ) {
		return '';
	}

	return (string) get_term_meta( $term_id, BOGOEXT_TERM_SLUG_META . $locale, true );
}

/**
 * Finds the term that has a translated slug in a locale.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $locale   Locale.
 * @param string $slug     Slug.
 * @return WP_Term|null
 */
function bogoext_term_by_translated_slug( $taxonomy, $locale, $slug ) {
	static $cache = array();

	$key = $taxonomy . '|' . $locale . '|' . $slug;

	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}

	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'number'     => 1,
		'meta_key'   => BOGOEXT_TERM_SLUG_META . $locale, // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value' => $slug,                             // phpcs:ignore WordPress.DB.SlowDBQuery
		'update_term_meta_cache' => false,
	) );

	return $cache[ $key ] = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0] : null;
}

/**
 * Another term that would answer the same address in this language.
 *
 * In a language every term is reachable by its translated slug, or by its
 * original slug when it has none – those must be unique.
 *
 * @return WP_Term|null
 */
function bogoext_term_slug_conflict( WP_Term $term, $slug, $locale ) {
	$other = bogoext_term_by_translated_slug( $term->taxonomy, $locale, $slug );

	if ( $other && $other->term_id !== $term->term_id ) {
		return $other;
	}

	$other = get_term_by( 'slug', $slug, $term->taxonomy );

	if ( $other && $other->term_id !== $term->term_id && '' === bogoext_term_slug( $other->term_id, $locale ) ) {
		return $other;
	}

	return null;
}

add_action( 'admin_notices', static function () {
	$key    = 'bogoext_term_errors_' . get_current_user_id();
	$errors = get_transient( $key );

	if ( ! $errors ) {
		return;
	}

	delete_transient( $key );

	foreach ( (array) $errors as $error ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
	}
} );

if ( get_option( 'permalink_structure' ) ) {
	require_once __DIR__ . '/term-slugs.php';
}
