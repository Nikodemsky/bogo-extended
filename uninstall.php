<?php
/**
 * Removes the add-on settings. Translations (term/menu meta, CF7 links)
 * are kept on purpose – they are content, not configuration.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'bogoext_settings' );
