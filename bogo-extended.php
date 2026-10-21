<?php
/**
 * Plugin Name:       Bogo Extended
 * Description:       Add-on for the Bogo multilingual plugin: translation flags in post lists, editor translations sidebar, menu, term, link, ACF, synced pattern, theme strings and Contact Form 7 translations. Works on top of the original Bogo – no fork.
 * Version:           1.4.1
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Requires Plugins:  bogo
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bogo-extended
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'BOGOEXT_VERSION', '1.4.1' );
define( 'BOGOEXT_FILE', __FILE__ );
define( 'BOGOEXT_DIR', __DIR__ );
define( 'BOGOEXT_URL', plugin_dir_url( __FILE__ ) );

/*
 * Settings are needed very early (before Bogo builds its list of localizable
 * post types on `init`), so they live in a tiny standalone file.
 */
require_once BOGOEXT_DIR . '/includes/settings.php';

add_action( 'plugins_loaded', 'bogoext_bootstrap', 20, 0 );

/**
 * Loads the add-on once all plugins are available.
 * Nothing is loaded when Bogo is missing.
 */
function bogoext_bootstrap() {
	if ( ! defined( 'BOGO_VERSION' ) || ! function_exists( 'bogo_get_post_translations' ) ) {
		add_action( 'admin_notices', 'bogoext_notice_missing_bogo' );
		return;
	}

	require_once BOGOEXT_DIR . '/includes/functions.php';
	require_once BOGOEXT_DIR . '/includes/class-translations.php';
	require_once BOGOEXT_DIR . '/includes/class-url-resolver.php';
	require_once BOGOEXT_DIR . '/includes/slugs-data.php';

	// Custom post types chosen in Languages → Bogo Extended.
	add_filter( 'bogo_localizable_post_types', 'bogoext_localizable_post_types', 5 );

	$modules = array(
		'hide_admin_bar'  => 'admin-bar.php',
		'duplicate_fix'   => 'duplicate.php',
		'seo'             => 'seo.php',
		'list_table'      => 'list-table.php',
		'editor_sidebar'  => 'editor.php',
		'nav_menus'       => 'nav-menu.php',
		'taxonomies'      => 'taxonomy.php',
		'sync_terms'      => 'sync-terms.php',
		'content_links'   => 'content-links.php',
		'acf'             => 'acf.php',
		'synced_patterns' => 'synced-patterns.php',
		'switcher'        => 'switcher.php',
		'slugs'           => 'slugs.php',
		'strings'         => 'strings.php',
		'cf7'             => 'cf7.php',
		'tsf'             => 'the-seo-framework.php',
	);

	foreach ( $modules as $key => $file ) {
		if ( bogoext_module_enabled( $key ) ) {
			require_once BOGOEXT_DIR . '/includes/modules/' . $file;
		}
	}

	if ( is_admin() ) {
		require_once BOGOEXT_DIR . '/includes/admin/settings-page.php';
	}

	do_action( 'bogoext_loaded' );
}

/*
 * Translated URL bases add rewrite rules – let WordPress rebuild the rules
 * without them after deactivation (and with them after activation).
 */
register_activation_hook( __FILE__, 'bogoext_reset_rewrite_rules' );
register_deactivation_hook( __FILE__, 'bogoext_reset_rewrite_rules' );

function bogoext_reset_rewrite_rules() {
	delete_option( 'rewrite_rules' );
}

add_action( 'init', 'bogoext_load_textdomain', 1, 0 );

function bogoext_load_textdomain() {
	load_plugin_textdomain(
		'bogo-extended',
		false,
		dirname( plugin_basename( BOGOEXT_FILE ) ) . '/languages'
	);
}

function bogoext_notice_missing_bogo() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Bogo Extended requires the Bogo plugin (by Rock Lobster Inc.) to be installed and active.', 'bogo-extended' )
	);
}
