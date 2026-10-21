<?php
/**
 * Module: translations column in post lists + "originals only" view.
 *
 * Every row shows one badge per language:
 *   solid      – published translation (click = edit)
 *   outlined   – draft / pending / scheduled / private (click = edit)
 *   dashed     – missing (click = create via Bogo's own REST endpoint)
 *
 * The "originals only" view hides translations from the list but keeps
 * posts that exist only in a secondary language visible, so nothing
 * disappears from the admin.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'load-edit.php', 'bogoext_list_table_load', 10, 0 );

function bogoext_list_table_load() {
	$screen = get_current_screen();

	if ( ! $screen || ! bogo_is_localizable_post_type( $screen->post_type ) ) {
		return;
	}

	$post_type = $screen->post_type;

	add_filter( "manage_{$post_type}_posts_columns", 'bogoext_list_table_columns', 20 );
	add_action( "manage_{$post_type}_posts_custom_column", 'bogoext_list_table_column_content', 10, 2 );
	add_filter( 'the_posts', 'bogoext_list_table_prime', 10, 2 );
	add_action( 'admin_enqueue_scripts', 'bogoext_list_table_assets' );

	if ( bogoext_get_setting( 'originals_only' ) ) {
		add_filter( 'posts_where', 'bogoext_list_table_originals_where', 20, 2 );
	}

	add_filter( "views_edit-{$post_type}", 'bogoext_list_table_views' );
}

/**
 * Adds the "Translations" column right after Bogo's "Locale" column.
 */
function bogoext_list_table_columns( $columns ) {
	$new = array();
	$key = 'bogoext_translations';
	$label = __( 'Translations', 'bogo-extended' );

	foreach ( $columns as $name => $title ) {
		$new[ $name ] = $title;

		if ( 'locale' === $name ) {
			$new[ $key ] = $label;
		}
	}

	if ( ! isset( $new[ $key ] ) ) {
		$new = array_slice( $columns, 0, 2, true ) + array( $key => $label ) + array_slice( $columns, 2, null, true );
	}

	return $new;
}

/**
 * Loads all translation groups for the visible rows in two queries.
 */
function bogoext_list_table_prime( $posts, $query ) {
	if ( $query->is_main_query() && $posts ) {
		BogoExt_Translations::prime( $posts );
	}

	return $posts;
}

function bogoext_list_table_column_content( $column, $post_id ) {
	if ( 'bogoext_translations' !== $column ) {
		return;
	}

	echo bogoext_render_translation_badges( (int) $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
}

/**
 * Renders the badge list for a post (also used by other screens).
 *
 * @param int $post_id Post ID.
 * @return string HTML.
 */
function bogoext_render_translation_badges( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return '';
	}

	$group       = BogoExt_Translations::get_group( $post_id );
	$own_locale  = bogo_get_post_locale( $post_id );
	$type_object = get_post_type_object( $post->post_type );
	$can_create  = $type_object && current_user_can( $type_object->cap->create_posts )
		&& ( current_user_can( 'edit_post', $post_id ) || 'publish' === $post->post_status );

	$languages = bogoext_languages();

	// Default language first, then alphabetical by locale.
	$default = bogoext_default_locale();

	if ( isset( $languages[ $default ] ) ) {
		$languages = array( $default => $languages[ $default ] ) + $languages;
	}

	$html = '';

	foreach ( $languages as $locale => $name ) {
		$member = $group[ $locale ] ?? null;

		if ( $member ) {
			$status = $member['status'];
			$title  = sprintf(
				/* translators: 1: language name, 2: post status */
				__( '%1$s – %2$s', 'bogo-extended' ),
				$name,
				bogoext_status_label( $status )
			);

			$href = current_user_can( 'edit_post', $member['id'] ) ? get_edit_post_link( $member['id'], 'raw' ) : '';

			$html .= bogoext_badge( $locale, array(
				'status' => ( $member['id'] === $post_id ? 'current ' : '' ) . ( 'publish' === $status ? 'publish' : 'draft' ),
				'href'   => $member['id'] === $post_id ? '' : $href,
				'title'  => $title,
			) );
			continue;
		}

		if ( $can_create && current_user_can( 'bogo_access_locale', $locale ) ) {
			$html .= bogoext_badge( $locale, array(
				'status' => 'missing',
				'title'  => sprintf(
					/* translators: %s: language name */
					__( 'Add %s translation', 'bogo-extended' ),
					$name
				),
				'data'   => array(
					'post'   => $post_id,
					'locale' => $locale,
				),
			) );
		} else {
			$html .= bogoext_badge( $locale, array(
				'status' => 'missing disabled',
				'title'  => sprintf(
					/* translators: %s: language name */
					__( 'No %s translation', 'bogo-extended' ),
					$name
				),
			) );
		}
	}

	return '<div class="bogoext-badges">' . $html . '</div>';
}

