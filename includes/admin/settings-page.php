<?php
/**
 * Settings screen: Languages → Bogo Extended.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'bogoext_settings_menu', 20 );

function bogoext_settings_menu() {
	$hook = add_submenu_page(
		'bogo',
		__( 'Bogo Extended', 'bogo-extended' ),
		__( 'Bogo Extended', 'bogo-extended' ),
		'manage_options',
		'bogo-extended',
		'bogoext_settings_page'
	);

	if ( $hook ) {
		add_action( 'load-' . $hook, static function () {
			add_action( 'admin_enqueue_scripts', 'bogoext_enqueue_admin_style' );
		} );
	}
}

add_action( 'admin_init', 'bogoext_settings_register' );

function bogoext_settings_register() {
	register_setting( 'bogoext_settings', 'bogoext_settings', array(
		'type'              => 'array',
		'sanitize_callback' => 'bogoext_settings_sanitize',
		'default'           => bogoext_default_settings(),
	) );

	register_setting( 'bogoext_slugs', 'bogoext_slugs', array(
		'type'              => 'array',
		'sanitize_callback' => 'bogoext_slugs_sanitize_option',
		'default'           => array(),
	) );
}

/**
 * Module descriptions for the settings form.
 *
 * @return array key => [ label, description, available ]
 */
function bogoext_settings_modules() {
	return array(
		'hide_admin_bar'  => array(
			__( 'Hide the toolbar language menu', 'bogo-extended' ),
			__( 'Removes Bogo\'s language menu next to "Howdy" in the admin toolbar. It only changes the language of the admin interface for you, not the site language. The admin language can still be set in Users → Profile.', 'bogo-extended' ),
			true,
		),
		'list_table'      => array(
			__( 'Translations column', 'bogo-extended' ),
			__( 'Language badges in post/page lists – click a dashed badge to create a translation, a filled one to edit it.', 'bogo-extended' ),
			true,
		),
		'originals_only'  => array(
			__( '"Originals only" list view', 'bogo-extended' ),
			__( 'Post lists show default-language posts by default (plus posts that exist only in another language). Use "All languages" or Bogo\'s language filter to see everything.', 'bogo-extended' ),
			true,
		),
		'editor_sidebar'  => array(
			__( 'Editor translations sidebar', 'bogo-extended' ),
			__( 'Pinnable "Translations" panel in the block editor header: status of every language, edit, preview and create links.', 'bogo-extended' ),
			true,
		),
		'nav_menus'       => array(
			__( 'Menu translations', 'bogo-extended' ),
			__( 'Per-language labels, descriptions and custom-link URLs; menu items follow the translated post automatically. Also localizes block-theme navigation links.', 'bogo-extended' ),
			true,
		),
		'taxonomies'      => array(
			__( 'Term translations', 'bogo-extended' ),
			__( 'Per-language name and description fields on the term edit screen of the taxonomies selected below.', 'bogo-extended' ),
			true,
		),
		'sync_terms'      => array(
			__( 'Sync terms to translations', 'bogo-extended' ),
			__( 'When categories/tags of a default-language post change, apply the same terms to its translations.', 'bogo-extended' ),
			true,
		),
		'content_links'   => array(
			__( 'Automatic link localization', 'bogo-extended' ),
			__( 'On translated pages, links to default-language posts point to their translation. The link picker shows only originals.', 'bogo-extended' ),
			true,
		),
		'acf'             => array(
			__( 'ACF integration', 'bogo-extended' ),
			__( 'Post object, relationship, page link and link fields return translated content.', 'bogo-extended' ),
			class_exists( 'ACF' ) || function_exists( 'acf' ),
		),
		'synced_patterns' => array(
			__( 'Translatable synced patterns', 'bogo-extended' ),
			__( 'Synced patterns (reusable blocks) can be translated; the translation is rendered automatically.', 'bogo-extended' ),
			true,
		),
		'switcher'        => array(
			__( 'Dropdown switcher shortcode', 'bogo-extended' ),
			__( 'Enables [bogo-dropdown] (styles: popup, toggle, list; options: compact, flags).', 'bogo-extended' ),
			true,
		),
		'slugs'           => array(
			__( 'Translated URL bases', 'bogo-extended' ),
			__( 'Custom post type and taxonomy bases per language, e.g. /de/projekte/… instead of /de/realizacje/…, with 301 redirects from the old addresses. Set them in the table below.', 'bogo-extended' ),
			true,
		),
		'strings'         => array(
			__( 'Strings translation', 'bogo-extended' ),
			__( 'Languages → Strings Translation: translate texts written in the theme code (buttons, labels, footer…). Saved as standard .po/.mo/.l10n.php files that WordPress loads natively.', 'bogo-extended' ),
			true,
		),
		'cf7'             => array(
			__( 'Contact Form 7 translations', 'bogo-extended' ),
			__( 'One form per language linked into a group; the shortcode shows the form in the visitor\'s language.', 'bogo-extended' ),
			class_exists( 'WPCF7_ContactForm' ),
		),
		'duplicate_fix'   => array(
			__( 'Safe "Add translation"', 'bogo-extended' ),
			__( 'Keeps backslashes in block attributes and meta (Bogo strips them) and skips editor lock / old slug meta when copying.', 'bogo-extended' ),
			true,
		),
		'seo'             => array(
			__( 'hreflang fixes', 'bogo-extended' ),
			__( 'Only published translations in hreflang, adds x-default, none on 404/search pages.', 'bogo-extended' ),
			true,
		),
		'tsf'             => array(
			__( 'The SEO Framework: translated front page', 'bogo-extended' ),
			__( 'Uses the translated page\'s own SEO title/description on the translated static front page.', 'bogo-extended' ),
			defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'tsf' ),
		),
	);
}

