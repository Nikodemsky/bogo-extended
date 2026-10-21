<?php
/**
 * Module: Contact Form 7 translations.
 *
 * Each language gets its own, complete Contact Form 7 form (fields, labels,
 * validation messages, mail templates, additional settings). Forms are
 * linked into a translation group; pages keep using ONE shortcode/block –
 * the form matching the current language is rendered automatically.
 *
 * How it works
 *  - A form's language is CF7's own "locale" (post meta `_locale`), so CF7
 *    also switches its built-in messages and the submission locale.
 *  - Translations store the ID of the source form in `_bogoext_cf7_source`.
 *  - `shortcode_atts_wpcf7` swaps the form ID before CF7 looks it up – this
 *    covers the shortcode, the CF7 block (it renders the same shortcode),
 *    page builders and do_shortcode() in templates.
 *  - The edit screen gets a "Languages" box: set the form language, link it
 *    to a source form, open or create translations (a copy is created with
 *    CF7's own copy() API and opened for editing).
 *  - The forms list gets a "Languages" column.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCF7_ContactForm' ) || ! function_exists( 'wpcf7_contact_form' ) ) {
	return;
}

const BOGOEXT_CF7_SOURCE = '_bogoext_cf7_source';

/* ---------------------------------------------------------------------
 * Group helpers
 * ------------------------------------------------------------------- */

/**
 * Language of a form (forms without a valid Bogo locale count as default).
 *
 * @param int $form_id Form ID.
 * @return string
 */
function bogoext_cf7_form_locale( $form_id ) {
	$locale = (string) get_post_meta( $form_id, '_locale', true );

	return bogo_is_available_locale( $locale ) ? $locale : bogoext_default_locale();
}

/**
 * Root (source) form of a group.
 *
 * @param int $form_id Form ID.
 * @return int
 */
function bogoext_cf7_root_id( $form_id ) {
	$source = (int) get_post_meta( $form_id, BOGOEXT_CF7_SOURCE, true );

	if ( $source && $source !== (int) $form_id && WPCF7_ContactForm::post_type === get_post_type( $source ) ) {
		return $source;
	}

	return (int) $form_id;
}

/**
 * All forms of a group: locale => form ID (the root wins on conflicts).
 *
 * @param int $form_id Any form of the group.
 * @return array
 */
function bogoext_cf7_group( $form_id ) {
	static $cache = array();

	$root = bogoext_cf7_root_id( $form_id );

	if ( isset( $cache[ $root ] ) ) {
		return $cache[ $root ];
	}

	$children = get_posts( array(
		'post_type'        => WPCF7_ContactForm::post_type,
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'meta_key'         => BOGOEXT_CF7_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'       => (string) $root,     // phpcs:ignore WordPress.DB.SlowDBQuery
		'suppress_filters' => true,
		'no_found_rows'    => true,
	) );

	$group = array( bogoext_cf7_form_locale( $root ) => $root );

	foreach ( $children as $child ) {
		$locale = bogoext_cf7_form_locale( $child );

		if ( ! isset( $group[ $locale ] ) ) {
			$group[ $locale ] = (int) $child;
		}
	}

	return $cache[ $root ] = $group;
}

/**
 * Finds a form the same way CF7's shortcode handler does.
 *
 * @param string $id    Hash or ID.
 * @param string $title Title.
 * @return WPCF7_ContactForm|null
 */
function bogoext_cf7_lookup( $id, $title ) {
	$id    = trim( (string) $id );
	$title = trim( (string) $title );
	$form  = null;

	if ( '' !== $id && function_exists( 'wpcf7_get_contact_form_by_hash' ) ) {
		$form = wpcf7_get_contact_form_by_hash( $id );
	}

	if ( ! $form && '' !== $id && ctype_digit( $id ) ) {
		$post = get_post( (int) $id );

		if ( $post && WPCF7_ContactForm::post_type === $post->post_type ) {
			$form = wpcf7_contact_form( $post );
		}
	}

	if ( ! $form && '' !== $title && function_exists( 'wpcf7_get_contact_form_by_title' ) ) {
		$form = wpcf7_get_contact_form_by_title( $title );
	}

	return $form instanceof WPCF7_ContactForm ? $form : null;
}

