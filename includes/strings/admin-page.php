<?php
/**
 * Languages → Strings translation.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Capability needed to translate strings.
 */
function bogoext_strings_capability() {
	return (string) apply_filters( 'bogoext_strings_capability', 'manage_options' );
}

add_action( 'admin_menu', 'bogoext_strings_menu', 15 );

function bogoext_strings_menu() {
	$hook = add_submenu_page(
		'bogo',
		__( 'Strings Translation', 'bogo-extended' ),
		__( 'Strings Translation', 'bogo-extended' ),
		bogoext_strings_capability(),
		'bogoext-strings',
		'bogoext_strings_page'
	);

	if ( $hook ) {
		add_action( 'load-' . $hook, 'bogoext_strings_load' );
	}
}

/**
 * Current screen state from the query string.
 *
 * @return array{locale:string,domain:string,status:string,s:string}
 */
function bogoext_strings_state() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
	$languages = bogoext_languages();
	$index     = BogoExt_Strings_Store::index();
	$domains   = array_keys( (array) ( $index['domains'] ?? array() ) );

	$locale = isset( $_GET['st_locale'] ) ? sanitize_text_field( wp_unslash( $_GET['st_locale'] ) ) : '';

	if ( ! isset( $languages[ $locale ] ) ) {
		$secondary = bogoext_secondary_languages();
		$locale    = $secondary ? (string) array_key_first( $secondary ) : (string) array_key_first( $languages );
	}

	$domain = isset( $_GET['st_domain'] ) ? sanitize_text_field( wp_unslash( $_GET['st_domain'] ) ) : '';

	if ( ! in_array( $domain, $domains, true ) ) {
		$theme_domains = array_values( array_intersect( BogoExt_Strings_Store::theme_domains(), $domains ) );
		$domain        = $theme_domains ? $theme_domains[0] : (string) ( $domains[0] ?? '' );
	}

	$status = isset( $_GET['st_status'] ) ? sanitize_key( $_GET['st_status'] ) : 'all';
	$status = in_array( $status, array( 'all', 'untranslated', 'translated' ), true ) ? $status : 'all';

	$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	// phpcs:enable

	return compact( 'locale', 'domain', 'status', 's' );
}

/**
 * Screen load: first scan, actions, screen options.
 */
function bogoext_strings_load() {
	if ( ! current_user_can( bogoext_strings_capability() ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'bogo-extended' ), 403 );
	}

	bogoext_enqueue_admin_style();

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in the handlers.
	$action = isset( $_POST['bogoext_st_action'] ) ? sanitize_key( $_POST['bogoext_st_action'] ) : '';

	if ( 'rescan' === $action ) {
		check_admin_referer( 'bogoext_st_rescan', 'bogoext_st_nonce' );
		BogoExt_Strings_Store::maybe_scan( true );
		bogoext_strings_redirect( array( 'st_msg' => 'rescanned' ) );
	}

	if ( 'save' === $action ) {
		bogoext_strings_handle_save();
	}

	// One-time scan (and again whenever the theme or its version changes).
	if ( BogoExt_Strings_Store::maybe_scan() ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'The theme was scanned for translatable strings.', 'bogo-extended' ) . '</p></div>';
		} );
	}

	add_screen_option( 'per_page', array(
		'label'   => __( 'Strings per page', 'bogo-extended' ),
		'default' => 30,
		'option'  => 'bogoext_strings_per_page',
	) );
}

add_filter( 'set_screen_option_bogoext_strings_per_page', static function ( $status, $option, $value ) {
	return max( 5, min( 200, (int) $value ) );
}, 10, 3 );

/**
 * Redirects back to the screen keeping the filters.
 */
function bogoext_strings_redirect( array $args = array() ) {
	$state = bogoext_strings_state();
	// phpcs:ignore WordPress.Security.NonceVerification
	$paged = isset( $_REQUEST['paged'] ) ? max( 1, absint( $_REQUEST['paged'] ) ) : 1;

	$url = add_query_arg( array_merge( array(
		'page'      => 'bogoext-strings',
		'st_locale' => $state['locale'],
		'st_domain' => $state['domain'],
		'st_status' => $state['status'],
		's'         => $state['s'],
		'paged'     => $paged,
	), $args ), admin_url( 'admin.php' ) );

	wp_safe_redirect( $url );
	exit;
}

/**
 * Saves the translations shown on the current page.
 */