/**
 * @param mixed $input Raw input.
 * @return array
 */
function bogoext_settings_sanitize( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = bogoext_default_settings();
	$clean    = array();

	foreach ( $defaults as $key => $default ) {
		if ( 'post_types' === $key ) {
			$list          = isset( $input[ $key ] ) ? (array) $input[ $key ] : array();
			$clean[ $key ] = array_values( array_intersect( array_map( 'sanitize_key', $list ), array_keys( bogoext_selectable_post_types() ) ) );
			continue;
		}

		if ( 'taxonomy_list' === $key ) {
			$list  = isset( $input[ $key ] ) ? (array) $input[ $key ] : array();
			$clean[ $key ] = array_values( array_filter( array_map( 'sanitize_key', $list ), 'taxonomy_exists' ) );
			continue;
		}

		$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
	}

	return $clean;
}

function bogoext_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings   = bogoext_settings();
	$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );

	unset( $taxonomies['nav_menu'], $taxonomies['link_category'], $taxonomies['post_format'], $taxonomies['wp_pattern_category'] );

	?>
	<div class="wrap bogoext-settings">
		<h1><?php esc_html_e( 'Bogo Extended', 'bogo-extended' ); ?></h1>
		<p><?php esc_html_e( 'Add-on modules for Bogo. Each one can be switched off independently.', 'bogo-extended' ); ?></p>

		<?php settings_errors(); ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'bogoext_settings' ); ?>

			<h2><?php esc_html_e( 'Translatable content types', 'bogo-extended' ); ?></h2>
			<?php bogoext_post_types_fieldset( $settings ); ?>

			<h2><?php esc_html_e( 'Modules', 'bogo-extended' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( bogoext_settings_modules() as $key => $module ) : ?>
					<?php list( $label, $description, $available ) = $module; ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="bogoext_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
								<?php esc_html_e( 'Enabled', 'bogo-extended' ); ?>
							</label>
							<?php if ( ! $available ) : ?>
								<span class="bogoext-module-off">— <?php esc_html_e( 'the required plugin is not active, the module stays idle', 'bogo-extended' ); ?></span>
							<?php endif; ?>
							<p class="description"><?php echo esc_html( $description ); ?></p>

							<?php if ( 'taxonomies' === $key ) : ?>
								<fieldset style="margin-top:8px">
									<legend class="screen-reader-text"><?php esc_html_e( 'Translatable taxonomies', 'bogo-extended' ); ?></legend>
									<?php foreach ( $taxonomies as $taxonomy ) : ?>
										<label style="display:inline-block;margin-right:14px">
											<input type="checkbox" name="bogoext_settings[taxonomy_list][]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php checked( in_array( $taxonomy->name, (array) $settings['taxonomy_list'], true ) ); ?> />
											<?php echo esc_html( $taxonomy->labels->name ); ?> <code><?php echo esc_html( $taxonomy->name ); ?></code>
										</label>
									<?php endforeach; ?>
								</fieldset>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button(); ?>
		</form>

		<hr />

		<?php bogoext_slugs_settings_form( $settings ); ?>


	</div>
	<?php
}

/**
 * Table of translated URL bases.
 *
 * @param array $settings Module settings.
 */
