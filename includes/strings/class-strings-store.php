<?php
/**
 * Strings translation – storage and compilation.
 *
 * Source of truth: the database (translations survive file wipes and
 * deployments). Output: standard gettext files that WordPress loads itself:
 *
 *   wp-content/languages/bogo-extended/{domain}-{locale}.po        (editable, e.g. in Poedit)
 *   wp-content/languages/bogo-extended/{domain}-{locale}.mo
 *   wp-content/languages/bogo-extended/{domain}-{locale}.l10n.php  (WordPress 6.5+ fast format)
 *
 * A separate folder is used on purpose: wp-content/languages/themes is
 * overwritten by language pack updates. The files contain only the edited
 * strings; the theme's own translations keep loading from their usual
 * place (after ours), so theme updates still bring fresh translations.
 */

defined( 'ABSPATH' ) || exit;

final class BogoExt_Strings_Store {

	const INDEX_OPTION    = 'bogoext_st_index';
	const COMPILED_OPTION = 'bogoext_st_compiled';

	/* ---------------------------------------------------------------
	 * Scan index
	 * ------------------------------------------------------------- */

	/**
	 * @return array{strings:array,domains:array,files:int,signature:string,scanned:int}
	 */
	public static function index() {
		$index = get_option( self::INDEX_OPTION );

		return is_array( $index ) ? $index : array();
	}

	/**
	 * Identifies the active theme(s) and their versions.
	 */
	public static function theme_signature() {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		return implode( '|', array(
			get_stylesheet(),
			$theme->get( 'Version' ),
			get_template(),
			$parent ? $parent->get( 'Version' ) : '',
		) );
	}

	/**
	 * Scans when nothing was scanned yet, the theme changed or $force is set.
	 *
	 * @param bool $force Rescan unconditionally.
	 * @return bool Whether a scan happened.
	 */
	public static function maybe_scan( $force = false ) {
		$index = self::index();

		if ( ! $force && ! empty( $index['strings'] ) && ( $index['signature'] ?? '' ) === self::theme_signature() ) {
			return false;
		}

		$result = BogoExt_Strings_Scanner::scan_active_theme();

		$result['signature'] = self::theme_signature();
		$result['scanned']   = time();

		update_option( self::INDEX_OPTION, $result, false );

		return true;
	}

	/**
	 * Text domains declared by the active theme (child first).
	 *
	 * @return string[]
	 */
	public static function theme_domains() {
		$domains = array();
		$theme   = wp_get_theme();

		foreach ( array( $theme, $theme->parent() ) as $t ) {
			if ( ! $t ) {
				continue;
			}

			$domain = (string) $t->get( 'TextDomain' );
			$domains[] = '' !== $domain ? $domain : $t->get_stylesheet();
		}

		return array_values( array_unique( array_filter( $domains ) ) );
	}

	/* ---------------------------------------------------------------
	 * Translations (DB)
	 * ------------------------------------------------------------- */

	private static function option_name( $locale ) {
		return 'bogoext_st_tr_' . strtolower( preg_replace( '/[^A-Za-z0-9_]/', '', $locale ) );
	}