function bogoext_list_table_assets( $hook_suffix ) {
	if ( 'edit.php' !== $hook_suffix ) {
		return;
	}

	bogoext_enqueue_admin_style();

	wp_enqueue_script(
		'bogoext-list-table',
		BOGOEXT_URL . 'assets/js/list-table.js',
		array( 'wp-api-fetch' ),
		BOGOEXT_VERSION,
		true
	);

	wp_localize_script( 'bogoext-list-table', 'bogoextListTable', array(
		'i18n' => array(
			'creating' => __( 'Creating translation…', 'bogo-extended' ),
			'failed'   => __( 'Could not create the translation.', 'bogo-extended' ),
			'draft'    => __( 'Draft created – click to edit', 'bogo-extended' ),
		),
	) );
}

/**
 * Whether the current list request should show originals only.
 *
 * The list starts in "originals" mode. Choosing anything in Bogo's language
 * dropdown (including "Show all locales") sends `lang` and switches it off.
 *
 * @return bool
 */
function bogoext_list_table_is_originals_view() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
	if ( isset( $_GET['lang'] ) ) {
		return false;
	}

	if ( ! empty( $_GET['s'] ) ) {
		return false; // Searching must find translations too.
	}

	$status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
	// phpcs:enable

	return 'trash' !== $status;
}

function bogoext_list_table_originals_where( $where, $query ) {
	global $wpdb;

	if ( ! $query->is_main_query() || ! bogoext_list_table_is_originals_view() ) {
		return $where;
	}

	$post_type = $query->get( 'post_type' );

	if ( ! is_string( $post_type ) || ! bogo_is_localizable_post_type( $post_type ) ) {
		return $where;
	}

	$default = bogoext_default_locale();
	$orphans = bogoext_list_table_orphan_ids( $post_type );

	$where .= $wpdb->prepare(
		" AND ( NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS bxl WHERE bxl.post_id = {$wpdb->posts}.ID AND bxl.meta_key = '_locale' AND bxl.meta_value <> '' AND bxl.meta_value <> %s )",
		$default
	);

	if ( $orphans ) {
		$where .= " OR {$wpdb->posts}.ID IN (" . implode( ',', array_map( 'absint', $orphans ) ) . ')';
	}

	$where .= ' )';

	return $where;
}

/**
 * IDs of translations whose group has no post in the default language
 * (one per group). They are shown in the "originals" view.
 *
 * @param string $post_type Post type.
 * @return int[]
 */
