<?php
/**
 * Module: hide Bogo's language menu in the admin toolbar.
 *
 * Bogo adds a language menu next to "Howdy, …". It switches the language of
 * the admin interface for the current user, which is easily mistaken for a
 * switch of the site language. The admin language can still be changed in
 * Users → Profile → Language.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_bar_menu', 'bogoext_hide_admin_bar_switcher', 100 );

/**
 * @param WP_Admin_Bar $wp_admin_bar Toolbar.
 */
function bogoext_hide_admin_bar_switcher( $wp_admin_bar ) {
	foreach ( (array) $wp_admin_bar->get_nodes() as $id => $node ) {
		if ( 'bogo-user-locale' === $id || 'bogo-user-locale' === $node->parent ) {
			$wp_admin_bar->remove_node( $id );
		}
	}
}