/* ---------------------------------------------------------------------
 * Front end: render the form of the current language
 * ------------------------------------------------------------------- */

add_filter( 'shortcode_atts_wpcf7', 'bogoext_cf7_swap_form', 10, 1 );

/**
 * @param array $out Shortcode attributes after defaults.
 * @return array
 */
function bogoext_cf7_swap_form( $out ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $out;
	}

	$locale = (string) apply_filters( 'bogoext_cf7_locale', get_locale() );

	if ( ! bogo_is_available_locale( $locale ) ) {
		return $out;
	}

	$form = bogoext_cf7_lookup( $out['id'] ?? '', $out['title'] ?? '' );

	if ( ! $form || bogoext_cf7_form_locale( $form->id() ) === $locale ) {
		return $out;
	}

	$group  = bogoext_cf7_group( $form->id() );
	$target = $group[ $locale ] ?? 0;

	if ( ! $target || $target === $form->id() ) {
		return $out; // No translation: render the original form.
	}

	$hash = (string) get_post_meta( $target, '_hash', true );

	$out['id']    = '' !== $hash ? $hash : (string) $target;
	$out['title'] = '';

	return $out;
}

/* ---------------------------------------------------------------------
 * Admin: "Languages" box on the form edit screen
 * ------------------------------------------------------------------- */

add_action( 'wpcf7_admin_misc_pub_section', 'bogoext_cf7_editor_box', 20, 1 );

function bogoext_cf7_editor_box( $post_id ) {
	$post_id = (int) $post_id;

	if ( $post_id <= 0 || ! current_user_can( 'wpcf7_edit_contact_form', $post_id ) ) {
		return;
	}

	$languages = bogoext_languages();
	$locale    = bogoext_cf7_form_locale( $post_id );
	$root      = bogoext_cf7_root_id( $post_id );
	$group     = bogoext_cf7_group( $post_id );

	echo '<div class="misc-pub-section bogoext-cf7-box">';
	wp_nonce_field( 'bogoext_cf7_save_' . $post_id, 'bogoext_cf7_nonce' );

	// Language.
	echo '<label for="bogoext-cf7-locale">' . esc_html__( 'Form language', 'bogo-extended' ) . '</label>';
	echo '<select id="bogoext-cf7-locale" name="bogoext_cf7_locale">';

	foreach ( $languages as $code => $name ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $code ), selected( $code, $locale, false ), esc_html( $name ) );
	}

	echo '</select>';

	// Source form (only for forms that are not the root of a group with translations).
	$is_root_with_children = $root === $post_id && count( $group ) > 1;

	if ( ! $is_root_with_children ) {
		$roots = get_posts( array(
			'post_type'        => WPCF7_ContactForm::post_type,
			'post_status'      => 'publish',
			'posts_per_page'   => 200,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post__not_in'     => array( $post_id ),
			'suppress_filters' => true,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => BOGOEXT_CF7_SOURCE,
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		echo '<label for="bogoext-cf7-source">' . esc_html__( 'Translation of', 'bogo-extended' ) . '</label>';
		echo '<select id="bogoext-cf7-source" name="bogoext_cf7_source">';
		printf( '<option value="0">%s</option>', esc_html__( '— none (this is an original form) —', 'bogo-extended' ) );

		foreach ( $roots as $candidate ) {
			printf(
				'<option value="%1$d"%2$s>%3$s [%4$s]</option>',
				(int) $candidate->ID,
				selected( $candidate->ID, $root === $post_id ? 0 : $root, false ),
				esc_html( $candidate->post_title ),
				esc_html( bogoext_language_code( bogoext_cf7_form_locale( $candidate->ID ) ) )
			);
		}

		echo '</select>';
	}

	// Translations.
	echo '<label>' . esc_html__( 'Translations', 'bogo-extended' ) . '</label><div class="bogoext-badges">';

	foreach ( $languages as $code => $name ) {
		$form_id = $group[ $code ] ?? 0;

		if ( $form_id ) {
			echo bogoext_badge( $code, array( // phpcs:ignore WordPress.Security.EscapeOutput
				'status' => $form_id === $post_id ? 'publish current' : 'publish',
				'href'   => $form_id === $post_id ? '' : admin_url( 'admin.php?page=wpcf7&action=edit&post=' . $form_id ),
				'title'  => $name . ' – ' . get_the_title( $form_id ),
			) );
		} else {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=bogoext_cf7_translate&form=' . $post_id . '&locale=' . rawurlencode( $code ) ),
				'bogoext_cf7_translate_' . $post_id . '_' . $code
			);

			echo bogoext_badge( $code, array( // phpcs:ignore WordPress.Security.EscapeOutput
				'status' => 'missing',
				'href'   => $url,
				/* translators: %s: language name */
				'title'  => sprintf( __( 'Create %s translation (copy of this form)', 'bogo-extended' ), $name ),
			) );
		}
	}

	echo '</div>';
	echo '<p class="description">' . esc_html__( 'Use the shortcode of any form in the group – the version in the visitor\'s language is shown automatically.', 'bogo-extended' ) . '</p>';
	echo '</div>';
}