function bogoext_list_table_orphan_ids( $post_type ) {
	global $wpdb;

	$default = bogoext_default_locale();

	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT MIN(o.post_id)
		FROM {$wpdb->postmeta} AS o
		INNER JOIN {$wpdb->posts} AS p ON p.ID = o.post_id AND p.post_type = %s AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
		INNER JOIN {$wpdb->postmeta} AS l ON l.post_id = o.post_id AND l.meta_key = '_locale' AND l.meta_value <> '' AND l.meta_value <> %s
		WHERE o.meta_key = '_original_post'
		AND o.meta_value NOT IN (
			SELECT o2.meta_value
			FROM {$wpdb->postmeta} AS o2
			INNER JOIN {$wpdb->posts} AS p2 ON p2.ID = o2.post_id AND p2.post_type = %s AND p2.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
			LEFT JOIN {$wpdb->postmeta} AS l2 ON l2.post_id = o2.post_id AND l2.meta_key = '_locale'
			WHERE o2.meta_key = '_original_post'
			AND ( l2.meta_value IS NULL OR l2.meta_value = '' OR l2.meta_value = %s )
		)
		GROUP BY o.meta_value",
		$post_type,
		$default,
		$post_type,
		$default
	) );

	return array_map( 'intval', (array) $ids );
}

/**
 * Number of posts per language (statuses shown in the "All" view).
 *
 * @param string $post_type Post type.
 * @return array locale => count
 */
function bogoext_list_table_language_counts( $post_type ) {
	global $wpdb;

	$statuses = get_post_stati( array( 'show_in_admin_all_list' => true ) );

	if ( ! $statuses ) {
		return array();
	}

	$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT COALESCE( l.meta_value, '' ) AS locale, COUNT(*) AS total
		FROM {$wpdb->posts} AS p
		LEFT JOIN {$wpdb->postmeta} AS l ON l.post_id = p.ID AND l.meta_key = '_locale'
		WHERE p.post_type = %s AND p.post_status IN ( $placeholders )
		GROUP BY COALESCE( l.meta_value, '' )",
		array_merge( array( $post_type ), $statuses )
	) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$default = bogoext_default_locale();
	$counts  = array();

	foreach ( (array) $rows as $row ) {
		$locale            = '' === $row->locale ? $default : $row->locale;
		$counts[ $locale ] = ( $counts[ $locale ] ?? 0 ) + (int) $row->total;
	}

	return $counts;
}

/**
 * Language views above the list:
 * Originals only | All languages | Polski (12) | Deutsch (8) | …
 */
function bogoext_list_table_views( $views ) {
	$screen = get_current_screen();
	$base   = add_query_arg( 'post_type', $screen->post_type, admin_url( 'edit.php' ) );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$lang = isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( $_GET['lang'] ) ) : null;

	$link = static function ( $url, $label, $current, $count = null ) {
		return sprintf(
			'<a href="%1$s"%2$s>%3$s%4$s</a>',
			esc_url( $url ),
			$current ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			null === $count ? '' : sprintf( ' <span class="count">(%s)</span>', number_format_i18n( $count ) )
		);
	};

	if ( bogoext_get_setting( 'originals_only' ) ) {
		$views['bogoext_originals'] = $link( $base, __( 'Originals only', 'bogo-extended' ), bogoext_list_table_is_originals_view() );
		$views['bogoext_all_languages'] = $link( add_query_arg( 'lang', '', $base ), __( 'All languages', 'bogo-extended' ), '' === $lang );
	} else {
		$views['bogoext_all_languages'] = $link( $base, __( 'All languages', 'bogo-extended' ), null === $lang || '' === $lang );
	}

	$counts    = bogoext_list_table_language_counts( $screen->post_type );
	$languages = bogoext_languages();
	$default   = bogoext_default_locale();

	if ( isset( $languages[ $default ] ) ) {
		$languages = array( $default => $languages[ $default ] ) + $languages;
	}

	foreach ( $languages as $locale => $name ) {
		$views[ 'bogoext_lang_' . $locale ] = $link(
			// filter_action stops Bogo from also switching the admin UI language (see bogo_pre_determine_locale()).
			add_query_arg( array( 'lang' => $locale, 'filter_action' => 'bogoext' ), $base ),
			$name,
			$lang === $locale,
			$counts[ $locale ] ?? 0
		);
	}

	return $views;
}