	/**
	 * @param string $locale Locale.
	 * @return array key => string|string[]
	 */
	public static function translations( $locale ) {
		$data = get_option( self::option_name( $locale ), array() );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Merges changes into the stored translations of a locale.
	 *
	 * @param string $locale  Locale.
	 * @param array  $changes key => string|string[]|'' ('' deletes).
	 */
	public static function save_translations( $locale, array $changes ) {
		$data = self::translations( $locale );

		foreach ( $changes as $key => $value ) {
			$empty = is_array( $value ) ? '' === implode( '', $value ) : '' === (string) $value;

			if ( $empty ) {
				unset( $data[ $key ] );
			} else {
				$data[ $key ] = $value;
			}
		}

		update_option( self::option_name( $locale ), $data, false );
	}

	/* ---------------------------------------------------------------
	 * Validation
	 * ------------------------------------------------------------- */

	/**
	 * How many printf arguments a string needs (%s → 1, %2$s → 2, %% → 0).
	 */
	public static function required_args( $text ) {
		if ( ! preg_match_all( '/%(?:(\d+)\$)?[-+ 0#]*(?:\'.)?\d*(?:\.\d+)?([bcdeEfFgGosuxX%])/', (string) $text, $m, PREG_SET_ORDER ) ) {
			return 0;
		}

		$sequential = 0;
		$positional = 0;

		foreach ( $m as $match ) {
			if ( '%' === $match[2] ) {
				continue;
			}

			if ( '' !== $match[1] ) {
				$positional = max( $positional, (int) $match[1] );
			} else {
				$sequential++;
			}
		}

		return max( $sequential, $positional );
	}

	/**
	 * A translation must not need more printf arguments than the original
	 * provides – otherwise sprintf() throws ArgumentCountError (fatal error
	 * on the front end in PHP 8).
	 *
	 * @param array  $string      Index entry.
	 * @param string $translation Translation.
	 * @return bool
	 */
	public static function placeholders_ok( array $string, $translation ) {
		$available = max( self::required_args( $string['s'] ), self::required_args( $string['p'] ?? '' ) );

		return self::required_args( $translation ) <= $available;
	}

	/**
	 * Cleans a submitted translation.
	 */
	public static function sanitize_translation( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = str_replace( array( "\r\n", "\r", "\0" ), array( "\n", "\n", '' ), (string) $value );

		return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
	}

	/* ---------------------------------------------------------------
	 * Plural forms
	 * ------------------------------------------------------------- */

	/**
	 * Plural-Forms header for a locale (from the installed WordPress
	 * language file, with a fallback for common languages).
	 */
	public static function plural_forms( $locale ) {
		static $cache = array();

		if ( isset( $cache[ $locale ] ) ) {
			return $cache[ $locale ];
		}

		foreach ( array( WP_LANG_DIR . "/{$locale}.l10n.php", WP_LANG_DIR . "/{$locale}.mo" ) as $file ) {
			if ( is_readable( $file ) ) {
				$source  = WP_Translation_File::create( $file );
				$headers = $source ? $source->headers() : array();

				if ( ! empty( $headers['plural-forms'] ) ) {
					return $cache[ $locale ] = $headers['plural-forms'];
				}
			}
		}

		$lang = strtolower( strtok( $locale, '_' ) );

		$known = array(
			'pl' => 'nplurals=3; plural=(n == 1) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);',
			'cs' => 'nplurals=3; plural=(n == 1) ? 0 : ((n >= 2 && n <= 4) ? 1 : 2);',
			'sk' => 'nplurals=3; plural=(n == 1) ? 0 : ((n >= 2 && n <= 4) ? 1 : 2);',
			'ru' => 'nplurals=3; plural=(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);',
			'uk' => 'nplurals=3; plural=(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);',
			'lt' => 'nplurals=3; plural=(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && (n % 100 < 10 || n % 100 >= 20)) ? 1 : 2);',
			'ja' => 'nplurals=1; plural=0;',
			'zh' => 'nplurals=1; plural=0;',
			'ko' => 'nplurals=1; plural=0;',
			'fr' => 'nplurals=2; plural=n > 1;',
		);

		return $cache[ $locale ] = $known[ $lang ] ?? 'nplurals=2; plural=n != 1;';
	}

	/**
	 * Number of plural forms of a locale.
	 */
	public static function plural_count( $locale ) {
		return preg_match( '/nplurals\s*=\s*(\d+)/', self::plural_forms( $locale ), $m ) ? max( 1, (int) $m[1] ) : 2;
	}

	/* ---------------------------------------------------------------
	 * Existing translations of a theme (merged into our file)
	 * ------------------------------------------------------------- */

	/**
	 * Translation files that WordPress would load for a theme domain.
	 *
	 * @return string[] Paths without extension, highest priority first.
	 */
	public static function theme_source_files( $domain, $locale ) {
		$bases = array();
		$theme = wp_get_theme();

		foreach ( array( $theme, $theme->parent() ) as $t ) {
			if ( ! $t ) {
				continue;
			}

			$path = $t->get( 'DomainPath' ) ? trim( $t->get( 'DomainPath' ), '/' ) : 'languages';

			foreach ( array_unique( array( $path, 'languages', 'lang' ) ) as $dir ) {
				$bases[] = trailingslashit( $t->get_stylesheet_directory() ) . $dir . '/' . $locale;
			}
		}

		$bases[] = WP_LANG_DIR . '/themes/' . $domain . '-' . $locale;

		return array_unique( $bases );
	}

	/**
	 * Existing translations of a theme domain: key (ctx\4singular) => entry args.
	 *
	 * @return array
	 */
	public static function existing_translations( $domain, $locale ) {
		$entries = array();

		if ( ! in_array( $domain, self::theme_domains(), true ) ) {
			return $entries;
		}

		foreach ( array_reverse( self::theme_source_files( $domain, $locale ) ) as $base ) {
			if ( is_readable( $base . '.mo' ) ) {
				$mo = new MO();

				if ( $mo->import_from_file( $base . '.mo' ) ) {
					foreach ( $mo->entries as $entry ) {
						$entries[ self::entry_key( $entry->context, $entry->singular ) ] = array(
							'singular'     => $entry->singular,
							'plural'       => $entry->is_plural ? $entry->plural : null,
							'context'      => $entry->context,
							'translations' => $entry->translations,
						);
					}
				}
			} elseif ( is_readable( $base . '.l10n.php' ) ) {
				$source = WP_Translation_File::create( $base . '.l10n.php' );

				foreach ( $source ? $source->entries() : array() as $original => $translation ) {
					$context  = null;
					$singular = $original;

					if ( false !== strpos( $original, "\4" ) ) {
						list( $context, $singular ) = explode( "\4", $original, 2 );
					}

					$forms = explode( "\0", $translation );

					$entries[ self::entry_key( $context, $singular ) ] = array(
						'singular'     => $singular,
						'plural'       => count( $forms ) > 1 ? $singular : null,
						'context'      => $context,
						'translations' => $forms,
					);
				}
			}
		}

		return $entries;
	}

	private static function entry_key( $context, $singular ) {
		return ( null !== $context && '' !== $context ) ? $context . "\4" . $singular : $singular;
	}

	/**
	 * Existing translation of one string ('' if none) – shown as a hint.
	 */
	public static function existing_translation( array $string, $locale ) {
		static $cache = array();

		$cache_key = $string['d'] . '|' . $locale;

		if ( ! isset( $cache[ $cache_key ] ) ) {
			$cache[ $cache_key ] = self::existing_translations( $string['d'], $locale );
		}

		$entry = $cache[ $cache_key ][ self::entry_key( $string['c'], $string['s'] ) ] ?? null;

		return $entry ? $entry['translations'] : array();
	}

	/* ---------------------------------------------------------------
	 * Files
	 * ------------------------------------------------------------- */

	public static function dir() {
		return WP_LANG_DIR . '/bogo-extended';
	}

	/**
	 * Path without extension.
	 */
	public static function file_base( $domain, $locale ) {
		return self::dir() . '/' . sanitize_file_name( $domain ) . '-' . $locale;
	}

	/**
	 * domain => [ locale => timestamp ] of compiled files.
	 */
	public static function compiled() {
		$compiled = get_option( self::COMPILED_OPTION, array() );

		return is_array( $compiled ) ? $compiled : array();
	}

	/**
	 * Writes .po, .mo and .l10n.php for a domain and locale.
	 *
	 * @return true|WP_Error
	 */
	public static function compile( $domain, $locale ) {
		if ( ! class_exists( 'PO' ) ) {
			require_once ABSPATH . WPINC . '/pomo/po.php';
		}

		$index        = self::index();
		$translations = self::translations( $locale );
		$entries      = array();
		$own          = 0;
		$nplurals     = self::plural_count( $locale );

		foreach ( (array) ( $index['strings'] ?? array() ) as $key => $string ) {
			if ( $string['d'] !== $domain || ! isset( $translations[ $key ] ) ) {
				continue;
			}

			$value = $translations[ $key ];
			$forms = is_array( $value ) ? array_values( $value ) : array( (string) $value );

			if ( '' !== $string['p'] ) {
				$forms = array_pad( array_slice( $forms, 0, $nplurals ), $nplurals, '' );

				// Missing plural forms fall back to the first one.
				foreach ( $forms as $i => $form ) {
					if ( '' === $form ) {
						$forms[ $i ] = $forms[0];
					}
				}
			} else {
				$forms = array( $forms[0] );
			}

			if ( '' === implode( '', $forms ) ) {
				continue;
			}

			$entries[ self::entry_key( $string['c'], $string['s'] ) ] = array(
				'singular'     => $string['s'],
				'plural'       => '' !== $string['p'] ? $string['p'] : null,
				'context'      => '' !== $string['c'] ? $string['c'] : null,
				'translations' => $forms,
				'references'   => $string['refs'],
				'extracted_comments' => $string['comment'],
			);
			$own++;
		}

		$base     = self::file_base( $domain, $locale );
		$compiled = self::compiled();

		if ( 0 === $own ) {
			foreach ( array( '.po', '.mo', '.l10n.php' ) as $ext ) {
				if ( file_exists( $base . $ext ) ) {
					wp_delete_file( $base . $ext );
				}
			}

			unset( $compiled[ $domain ][ $locale ] );

			if ( empty( $compiled[ $domain ] ) ) {
				unset( $compiled[ $domain ] );
			}

			update_option( self::COMPILED_OPTION, $compiled, true );

			return true;
		}

		if ( ! wp_mkdir_p( self::dir() ) || ! wp_is_writable( self::dir() ) ) {
			return new WP_Error( 'bogoext_st_not_writable', sprintf(
				/* translators: %s: directory */
				__( 'The folder %s is not writable. Translations were saved in the database, but the files could not be created.', 'bogo-extended' ),
				self::dir()
			) );
		}

		$headers = array(
			'Project-Id-Version'        => $domain,
			'Language'                  => $locale,
			'MIME-Version'              => '1.0',
			'Content-Type'              => 'text/plain; charset=UTF-8',
			'Content-Transfer-Encoding' => '8bit',
			'Plural-Forms'              => self::plural_forms( $locale ),
			'PO-Revision-Date'          => gmdate( 'Y-m-d H:i+0000' ),
			'X-Generator'               => 'Bogo Extended ' . BOGOEXT_VERSION,
			'X-Domain'                  => $domain,
		);

		$mo = new MO();
		$po = new PO();
		$mo->set_headers( $headers );
		$po->set_headers( $headers );

		foreach ( $entries as $args ) {
			$args  = array_filter( $args, static function ( $v ) { return null !== $v && '' !== $v && array() !== $v; } );
			$entry = new Translation_Entry( $args );
			$mo->add_entry( $entry );
			$po->add_entry( $entry );
		}

		if ( ! $mo->export_to_file( $base . '.mo' ) ) {
			return new WP_Error( 'bogoext_st_write', __( 'Could not write the .mo file.', 'bogo-extended' ) );
		}

		$po->export_to_file( $base . '.po' );

		$php = WP_Translation_File::transform( $base . '.mo', 'php' );

		if ( false !== $php ) {
			file_put_contents( $base . '.l10n.php', $php ); // phpcs:ignore WordPress.WP.AlternativeFunctions

			if ( function_exists( 'opcache_invalidate' ) ) {
				@opcache_invalidate( $base . '.l10n.php', true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}

		$compiled[ $domain ][ $locale ] = time();
		update_option( self::COMPILED_OPTION, $compiled, true );

		return true;
	}
}