add_action( 'wpcf7_after_save', 'bogoext_cf7_save_box', 10, 1 );

/**
 * Saves the box (runs inside CF7's own save, after its nonce and capability
 * checks; we verify our own nonce as well).
 *
 * @param WPCF7_ContactForm $contact_form Form.
 */
function bogoext_cf7_save_box( $contact_form ) {
	$post_id = (int) $contact_form->id();
	$nonce   = isset( $_POST['bogoext_cf7_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['bogoext_cf7_nonce'] ) ) : '';

	if ( ! $post_id || ! $nonce || ! wp_verify_nonce( $nonce, 'bogoext_cf7_save_' . $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'wpcf7_edit_contact_form', $post_id ) ) {
		return;
	}

	$locale = isset( $_POST['bogoext_cf7_locale'] ) ? sanitize_text_field( wp_unslash( $_POST['bogoext_cf7_locale'] ) ) : '';

	if ( bogo_is_available_locale( $locale ) ) {
		update_post_meta( $post_id, '_locale', $locale );
	}

	if ( isset( $_POST['bogoext_cf7_source'] ) ) {
		$source = absint( $_POST['bogoext_cf7_source'] );

		if ( $source && $source !== $post_id
			&& WPCF7_ContactForm::post_type === get_post_type( $source )
			&& ! get_post_meta( $source, BOGOEXT_CF7_SOURCE, true ) ) {
			update_post_meta( $post_id, BOGOEXT_CF7_SOURCE, $source );
		} else {
			delete_post_meta( $post_id, BOGOEXT_CF7_SOURCE );
		}
	}
}

/* ---------------------------------------------------------------------
 * Admin: create a translation
 * ------------------------------------------------------------------- */

add_action( 'admin_post_bogoext_cf7_translate', 'bogoext_cf7_create_translation' );

function bogoext_cf7_create_translation() {
	$form_id = isset( $_GET['form'] ) ? absint( $_GET['form'] ) : 0;
	$locale  = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : '';

	check_admin_referer( 'bogoext_cf7_translate_' . $form_id . '_' . $locale );

	if ( ! $form_id || ! bogo_is_available_locale( $locale ) ) {
		wp_die( esc_html__( 'Invalid request.', 'bogo-extended' ), 400 );
	}

	if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) || ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'bogo-extended' ), 403 );
	}

	$source = wpcf7_contact_form( $form_id );

	if ( ! $source ) {
		wp_die( esc_html__( 'Form not found.', 'bogo-extended' ), 404 );
	}

	$root  = bogoext_cf7_root_id( $form_id );
	$group = bogoext_cf7_group( $root );

	if ( ! empty( $group[ $locale ] ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=wpcf7&action=edit&post=' . (int) $group[ $locale ] ) );
		exit;
	}

	$copy = $source->copy();
	$copy->set_title( sprintf( '%1$s [%2$s]', $source->title(), bogoext_language_code( $locale ) ) );
	$copy->set_locale( $locale );
	$new_id = (int) $copy->save();

	if ( ! $new_id ) {
		wp_die( esc_html__( 'Could not create the translation.', 'bogo-extended' ), 500 );
	}

	update_post_meta( $new_id, BOGOEXT_CF7_SOURCE, $root );

	wp_safe_redirect( admin_url( 'admin.php?page=wpcf7&action=edit&post=' . $new_id ) );
	exit;
}