function bogoext_slugs_settings_form( $settings ) {
	$languages  = bogoext_secondary_languages();
	$post_types = bogoext_slugs_post_types();
	$taxonomies = bogoext_slugs_taxonomies();
	$option     = bogoext_slugs_option();

	$rows = array();

	foreach ( $post_types as $name => $object ) {
		$rows[] = array( 'pt', $name, 'base', $object->labels->name, (string) $object->rewrite['slug'], __( 'post type', 'bogo-extended' ) );

		$archive = bogoext_slugs_archive_slug( $object );

		if ( $archive && $archive !== $object->rewrite['slug'] ) {
			$rows[] = array( 'pt', $name, 'archive', $object->labels->name, $archive, __( 'archive', 'bogo-extended' ) );
		}
	}

	foreach ( $taxonomies as $name => $object ) {
		$rows[] = array( 'tax', $name, 'base', $object->labels->name, (string) $object->rewrite['slug'], __( 'taxonomy', 'bogo-extended' ) );
	}

	?>
	<h2 id="bogoext-slugs"><?php esc_html_e( 'Translated URL bases', 'bogo-extended' ); ?></h2>

	<?php if ( empty( $settings['slugs'] ) ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'The "Translated URL bases" module is switched off – the values below are stored but not used.', 'bogo-extended' ); ?></p></div>
	<?php endif; ?>

	<p><?php esc_html_e( 'Leave a field empty to keep the original base. Use lowercase words; slashes are allowed (e.g. "offer/projects"). Old addresses redirect (301) to the new ones automatically.', 'bogo-extended' ); ?></p>

	<?php if ( ! $rows || ! $languages ) : ?>
		<p><em><?php esc_html_e( 'Nothing to translate: there are no translatable custom post types or taxonomies with their own URL base, or only one language is active.', 'bogo-extended' ); ?></em></p>
		<?php return; ?>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'bogoext_slugs' ); ?>
		<table class="widefat striped bogoext-slugs-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Content', 'bogo-extended' ); ?></th>
					<th scope="col"><?php echo esc_html( bogoext_language_name( bogoext_default_locale() ) ); ?></th>
					<?php foreach ( $languages as $locale => $name ) : ?>
						<th scope="col"><?php echo esc_html( $name ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php list( $kind, $name, $field, $label, $original, $type_label ) = $row; ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $label ); ?></strong>
							<br /><span class="description"><?php echo esc_html( $type_label ); ?> · <code><?php echo esc_html( $name ); ?></code></span>
						</td>
						<td><code>/<?php echo esc_html( $original ); ?>/</code></td>
						<?php foreach ( $languages as $locale => $lang_name ) : ?>
							<td>
								<span class="bogoext-slug-prefix">/<?php echo esc_html( bogo_lang_slug( $locale ) ); ?>/</span>
								<input
									type="text"
									class="regular-text code"
									name="<?php echo esc_attr( sprintf( 'bogoext_slugs[%s][%s][%s][%s]', $kind, $name, $locale, $field ) ); ?>"
									value="<?php echo esc_attr( $option[ $kind ][ $name ][ $locale ][ $field ] ?? '' ); ?>"
									placeholder="<?php echo esc_attr( $original ); ?>"
									aria-label="<?php echo esc_attr( $label . ' – ' . $lang_name ); ?>"
								/>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php submit_button( __( 'Save URL bases', 'bogo-extended' ) ); ?>
	</form>
	<?php
}

/**
 * Checkboxes of translatable post types.
 *
 * @param array $settings Settings.
 */
function bogoext_post_types_fieldset( $settings ) {
	$selected = (array) $settings['post_types'];

	// Types made translatable elsewhere (Bogo itself, the theme, other plugins).
	remove_filter( 'bogo_localizable_post_types', 'bogoext_localizable_post_types', 5 );
	$from_code = bogo_localizable_post_types();
	add_filter( 'bogo_localizable_post_types', 'bogoext_localizable_post_types', 5 );

	?>
	<p><?php esc_html_e( 'Posts and pages are always translatable. Tick custom post types that should get translations (language column, "Add translation", editor panel, language prefix in URLs).', 'bogo-extended' ); ?></p>
	<fieldset class="bogoext-post-types">
		<legend class="screen-reader-text"><?php esc_html_e( 'Translatable content types', 'bogo-extended' ); ?></legend>

		<?php foreach ( array( 'post', 'page' ) as $builtin ) : ?>
			<?php $object = get_post_type_object( $builtin ); ?>
			<label>
				<input type="checkbox" checked disabled />
				<?php echo esc_html( $object->labels->name ); ?> <code><?php echo esc_html( $builtin ); ?></code>
			</label>
		<?php endforeach; ?>

		<?php foreach ( bogoext_selectable_post_types() as $name => $object ) : ?>
			<?php $locked = in_array( $name, $from_code, true ) && ! in_array( $name, $selected, true ); ?>
			<label>
				<input
					type="checkbox"
					name="bogoext_settings[post_types][]"
					value="<?php echo esc_attr( $name ); ?>"
					<?php checked( $locked || in_array( $name, $selected, true ) ); ?>
					<?php disabled( $locked ); ?>
				/>
				<?php echo esc_html( $object->labels->name ); ?> <code><?php echo esc_html( $name ); ?></code>
				<?php if ( $locked ) : ?>
					<span class="description">(<?php esc_html_e( 'enabled in code', 'bogo-extended' ); ?>)</span>
				<?php endif; ?>
				<?php if ( ! $object->show_in_rest ) : ?>
					<span class="description">(<?php esc_html_e( 'classic editor – translations via the Language box', 'bogo-extended' ); ?>)</span>
				<?php endif; ?>
			</label>
		<?php endforeach; ?>

		<?php if ( ! bogoext_selectable_post_types() ) : ?>
			<p><em><?php esc_html_e( 'No custom post types registered.', 'bogo-extended' ); ?></em></p>
		<?php endif; ?>
	</fieldset>
	<?php
}
