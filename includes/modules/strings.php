<?php
/**
 * Module: strings translation (Languages → Strings translation).
 *
 * Front end: makes WordPress load the compiled translation file of a text
 * domain BEFORE the theme's (or language pack's) own file. WordPress uses the
 * first loaded file that contains a string, so edited strings win and every
 * other string still comes from the original, up-to-date translations.
 *
 * Nothing is translated with gettext filters at runtime – WordPress reads the
 * standard .l10n.php / .mo file as with any other translation.
 */

defined( 'ABSPATH' ) || exit;

require_once BOGOEXT_DIR . '/includes/strings/class-strings-scanner.php';
require_once BOGOEXT_DIR . '/includes/strings/class-strings-store.php';

if ( is_admin() ) {
	require_once BOGOEXT_DIR . '/includes/strings/admin-page.php';
}

final class BogoExt_Strings_Loader {

	/** @var array locale => [ domain => true ] */
	private static $loaded = array();

	/** @var bool */
	private static $loading = false;

	public static function init() {
		if ( ! BogoExt_Strings_Store::compiled() ) {
			return; // Nothing compiled yet – no hooks at all.
		}

		add_action( 'load_textdomain', array( __CLASS__, 'before_load_textdomain' ), 10, 2 );
		add_action( 'unload_textdomain', array( __CLASS__, 'after_unload_textdomain' ), 10, 1 );
		add_action( 'after_setup_theme', array( __CLASS__, 'preload_theme_domains' ), 0 );
		add_action( 'change_locale', array( __CLASS__, 'preload_theme_domains' ), 10 );
	}

	/**
	 * Path of the compiled .mo for a domain in a locale, or ''.
	 */
	private static function file_for( $domain, $locale ) {
		$compiled = BogoExt_Strings_Store::compiled();

		if ( empty( $compiled[ $domain ][ $locale ] ) ) {
			return '';
		}

		return BogoExt_Strings_Store::file_base( $domain, $locale ) . '.mo';
	}

	/**
	 * Loads our file for a domain (WordPress prefers the .l10n.php next to it).
	 */
	private static function load( $domain, $locale ) {
		if ( isset( self::$loaded[ $locale ][ $domain ] ) ) {
			return;
		}

		$file = self::file_for( $domain, $locale );

		if ( '' === $file ) {
			return;
		}

		self::$loaded[ $locale ][ $domain ] = true;
		self::$loading = true;
		load_textdomain( $domain, $file, $locale );
		self::$loading = false;
	}

	/**
	 * Loads the theme's own translation files right after ours. Needed when
	 * our file is preloaded: WordPress would not "just-in-time" load a
	 * domain that is already loaded.
	 */
	private static function load_originals( $domain, $locale ) {
		self::$loading = true;

		foreach ( BogoExt_Strings_Store::theme_source_files( $domain, $locale ) as $base ) {
			if ( is_readable( $base . '.mo' ) || is_readable( $base . '.l10n.php' ) ) {
				load_textdomain( $domain, $base . '.mo', $locale );
			}
		}

		self::$loading = false;
	}

	/**
	 * Runs at the start of every load_textdomain(): our file goes first.
	 *
	 * @param string $domain Text domain.
	 * @param string $mofile File WordPress is about to load.
	 */
	public static function before_load_textdomain( $domain, $mofile ) {
		if ( self::$loading || 0 === strpos( wp_normalize_path( (string) $mofile ), wp_normalize_path( BogoExt_Strings_Store::dir() ) ) ) {
			return;
		}

		self::load( $domain, determine_locale() );
	}

	/**
	 * After unload_textdomain() our file is gone as well.
	 */
	public static function after_unload_textdomain( $domain ) {
		foreach ( self::$loaded as $locale => $domains ) {
			unset( self::$loaded[ $locale ][ $domain ] );
		}
	}

	/**
	 * Theme text domains are loaded up front (a theme without any translation
	 * file of its own would otherwise never trigger loading), followed by the
	 * theme's own translation files.
	 */
	public static function preload_theme_domains() {
		$locale = determine_locale();

		foreach ( BogoExt_Strings_Store::theme_domains() as $domain ) {
			if ( isset( self::$loaded[ $locale ][ $domain ] ) || '' === self::file_for( $domain, $locale ) ) {
				continue;
			}

			self::load( $domain, $locale );
			self::load_originals( $domain, $locale );
		}
	}
}

BogoExt_Strings_Loader::init();
