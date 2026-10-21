<?php
/**
 * Settings storage. Loaded before everything else.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default values for all settings.
 *
 * @return array
 */
function bogoext_default_settings() {
	return array(
		'post_types'      => array(),
		'hide_admin_bar'  => 1,
		'duplicate_fix'   => 1,
		'seo'             => 1,
		'list_table'      => 1,
		'originals_only'  => 1,
		'editor_sidebar'  => 1,
		'nav_menus'       => 1,
		'taxonomies'      => 1,
		'taxonomy_list'   => array( 'category', 'post_tag' ),
		'sync_terms'      => 0,
		'content_links'   => 1,
		'acf'             => 1,
		'synced_patterns' => 1,
		'switcher'        => 1,
		'slugs'           => 1,
		'strings'         => 1,
		'cf7'             => 1,
		'tsf'             => 1,
	);
}

/**
 * Returns all settings merged with defaults.
 *
 * @return array
 */
function bogoext_settings() {
	$saved = get_option( 'bogoext_settings', array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$settings = array_merge( bogoext_default_settings(), $saved );

	return apply_filters( 'bogoext_settings', $settings );
}

/**
 * Returns a single setting.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function bogoext_get_setting( $key ) {
	$settings = bogoext_settings();

	return $settings[ $key ] ?? null;
}

/**
 * Whether a module is switched on.
 *
 * Integrations with third-party plugins are additionally gated by the
 * presence of that plugin inside their module file.
 *
 * @param string $key Module key.
 * @return bool
 */
function bogoext_module_enabled( $key ) {
	return (bool) apply_filters(
		'bogoext_module_enabled',
		! empty( bogoext_get_setting( $key ) ),
		$key
	);
}