function bogoext_strings_handle_save() {
	// Own field name: the list table prints its own _wpnonce inside the form.
	check_admin_referer( 'bogoext_st_save', 'bogoext_st_nonce' );

	$state  = bogoext_strings_state();
	$locale = $state['locale'];
	$index  = BogoExt_Strings_Store::index();
	$keys   = isset( $_POST['st_keys'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['st_keys'] ) ) : array();
	$raw    = isset( $_POST['st_tr'] ) && is_array( $_POST['st_tr'] ) ? wp_unslash( $_POST['st_tr'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per value.

	$changes  = array();
	$rejected = 0;
	$domains  = array();

	foreach ( $keys as $key ) {
		if ( ! isset( $index['strings'][ $key ] ) ) {
			continue;
		}

		$string = $index['strings'][ $key ];
		$value  = $raw[ $key ] ?? '';

		if ( is_array( $value ) ) {
			$value = array_values( array_map( array( 'BogoExt_Strings_Store', 'sanitize_translation' ), $value ) );
			$check = $value;
		} else {
			$value = BogoExt_Strings_Store::sanitize_translation( $value );
			$check = array( $value );
		}

		$ok = true;

		foreach ( $check as $form ) {
			if ( '' !== $form && ! BogoExt_Strings_Store::placeholders_ok( $string, $form ) ) {
				$ok = false;
			}
		}

		if ( ! $ok ) {
			$rejected++;
			continue; // Keep the previous translation.
		}

		$changes[ $key ]             = $value;
		$domains[ $string['d'] ]     = true;
	}

	BogoExt_Strings_Store::save_translations( $locale, $changes );

	$errors = array();

	foreach ( array_keys( $domains ) as $domain ) {
		$result = BogoExt_Strings_Store::compile( $domain, $locale );

		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
		}
	}

	if ( $errors ) {
		set_transient( 'bogoext_st_error_' . get_current_user_id(), implode( ' ', $errors ), 60 );
	}

	bogoext_strings_redirect( array(
		'st_msg'      => $errors ? 'error' : 'saved',
		'st_rejected' => $rejected,
	) );
}

/**
 * Renders the screen.
 */
function bogoext_strings_page() {
	$state     = bogoext_strings_state();
	$index     = BogoExt_Strings_Store::index();
	$languages = bogoext_languages();
	$domains   = (array) ( $index['domains'] ?? array() );

	$table = new BogoExt_Strings_Table( $state );
	$table->prepare_items();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$msg      = isset( $_GET['st_msg'] ) ? sanitize_key( $_GET['st_msg'] ) : '';
	$rejected = isset( $_GET['st_rejected'] ) ? absint( $_GET['st_rejected'] ) : 0;
	// phpcs:enable

	?>
	<div class="wrap bogoext-strings">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Strings Translation', 'bogo-extended' ); ?></h1>

		<form method="post" class="bogoext-strings-rescan">
			<input type="hidden" name="bogoext_st_action" value="rescan" />
			<?php wp_nonce_field( 'bogoext_st_rescan', 'bogoext_st_nonce' ); ?>
			<button type="submit" class="page-title-action"><?php esc_html_e( 'Rescan theme', 'bogo-extended' ); ?></button>
		</form>
		<hr class="wp-header-end" />

		<?php if ( 'saved' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php esc_html_e( 'Translations saved. The translation files have been updated.', 'bogo-extended' ); ?>
				<?php if ( $rejected ) : ?>
					<br /><strong><?php echo esc_html( sprintf(
						/* translators: %d: number of strings */
						_n( '%d translation was not saved: it uses more placeholders (%%s, %%d…) than the original, which would cause a PHP error.', '%d translations were not saved: they use more placeholders (%%s, %%d…) than the original, which would cause a PHP error.', $rejected, 'bogo-extended' ),
						$rejected
					) ); ?></strong>
				<?php endif; ?>
			</p></div>
		<?php elseif ( 'rescanned' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The theme was scanned again.', 'bogo-extended' ); ?></p></div>
		<?php elseif ( 'error' === $msg ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( (string) get_transient( 'bogoext_st_error_' . get_current_user_id() ) ); ?></p></div>
		<?php endif; ?>

		<p class="description">
			<?php
			printf(
				/* translators: 1: number of strings, 2: number of files, 3: theme name, 4: date */
				esc_html__( '%1$d strings found in %2$d files of %3$s (scanned %4$s). Translations are saved as standard gettext files (.po, .mo, .l10n.php) in wp-content/languages/bogo-extended/ and loaded by WordPress before the theme\'s own translations.', 'bogo-extended' ),
				(int) count( (array) ( $index['strings'] ?? array() ) ),
				(int) ( $index['files'] ?? 0 ),
				esc_html( wp_get_theme()->get( 'Name' ) ),
				esc_html( ! empty( $index['scanned'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $index['scanned'] ) : '—' )
			);
			?>
		</p>

		<form method="get" class="bogoext-strings-filters">
			<input type="hidden" name="page" value="bogoext-strings" />

			<label for="bogoext-st-locale"><?php esc_html_e( 'Language', 'bogo-extended' ); ?></label>
			<select id="bogoext-st-locale" name="st_locale">
				<?php foreach ( $languages as $locale => $name ) : ?>
					<option value="<?php echo esc_attr( $locale ); ?>" <?php selected( $locale, $state['locale'] ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="bogoext-st-domain"><?php esc_html_e( 'Text domain', 'bogo-extended' ); ?></label>
			<select id="bogoext-st-domain" name="st_domain">
				<?php foreach ( $domains as $domain => $count ) : ?>
					<option value="<?php echo esc_attr( $domain ); ?>" <?php selected( $domain, $state['domain'] ); ?>>
						<?php echo esc_html( sprintf( '%s (%d)', $domain, $count ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<input type="hidden" name="st_status" value="<?php echo esc_attr( $state['status'] ); ?>" />
			<?php submit_button( __( 'Show', 'bogo-extended' ), 'secondary', '', false ); ?>
			<?php $table->search_box( __( 'Search strings', 'bogo-extended' ), 'bogoext-st' ); ?>
		</form>

		<?php $table->views(); ?>

		<form method="post">
			<input type="hidden" name="bogoext_st_action" value="save" />
			<?php wp_nonce_field( 'bogoext_st_save', 'bogoext_st_nonce' ); ?>
			<?php $table->display(); ?>
			<?php if ( $table->has_items() ) : ?>
				<?php submit_button( __( 'Save translations', 'bogo-extended' ) ); ?>
			<?php endif; ?>
		</form>
	</div>
	<?php
}

/**
 * Paginated table of strings.
 */
final class BogoExt_Strings_Table extends WP_List_Table {

	/** @var array */
	private $state;

	/** @var array */
	private $translations = array();

	/** @var array status => count */
	private $counts = array();

	public function __construct( array $state ) {
		$this->state = $state;

		parent::__construct( array(
			'singular' => 'string',
			'plural'   => 'strings',
			'ajax'     => false,
			'screen'   => get_current_screen(),
		) );
	}

	public function get_columns() {
		return array(
			'source'      => __( 'Original', 'bogo-extended' ),
			'translation' => sprintf(
				/* translators: %s: language name */
				__( 'Translation – %s', 'bogo-extended' ),
				bogoext_language_name( $this->state['locale'] )
			),
		);
	}

	public function prepare_items() {
		$index              = BogoExt_Strings_Store::index();
		$this->translations = BogoExt_Strings_Store::translations( $this->state['locale'] );

		$rows   = array();
		$counts = array( 'all' => 0, 'translated' => 0, 'untranslated' => 0 );
		$search = function_exists( 'mb_strtolower' ) ? mb_strtolower( $this->state['s'] ) : strtolower( $this->state['s'] );

		foreach ( (array) ( $index['strings'] ?? array() ) as $key => $string ) {
			if ( $string['d'] !== $this->state['domain'] ) {
				continue;
			}

			if ( '' !== $search ) {
				$haystack = $string['s'] . ' ' . $string['p'] . ' ' . $string['c'] . ' ' . ( is_array( $this->translations[ $key ] ?? '' ) ? implode( ' ', $this->translations[ $key ] ) : ( $this->translations[ $key ] ?? '' ) );
				$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );

				if ( false === strpos( $haystack, $search ) ) {
					continue;
				}
			}

			$translated = isset( $this->translations[ $key ] );
			$counts['all']++;
			$counts[ $translated ? 'translated' : 'untranslated' ]++;

			if ( 'all' !== $this->state['status'] && ( 'translated' === $this->state['status'] ) !== $translated ) {
				continue;
			}

			$string['key'] = $key;
			$rows[]        = $string;
		}

		usort( $rows, static function ( $a, $b ) {
			return strcasecmp( $a['s'], $b['s'] );
		} );

		$this->counts = $counts;
		$per_page     = $this->get_items_per_page( 'bogoext_strings_per_page', 30 );
		$total        = count( $rows );

		$this->items = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per_page, $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		) );

		$this->_column_headers = array( $this->get_columns(), array(), array(), 'source' );
	}

	protected function get_views() {
		$base = add_query_arg( array(
			'page'      => 'bogoext-strings',
			'st_locale' => $this->state['locale'],
			'st_domain' => $this->state['domain'],
			's'         => $this->state['s'],
		), admin_url( 'admin.php' ) );

		$labels = array(
			'all'          => __( 'All', 'bogo-extended' ),
			'untranslated' => __( 'Untranslated', 'bogo-extended' ),
			'translated'   => __( 'Translated', 'bogo-extended' ),
		);

		$views = array();

		foreach ( $labels as $status => $label ) {
			$views[ $status ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( add_query_arg( 'st_status', $status, $base ) ),
				$status === $this->state['status'] ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				number_format_i18n( $this->counts[ $status ] ?? 0 )
			);
		}

		return $views;
	}

	public function no_items() {
		esc_html_e( 'No strings found.', 'bogo-extended' );
	}

	protected function column_source( $item ) {
		$out = '<div class="bogoext-st-original">' . esc_html( $item['s'] ) . '</div>';

		if ( '' !== $item['p'] ) {
			$out .= '<div class="bogoext-st-original is-plural"><span class="bogoext-st-label">' . esc_html__( 'Plural:', 'bogo-extended' ) . '</span> ' . esc_html( $item['p'] ) . '</div>';
		}

		$meta = array();

		if ( '' !== $item['c'] ) {
			$meta[] = '<span class="bogoext-st-context">' . esc_html( sprintf(
				/* translators: %s: gettext context */
				__( 'context: %s', 'bogo-extended' ),
				$item['c']
			) ) . '</span>';
		}

		if ( '' !== $item['comment'] ) {
			$meta[] = '<span class="bogoext-st-comment">' . esc_html( $item['comment'] ) . '</span>';
		}

		$refs = (array) $item['refs'];

		if ( $refs ) {
			$meta[] = sprintf(
				'<span class="bogoext-st-refs" title="%1$s">%2$s%3$s</span>',
				esc_attr( implode( "\n", $refs ) ),
				esc_html( $refs[0] ),
				count( $refs ) > 1 ? esc_html( sprintf( ' +%d', count( $refs ) - 1 ) ) : ''
			);
		}

		if ( $meta ) {
			$out .= '<div class="bogoext-st-meta">' . implode( ' ', $meta ) . '</div>';
		}

		return $out;
	}

	protected function column_translation( $item ) {
		$key      = $item['key'];
		$value    = $this->translations[ $key ] ?? '';
		$existing = BogoExt_Strings_Store::existing_translation( $item, $this->state['locale'] );
		$rows     = max( 1, min( 6, (int) ceil( strlen( $item['s'] ) / 60 ) ) );
		$lang     = bogo_language_tag( $this->state['locale'] );

		$out = sprintf( '<input type="hidden" name="st_keys[]" value="%s" />', esc_attr( $key ) );

		if ( '' === $item['p'] ) {
			$out .= sprintf(
				'<textarea name="st_tr[%1$s]" rows="%2$d" lang="%3$s" placeholder="%4$s" aria-label="%5$s">%6$s</textarea>',
				esc_attr( $key ),
				$rows,
				esc_attr( $lang ),
				esc_attr( $existing[0] ?? '' ),
				esc_attr( $item['s'] ),
				esc_textarea( is_array( $value ) ? (string) ( $value[0] ?? '' ) : (string) $value )
			);
		} else {
			$forms = BogoExt_Strings_Store::plural_count( $this->state['locale'] );

			for ( $i = 0; $i < $forms; $i++ ) {
				$form_value = is_array( $value ) ? (string) ( $value[ $i ] ?? '' ) : ( 0 === $i ? (string) $value : '' );

				$out .= sprintf(
					'<label class="bogoext-st-form"><span>%1$s</span><textarea name="st_tr[%2$s][%3$d]" rows="1" lang="%4$s" placeholder="%5$s">%6$s</textarea></label>',
					esc_html( sprintf(
						/* translators: %d: plural form number */
						__( 'Form %d', 'bogo-extended' ),
						$i + 1
					) ),
					esc_attr( $key ),
					$i,
					esc_attr( $lang ),
					esc_attr( $existing[ $i ] ?? '' ),
					esc_textarea( $form_value )
				);
			}
		}

		if ( ! isset( $this->translations[ $key ] ) && $existing ) {
			$out .= '<p class="bogoext-st-hint">' . esc_html__( 'Placeholder = current translation from the theme or language pack. Leave empty to keep it.', 'bogo-extended' ) . '</p>';
		}

		$needed = max( BogoExt_Strings_Store::required_args( $item['s'] ), BogoExt_Strings_Store::required_args( $item['p'] ) );

		if ( $needed ) {
			$out .= '<p class="bogoext-st-hint">' . esc_html( sprintf(
				/* translators: %d: number of placeholders */
				_n( 'Keep the placeholder (%%s, %%d, %%1$s…) – at most %d.', 'Keep the placeholders (%%s, %%d, %%1$s…) – at most %d.', $needed, 'bogo-extended' ),
				$needed
			) ) . '</p>';
		}

		return $out;
	}

	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'bogoext-strings-table' );
	}
}
