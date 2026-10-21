<?php
/**
 * Module: "Translations" sidebar in the block editor.
 *
 * Uses the official PluginSidebar API: a pinnable icon in the editor header
 * that opens a panel with every language, its status and edit / view /
 * create actions.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'bogoext_editor_register_route' );

function bogoext_editor_register_route() {
	register_rest_route( 'bogoext/v1', '/posts/(?P<id>\d+)/translations', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'bogoext_editor_rest_translations',
		'permission_callback' => static function ( WP_REST_Request $request ) {
			return current_user_can( 'edit_post', (int) $request['id'] );
		},
		'args'                => array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		),
	) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function bogoext_editor_rest_translations( WP_REST_Request $request ) {
	$post = get_post( (int) $request['id'] );

	if ( ! $post || ! bogo_is_localizable_post_type( $post->post_type ) ) {
		return new WP_Error( 'bogoext_not_localizable', __( 'This content type is not translatable.', 'bogo-extended' ), array( 'status' => 400 ) );
	}

	$group       = BogoExt_Translations::get_group( $post->ID );
	$own_locale  = bogo_get_post_locale( $post->ID );
	$type_object = get_post_type_object( $post->post_type );
	$can_create  = $type_object && current_user_can( $type_object->cap->create_posts ) && 'auto-draft' !== $post->post_status;
	$languages   = bogoext_languages();
	$default     = bogoext_default_locale();

	if ( isset( $languages[ $default ] ) ) {
		$languages = array( $default => $languages[ $default ] ) + $languages;
	}

	$items = array();

	foreach ( $languages as $locale => $name ) {
		$member = $group[ $locale ] ?? null;
		$item   = array(
			'locale'      => $locale,
			'code'        => bogoext_language_code( $locale ),
			'name'        => $name,
			'is_current'  => $locale === $own_locale,
			'can_create'  => false,
			'translation' => null,
		);

		if ( $member ) {
			$can_edit = current_user_can( 'edit_post', $member['id'] );

			if ( $can_edit || 'publish' === $member['status'] ) {
				$item['translation'] = array(
					'id'           => $member['id'],
					'status'       => $member['status'],
					'status_label' => bogoext_status_label( $member['status'] ),
					'title'        => html_entity_decode( get_the_title( $member['id'] ), ENT_QUOTES, 'UTF-8' ),
					'edit_link'    => $can_edit ? get_edit_post_link( $member['id'], 'raw' ) : '',
					'view_link'    => 'publish' === $member['status'] ? get_permalink( $member['id'] ) : get_preview_post_link( $member['id'] ),
				);
			}
		} elseif ( $can_create ) {
			$item['can_create'] = current_user_can( 'bogo_access_locale', $locale );
		}

		$items[] = $item;
	}

	return rest_ensure_response( array(
		'post_id' => $post->ID,
		'locale'  => $own_locale,
		'items'   => $items,
	) );
}

add_action( 'enqueue_block_editor_assets', 'bogoext_editor_assets' );

function bogoext_editor_assets() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'post' !== $screen->base || ! bogo_is_localizable_post_type( $screen->post_type ) ) {
		return; // Not in the site editor, widgets editor, etc.
	}

	bogoext_enqueue_admin_style();

	wp_enqueue_script(
		'bogoext-editor',
		BOGOEXT_URL . 'assets/js/editor.js',
		array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' ),
		BOGOEXT_VERSION,
		true
	);

	wp_localize_script( 'bogoext-editor', 'bogoextEditor', array(
		'i18n' => array(
			'title'      => __( 'Translations', 'bogo-extended' ),
			'loading'    => __( 'Loading translations…', 'bogo-extended' ),
			'saveFirst'  => __( 'Save the post first to add translations.', 'bogo-extended' ),
			'thisPost'   => __( 'This post', 'bogo-extended' ),
			'missing'    => __( 'No translation', 'bogo-extended' ),
			'create'     => __( 'Create', 'bogo-extended' ),
			'creating'   => __( 'Creating…', 'bogo-extended' ),
			'edit'       => __( 'Edit', 'bogo-extended' ),
			'view'       => __( 'View', 'bogo-extended' ),
			'preview'    => __( 'Preview', 'bogo-extended' ),
			'failed'     => __( 'Could not create the translation.', 'bogo-extended' ),
			'noAccess'   => __( 'You cannot add this language.', 'bogo-extended' ),
			'createHint' => __( 'A draft copy will open in a new tab.', 'bogo-extended' ),
		),
	) );
}