/* ---------------------------------------------------------------------
 * Admin: list column
 * ------------------------------------------------------------------- */

/*
 * The screen ID depends on CF7's menu structure and the (translated) menu
 * title – e.g. "toplevel_page_wpcf7" in older versions, "contact_page_wpcf7"
 * or "kontakt_page_wpcf7" in 6.2+ – so it is resolved at runtime.
 */
add_action( 'current_screen', static function ( $screen ) {
	if ( $screen && preg_match( '/(^|_page_)wpcf7$/', (string) $screen->id ) ) {
		add_filter( "manage_{$screen->id}_columns", 'bogoext_cf7_list_columns', 20 );
	}
} );

function bogoext_cf7_list_columns( $columns ) {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['bogoext_languages'] = __( 'Languages', 'bogo-extended' );
		}
	}

	return $new;
}

add_action( 'wpcf7_manage_custom_column', 'bogoext_cf7_list_column', 10, 2 );

function bogoext_cf7_list_column( $column, $form_id ) {
	if ( 'bogoext_languages' !== $column ) {
		return;
	}

	$form_id = (int) $form_id;
	$group   = bogoext_cf7_group( $form_id );
	$html    = '';

	foreach ( bogoext_languages() as $code => $name ) {
		$id = $group[ $code ] ?? 0;

		$html .= bogoext_badge( $code, array(
			'status' => $id ? ( $id === $form_id ? 'publish current' : 'publish' ) : 'missing disabled',
			'href'   => ( $id && $id !== $form_id ) ? admin_url( 'admin.php?page=wpcf7&action=edit&post=' . $id ) : '',
			'title'  => $id ? $name . ' – ' . get_the_title( $id ) : $name,
		) );
	}

	echo '<div class="bogoext-badges">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

add_action( 'admin_enqueue_scripts', static function ( $hook_suffix ) {
	if ( false !== strpos( (string) $hook_suffix, 'wpcf7' ) ) {
		bogoext_enqueue_admin_style();
	}
} );

/* ---------------------------------------------------------------------
 * Keep groups consistent when a source form is deleted
 * ------------------------------------------------------------------- */

add_action( 'before_delete_post', 'bogoext_cf7_before_delete', 10, 1 );

function bogoext_cf7_before_delete( $post_id ) {
	if ( WPCF7_ContactForm::post_type !== get_post_type( $post_id ) ) {
		return;
	}

	$children = get_posts( array(
		'post_type'        => WPCF7_ContactForm::post_type,
		'post_status'      => 'any',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'meta_key'         => BOGOEXT_CF7_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'       => (string) $post_id,  // phpcs:ignore WordPress.DB.SlowDBQuery
		'suppress_filters' => true,
	) );

	if ( ! $children ) {
		return;
	}

	// The oldest translation becomes the new source of the group.
	$new_root = (int) array_shift( $children );
	delete_post_meta( $new_root, BOGOEXT_CF7_SOURCE );

	foreach ( $children as $child ) {
		update_post_meta( (int) $child, BOGOEXT_CF7_SOURCE, $new_root );
	}
}
