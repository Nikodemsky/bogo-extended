<?php
/**
 * Finds translatable strings (gettext calls) in theme files.
 *
 * Uses PHP's own tokenizer instead of regular expressions, so strings with
 * commas, parentheses, escaped quotes or concatenated literals are read
 * correctly, and method calls like $obj->__() are ignored.
 */

defined( 'ABSPATH' ) || exit;

final class BogoExt_Strings_Scanner {

	/**
	 * Function name => argument roles (s = singular, p = plural, c = context,
	 * d = domain, n = number / ignored).
	 */
	const FUNCTIONS = array(
		'__'              => array( 's', 'd' ),
		'_e'              => array( 's', 'd' ),
		'esc_html__'      => array( 's', 'd' ),
		'esc_html_e'      => array( 's', 'd' ),
		'esc_attr__'      => array( 's', 'd' ),
		'esc_attr_e'      => array( 's', 'd' ),
		'translate'       => array( 's', 'd' ),
		'_x'              => array( 's', 'c', 'd' ),
		'_ex'             => array( 's', 'c', 'd' ),
		'esc_html_x'      => array( 's', 'c', 'd' ),
		'esc_attr_x'      => array( 's', 'c', 'd' ),
		'_n'              => array( 's', 'p', 'n', 'd' ),
		'_nx'             => array( 's', 'p', 'n', 'c', 'd' ),
		'_n_noop'         => array( 's', 'p', 'd' ),
		'_nx_noop'        => array( 's', 'p', 'c', 'd' ),
	);

	/** Directories that never contain theme templates. */
	const SKIP_DIRS = array( '.git', '.svn', 'node_modules', 'vendor', 'tests', 'test', '.github', 'bower_components' );

	/** Files larger than this are skipped (minified bundles, data dumps). */
	const MAX_FILE_SIZE = 1048576;

	/**
	 * Scans the active theme (and its parent, if it is a child theme).
	 *
	 * @return array {
	 *     @type array $strings key => [ d, c, s, p, refs[], comment ]
	 *     @type array $domains domain => count
	 *     @type int   $files   Number of scanned files.
	 * }
	 */
	public static function scan_active_theme() {
		$dirs = array_unique( array(
			get_template_directory(),
			get_stylesheet_directory(),
		) );

		$strings = array();
		$files   = 0;

		foreach ( $dirs as $dir ) {
			$base = trailingslashit( dirname( $dir ) );

			foreach ( self::php_files( $dir ) as $file ) {
				$files++;
				$relative = str_replace( '\\', '/', substr( $file, strlen( $base ) ) );

				foreach ( self::scan_file( $file ) as $found ) {
					$key = self::key( $found['d'], $found['c'], $found['s'] );
					$ref = $relative . ':' . $found['line'];

					if ( ! isset( $strings[ $key ] ) ) {
						$strings[ $key ] = array(
							'd'       => $found['d'],
							'c'       => $found['c'],
							's'       => $found['s'],
							'p'       => $found['p'],
							'refs'    => array(),
							'comment' => $found['comment'],
						);
					}

					if ( '' === $strings[ $key ]['p'] && '' !== $found['p'] ) {
						$strings[ $key ]['p'] = $found['p'];
					}

					if ( '' === $strings[ $key ]['comment'] && '' !== $found['comment'] ) {
						$strings[ $key ]['comment'] = $found['comment'];
					}

					if ( count( $strings[ $key ]['refs'] ) < 10 ) {
						$strings[ $key ]['refs'][] = $ref;
					}
				}
			}
		}

		$domains = array();

		foreach ( $strings as $string ) {
			$domains[ $string['d'] ] = ( $domains[ $string['d'] ] ?? 0 ) + 1;
		}

		arsort( $domains );

		return array(
			'strings' => $strings,
			'domains' => $domains,
			'files'   => $files,
		);
	}

	/**
	 * Stable key of a string.
	 */
	public static function key( $domain, $context, $singular ) {
		return md5( $domain . "\x01" . $context . "\x04" . $singular );
	}

	/**
	 * PHP files of a directory, recursively.
	 *
	 * @param string $dir Directory.
	 * @return string[]
	 */
	public static function php_files( $dir ) {
		$files = array();

		if ( ! is_dir( $dir ) ) {
			return $files;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				static function ( $current ) {
					if ( $current->isDir() ) {
						return ! in_array( strtolower( $current->getFilename() ), self::SKIP_DIRS, true );
					}

					return 'php' === strtolower( $current->getExtension() ) && $current->getSize() <= self::MAX_FILE_SIZE;
				}
			)
		);

		foreach ( $iterator as $file ) {
			$files[] = $file->getPathname();
		}

		sort( $files );

		return $files;
	}

	/**
	 * Finds gettext calls in one file.
	 *
	 * @param string $file Path.
	 * @return array List of [ d, c, s, p, line, comment ].
	 */
	public static function scan_file( $file ) {
		$code = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( false === $code || false === stripos( $code, '_' ) ) {
			return array();
		}

		try {
			$tokens = token_get_all( $code );
		} catch ( \Throwable $e ) {
			return array();
		}

		$found   = array();
		$count   = count( $tokens );
		$comment = array( 'text' => '', 'line' => 0 );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( ! is_array( $token ) ) {
				continue;
			}

			if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
				if ( false !== stripos( $token[1], 'translators:' ) ) {
					$comment = array(
						'text' => self::clean_comment( $token[1] ),
						'line' => $token[2] + substr_count( $token[1], "\n" ),
					);
				}
				continue;
			}

			$name = $token[1];

			// PHP 8 tokenizes \__() as one fully qualified name.
			if ( defined( 'T_NAME_FULLY_QUALIFIED' ) && T_NAME_FULLY_QUALIFIED === $token[0] ) {
				$name = ltrim( $name, '\\' );
			} elseif ( T_STRING !== $token[0] ) {
				continue;
			}

			if ( ! isset( self::FUNCTIONS[ $name ] ) ) {
				continue;
			}

			// Skip $obj->__(), Class::__(), function __() declarations.
			$prev = self::previous_significant( $tokens, $i );

			if ( is_array( $prev ) && in_array( $prev[0], self::non_call_tokens(), true ) ) {
				continue;
			}

			$next = self::next_significant_index( $tokens, $i );

			if ( null === $next || '(' !== $tokens[ $next ] ) {
				continue;
			}

			$args = self::read_arguments( $tokens, $next );

			if ( null === $args ) {
				continue;
			}

			$roles  = self::FUNCTIONS[ $name ];
			$string = array( 's' => null, 'p' => '', 'c' => '', 'd' => 'default' );

			foreach ( $roles as $index => $role ) {
				if ( 'n' === $role ) {
					continue;
				}

				$value = $args[ $index ] ?? null;

				if ( 'd' === $role ) {
					if ( null !== $value ) {
						$string['d'] = $value;
					} elseif ( isset( $args[ $index ] ) || array_key_exists( $index, $args ) ) {
						$string['d'] = null; // Domain is a variable – unknown.
					}
					continue;
				}

				if ( null === $value ) {
					if ( 's' === $role || 'p' === $role || array_key_exists( $index, $args ) ) {
						$string['s'] = null; // Non-literal text/context cannot be translated statically.
						break;
					}
					continue;
				}

				$string[ $role ] = $value;
			}

			if ( null === $string['s'] || '' === $string['s'] || null === $string['d'] ) {
				continue;
			}

			$string['line']    = $token[2];
			$string['comment'] = ( $comment['text'] && $token[2] - $comment['line'] <= 2 ) ? $comment['text'] : '';
			$comment           = array( 'text' => '', 'line' => 0 ); // A comment belongs to the next call only.

			$found[] = $string;
		}

		return $found;
	}

	/**
	 * Token types that mean "this is not a function call".
	 */
	private static function non_call_tokens() {
		$types = array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW );

		if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) {
			$types[] = T_NULLSAFE_OBJECT_OPERATOR;
		}

		return $types;
	}

	private static function previous_significant( $tokens, $i ) {
		for ( $j = $i - 1; $j >= 0; $j-- ) {
			if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			// Fully qualified \__() – look one further back.
			if ( is_array( $tokens[ $j ] ) && T_NS_SEPARATOR === $tokens[ $j ][0] ) {
				continue;
			}

			return $tokens[ $j ];
		}

		return null;
	}

	private static function next_significant_index( $tokens, $i ) {
		$count = count( $tokens );

		for ( $j = $i + 1; $j < $count; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			return $j;
		}

		return null;
	}

	/**
	 * Reads call arguments. Each argument is its literal string value, or
	 * null when it is not a plain (possibly concatenated) string literal.
	 *
	 * @param array $tokens Tokens.
	 * @param int   $open   Index of "(".
	 * @return array|null
	 */
	private static function read_arguments( $tokens, $open ) {
		$count   = count( $tokens );
		$depth   = 0;
		$args    = array();
		$current = array();

		for ( $j = $open; $j < $count; $j++ ) {
			$t = $tokens[ $j ];

			if ( '(' === $t || '[' === $t || '{' === $t || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				$depth++;

				if ( 1 === $depth && '(' === $t ) {
					continue;
				}
			} elseif ( ')' === $t || ']' === $t || '}' === $t ) {
				$depth--;

				if ( 0 === $depth ) {
					$args[] = self::literal_value( $current );
					return $args;
				}
			} elseif ( ',' === $t && 1 === $depth ) {
				$args[]  = self::literal_value( $current );
				$current = array();
				continue;
			}

			$current[] = $t;
		}

		return null;
	}

	/**
	 * Value of an argument made only of string literals joined with ".".
	 *
	 * @param array $tokens Argument tokens.
	 * @return string|null
	 */
	private static function literal_value( array $tokens ) {
		$value     = '';
		$has_value = false;
		$expect    = 'string';

		foreach ( $tokens as $t ) {
			if ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			if ( 'string' === $expect && is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
				$value    .= self::decode_literal( $t[1] );
				$has_value = true;
				$expect    = 'dot';
				continue;
			}

			if ( 'dot' === $expect && '.' === $t ) {
				$expect = 'string';
				continue;
			}

			return null;
		}

		return ( $has_value && 'dot' === $expect ) ? $value : null;
	}

	/**
	 * Decodes a PHP string literal ('…' or "…" without variables).
	 */
	private static function decode_literal( $literal ) {
		$quote = $literal[0];
		$body  = substr( $literal, 1, -1 );

		if ( "'" === $quote ) {
			return str_replace( array( '\\\\', "\\'" ), array( '\\', "'" ), $body );
		}

		return stripcslashes( str_replace( '\\$', '$', $body ) );
	}

	private static function clean_comment( $comment ) {
		$comment = preg_replace( '#^\s*(/\*+|//|\#)\s*|\s*\*+/\s*$#', '', $comment );
		$comment = preg_replace( '#^\s*\*\s?#m', '', $comment );
		$comment = trim( preg_replace( '/\s+/', ' ', $comment ) );

		return (string) preg_replace( '/^.*?translators:\s*/i', '', $comment );
	}
}
