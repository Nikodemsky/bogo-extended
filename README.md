# Bogo Extended

🇬🇧 English version · 🇵🇱 [Polska wersja](README-pl.md)

An add-on for **[Bogo](https://wordpress.org/plugins/bogo/)**, the original lightweight multilingual plugin by Rock Lobster Inc. (the author of Contact Form 7).

This is **not a fork**. Bogo stays untouched and keeps updating normally from WordPress.org. Bogo Extended adds the missing features only through the hooks and APIs of Bogo, WordPress, ACF and Contact Form 7.

| | |
|---|---|
| Version | 1.6.0 |
| Requires | WordPress 6.7+, PHP 7.4+, Bogo 3.9+ |
| Optional | Contact Form 7 6.x, ACF / ACF PRO 6.x, The SEO Framework |
| License | GPL v2 or later |
| Text domain | `bogo-extended` (`pl_PL` translation included) |

---

## Installation

1. Install and activate **Bogo** (from the WordPress.org repository).
2. Upload the `bogo-extended` folder to `wp-content/plugins/` and activate the plugin.
3. Choose your languages in **Languages → Language Packs** (Bogo's screen).
4. Turn modules on and off in **Languages → Bogo Extended**.

Since WordPress 6.5, the `Requires Plugins: bogo` header prevents the add-on from being activated without Bogo.

### First-time setup

In **Languages → Bogo Extended**:

1. **Translatable content types.** Posts and pages are always translatable. Tick the custom post types (CPTs) that should get translations as well.
2. **Modules.** Everything is enabled by default except syncing terms to translations, the ACF "own relations per language" mode and ACF options pages translations. Turn off whatever you don't need.
3. **Translated URL bases** (optional). Enter the URL bases of content types and taxonomies in other languages, e.g. `projekte` instead of `realizacje`.

## Translatable content types

Out of the box Bogo only translates posts and pages. The settings screen lists every registered content type that has an admin UI. Ticking a type gives it everything posts and pages have:

- the translations column and language views in the list,
- creating translations from the list and from the editor sidebar (in the classic editor through Bogo's "Language" box),
- the language prefix in URLs (`/de/…`) and filtering content by language on the front end,
- the option to translate its URL base (the "Translated URL bases" module).

Content types made translatable in code (with the `bogo_localizable_post_types` filter in a theme or another plugin) are shown ticked and locked. Rewrite rules are rebuilt automatically when the list changes.

Existing posts of a newly selected type are treated as default-language content. You can add translations right after ticking the type.

---

## Modules

Every module works independently and can be switched off in the settings.

### 1. "Translations" column in post lists

In the lists of posts, pages and other translatable content types every row gets language badges:

| Badge | Meaning | Click |
|---|---|---|
| filled | published translation | edit the translation |
| light with a border | draft, pending, scheduled or private | edit the translation |
| dashed | no translation | creates a draft copy and opens it in a new tab |
| black outline | language of the current row | – |

Translations are created through **Bogo's official REST endpoint**, so its permissions apply: the role must be able to create content of that type and the user must have access to the language.

All translation groups visible on a list page are loaded with **two SQL queries**, not one query per row.

### 2. "Originals only" view and language views

When you open a list, you see only default-language content, so translations don't clutter it. Above the table, next to the status views, there are views:

**Originals only | All languages | Polski (12) | Deutsch (8) | English (3)**

- *Originals only* is the default view.
- *All languages* shows every language version.
- A language view shows only content in that language. The number counts all statuses shown under "All" (trash excluded).

Picking a language in the list does not change the language of the admin interface. Bogo's language filter (the dropdown next to "Filter") works as before. When the "Originals only" view is switched off in the settings, the *All languages* and language views remain.

The view never hides content: posts that exist **only in another language** (with no default-language version) are still shown. Search and Trash always show everything.

### 3. "Translations" sidebar in the block editor

A pinnable translations icon appears in the editor header. It opens a panel listing all languages with:

- the status and title of each translation,
- **Edit** and **View** or **Preview** buttons,
- a **Create** button for missing languages (the copy opens in a new tab).

The panel uses the official `PluginSidebar` API. Bogo's native "Language" panel keeps working next to it.

### 4. Hide the toolbar language menu

Bogo adds a menu with the language name to the admin toolbar, next to "Howdy, …". It **does not switch the site language**, only the admin interface language of the logged-in user, which makes it easy to misread. The module removes that menu (enabled by default). You can still set the admin language in **Users → Profile → Language**.

### 5. Menu translations

**Classic menus** (Appearance → Menus). Every menu item gets a collapsible *Translations* section with fields for each secondary language:

- **Navigation label**. An empty field means automatic: the title of the translated page or the translated category name.
- **URL**, for custom links only. An empty field means a link to the site's own content is localized automatically.
- **Description**.

On the front end in a secondary language:

- an item pointing to a page or post leads to its **published translation** and takes its title; `current-menu-item` highlighting works too,
- custom links to the site's own content are localized, external links stay unchanged,
- category items use the translated name.

The "Add menu items" panel shows only originals (translations are swapped in automatically).

Bogo's "Displayed on pages in…" mechanism (showing or hiding items per language) works unchanged and can be combined with these fields.

**Block themes** (Navigation block). Links to pages, posts and categories, as well as custom URLs, are rewritten to the version in the current language. The label is translated when it was equal to the original title or name.

### 6. Category, tag and other taxonomy translations

The term edit screen gets **Name**, **Slug** and **Description** fields for each secondary language. You choose the taxonomies in the settings. Categories and tags are selected by default, and others are available too, e.g. WooCommerce's `product_cat`.

- An empty field means the original is used, or the translation from Bogo's *Terms Translation* screen. Both mechanisms coexist.
- The translation applies wherever WordPress fetches the term: archive titles, `the_category()`, category lists, menus, breadcrumbs.
- The description is filtered like the native term description. Users without `unfiltered_html` can only use basic tags.

#### Translated term slugs

In Bogo a term is shared by all languages, so without this field the URL would have the Polish slug with a language prefix (`/de/category/aktualnosci/`). The **Slug** field in the *Translations* section (Posts → Categories → edit category) sets the slug for a language:

| Language | Slug not translated | With slug `neuigkeiten` | + base from module 16 |
|---|---|---|---|
| PL (default) | `/category/aktualnosci/` | `/category/aktualnosci/` | `/category/aktualnosci/` |
| DE | `/de/category/aktualnosci/` | `/de/category/neuigkeiten/` | `/de/kategorie/neuigkeiten/` |

- The field placeholder shows the original slug. An empty field, or a value equal to the original, means the original.
- The value is normalized with `sanitize_title()` (e.g. `Construction Sites` → `construction-sites`), just like the native slug.
- **Nested categories** get the translated slug on every level: `/de/kategorie/neuigkeiten/baustellen/`. A parent without a translation keeps its original slug.
- Links from `get_term_link()` (menus, `the_category()`, widgets, sitemaps, breadcrumbs) get the slug of the link's language. The language switcher and hreflang (with `x-default`) on a term archive give the right slug for each language.
- **301 redirect** from the original slug in that language: `/de/category/aktualnosci/` → `/de/category/neuigkeiten/`, keeping pagination and query parameters. The feed (`/de/category/neuigkeiten/feed/`) works.
- A translated slug only works in its own language. `/category/neuigkeiten/` returns 404, and a language without a translation uses the original.
- **Validation on save** rejects a slug already used in the same taxonomy and language by another term, either as its translation or as the original slug of another term without a translation. The field then keeps its previous value, a notice appears above the form and the rest of the translations is saved.
- Works for every taxonomy selected in the settings. Requires "pretty" permalinks. With `?cat=12` there is no slug in the URL.

### 7. Sync terms to translations (off by default)

When you change the categories or tags of a default-language post, the same terms are applied to its translations. The list of taxonomies can be narrowed with the `bogoext_sync_taxonomies` filter.

### 8. Automatic link localization

On secondary-language pages, links in the content (`the_content`, text and block widgets) pointing to **default-language** posts or pages are replaced with the URL of the published translation. `?query` parameters and `#anchors` are kept.

Left unchanged:

- external links, `mailto:`, `tel:`, `#…` anchors, files, and `wp-admin`, `wp-json` and `feed` URLs,
- links that **deliberately** point to another language, e.g. "Read in German",
- links with `class="no-localize"`, `data-bogo-keep` or `hreflang`,
- links to content without a published translation.

A link to the home page leads to the home page in the current language. The link insertion dialog shows only originals.

Parsing is done with `WP_HTML_Tag_Processor` (WordPress's HTML parser), not regular expressions. Results are cached per request and in the object cache.

### 9. ACF integration

On secondary-language pages:

| Field type | Behaviour |
|---|---|
| Post Object (single and multiple, returning an object or an ID) | returns the translation of the selected post |
| Relationship | as above, for every item |
| Page Link | returns the translation's URL |
| Link | URL localized. The title is replaced with the translated one only when it was equal to the post title |

In the admin, ACF post pickers show only originals, because the translation is swapped in automatically. This can be turned off with the `bogoext_acf_hide_translations` filter.

#### Own relations per language (off by default)

The default mode assumes relations are the same in every language (1:1 with the original). When a language needs different relations, enable **Languages → Bogo Extended → ACF: own relations per language**. Then:

- **Picking in the admin.** On a translated post (e.g. an English specialization) the Relationship, Post Object and Page Link fields list posts in that post's language, i.e. the English versions, not the originals. Custom post types included. Posts of types that are not translated are always listed.
- **A default-language post** works as before and lists originals.
- **"Add translation"** copies the relations from the original and immediately replaces them with their published translations in the new language. A post without a published translation stays as the original; the site still shows its translation as soon as it is published. This also applies to fields inside groups, repeaters and flexible content.
- **Language marker.** A selected post in another language than the edited one is marked with its code, e.g. `Cardiology (PL)`, so you can see at a glance which relations still come from the original.
- **On the site** exactly what was selected for that language is shown. Default-language items (e.g. relations saved before the option was enabled) are still swapped for their translations.

Fields on options pages, terms and user profiles have no language, so they always list originals (translated options pages from module 18 are the exception). Mapping on "Add translation" can be turned off with the `bogoext_acf_remap_on_duplicate` filter.

The mode can also be enabled for selected fields only, with the global option off. Identify the field by its key, because the field name changes while the form is rendered:

```php
add_filter( 'bogoext_acf_own_relations', function ( $enabled, $field ) {
	return in_array( $field['key'] ?? '', array( 'field_65f0c1a2b3c4d' ), true ) ? true : $enabled;
}, 10, 2 );
```

### 10. Translatable synced patterns (reusable blocks)

Patterns (`wp_block`) become translatable like pages: they get the "Add translation" button, badges and the editor sidebar. In the content you still insert the default-language pattern, and pages in another language render its **published translation**. The editor inserter shows only default-language patterns.

### 11. Language switcher `[bogo-dropdown]`

```text
[bogo-dropdown]                          dropdown menu (hover / click)
[bogo-dropdown style="toggle"]           expands in place (mobile menus)
[bogo-dropdown style="list"]             plain <ul> of links, no styles or scripts
[bogo-dropdown compact="1"]              short codes: PL, DE, EN
[bogo-dropdown flags="1"]                flags (Bogo's emoji) before the name
[bogo-dropdown hide_missing="0"]         also shows languages without a translation (as text)
[bogo-dropdown class="my-class"]         extra CSS class
```

In a PHP template:

```php
<?php echo do_shortcode( '[bogo-dropdown style="toggle" compact="1"]' ); ?>
```

Details:

- Only languages with somewhere to go are shown: a published translation or an archive. The switcher is not shown with fewer than 2 languages.
- The toggle is a real `<button>` with `aria-expanded`. It works with the keyboard and closes with Esc or a click outside.
- With 5+ and 9+ languages the list is laid out in 2 or 3 columns.
- Change the look with CSS variables: `--bogoext-bg`, `--bogoext-fg`, `--bogoext-border`, `--bogoext-hover`, `--bogoext-radius`.
- CSS and JS are loaded only on pages that contain the shortcode.

#### Plain list (`style="list"`)

A variant with no styling at all: the plugin loads none of its CSS or JS, there is no button and no `<div>` wrapper, just plain HTML:

```html
<ul class="bogoext-list">
  <li class="is-current"><a href="…" hreflang="pl" lang="pl" aria-current="page">Polski</a></li>
  <li><a href="…" hreflang="en" lang="en">English</a></li>
</ul>
```

The `compact`, `flags`, `hide_missing` and `class` options work. The `bogoext-list` and `is-current` classes are only hooks for the theme; the plugin attaches no rules to them.

Bogo's native switcher block and widget keep working unchanged.

### 12. Contact Form 7 translations

Every language has **its own complete CF7 form**: fields, labels, validation messages, email content and additional settings. The forms are linked into a group, and on the page you insert **one shortcode or block**. Visitors see the version in their language.

How to translate a form:

1. Open the form in **Contact → Contact Forms**. The *Status* box (on the right) has a **Form language / Translations** section.
2. Set the *Form language* if it differs from the default one.
3. Click the dashed language badge, e.g. **DE**. A copy of the form, "Title [DE]", is created in that language and opens for editing right away.
4. Translate the fields, messages and emails, then save.
5. Keep the original's shortcode in the page content, e.g. `[contact-form-7 id="7dc7d64" title="Form PL"]`. On `/de/...` the DE form is shown.

Also:

- An existing form can be added to a group manually in the **Translation of** field (when the forms were created separately before).
- The forms list has a **Languages** column with badges for the whole group.
- Submission works normally: CF7 handles the translated form with its own emails and messages, and its built-in messages switch to the form's language.
- No translation means the original is shown.
- Deleting the original does not break the group: the oldest translation becomes the new original.
- The swap works in the shortcode, the CF7 block, page builders and `do_shortcode()`, because it hooks into `shortcode_atts_wpcf7`.

### 13. Safe "Add translation"

When Bogo copies a post it loses **every backslash** (`\`). Block attributes such as `"<"` turn into `"u003c"` and `\"` into `"`, which breaks the block's JSON. JSON stored in meta fields (Elementor, ACF blocks, page builders) gets corrupted too. The module fixes this on every copy, including copies made by Bogo itself, and does not carry `_edit_lock`, `_edit_last`, `_wp_old_slug` and similar meta over to the translation. Extend the list with the `bogoext_duplicate_excluded_meta` filter.

> Verified on a live WordPress 7.1.3 with Bogo 3.9.3. Without the module, a copy of the block `{"placeholder":"A<B \"q\""}` becomes `{"placeholder":"Au003cB "q""}` (broken block). With the module the copy is identical to the original.

### 14. hreflang fixes

- `<link rel="alternate" hreflang>` contains only **published** translations. Bogo also prints drafts and private content, which exposes their URLs.
- Adds `hreflang="x-default"` pointing to the default-language version.
- Skips hreflang on 404, search and preview pages and for content that exists in one language only.
- On 404 and search pages the language switcher leads to the home page of each language.

### 15. The SEO Framework – translated front page

On a translated static front page, TSF takes the title and description from its global settings, i.e. in the default language. The module uses the SEO title and description set on the translated page instead. Works only when TSF is active.

### 16. Translated URL bases (content types and taxonomies)

Bogo translates the slugs of single posts, because every translation is a separate post with its own slug. It leaves the URL **base** in the default language, though. This module lets you translate it:

| Language | Without the module | With the module |
|---|---|---|
| PL (default) | `/realizacje/projekt-x/` | `/realizacje/projekt-x/` |
| DE | `/de/realizacje/projekt-x/` | `/de/projekte/projekt-x/` |
| DE, archive | `/de/realizacje/` | `/de/projekte/` |
| DE, category | `/de/category/aktualnosci/` | `/de/kategorie/aktualnosci/` (term slug: module 6) |

**Setup.** **Languages → Bogo Extended → Translated URL bases** has a table with a row for every translatable content type and taxonomy and a column for every language. An empty field means the original base. When a content type's archive has a different slug than its posts (`has_archive => 'nasza-oferta'`), it gets its own row.

**What it covers:**
- posts, archives (with pagination and feeds) and taxonomy archives under the new URL,
- links from `get_permalink()`, `get_post_type_archive_link()` and `get_term_link()`, and therefore menus, the language switcher, hreflang, sitemaps and theme links,
- a **301 redirect** from the old URL (`/de/realizacje/x/` → `/de/projekte/x/`), keeping `?utm=…` parameters,
- on archive pages the switcher and hreflang give the right base for each language (Bogo would keep the base of the current language).

**Validation on save** rejects reserved words (`page`, `feed`, `wp-json`, language codes…), a base taken by a page in that language and a base used twice in the same language. An invalid field keeps its previous value and the rest is saved.

**Performance** (measured on a test install: 2 content types, 3 taxonomies, 3 languages):
- **Rewrite rules.** The module added 75 rules (281 before, 356 after). Matching a page URL takes about 0.03–0.04 ms longer per request.
- **Links.** Replacing the base costs about 1.4 µs per link and runs no database queries. 100 links on a page take about 0.15 ms.
- **Rules are rebuilt** only after the bases change or the module is switched on or off. WordPress then rebuilds them on the next visit, never during normal traffic.

The module does not work with "plain" permalinks (`?p=123`).

### 17. Theme strings translation (Languages → Strings Translation)

For translating texts written in the theme code: buttons, labels, the footer, messages – everything the theme prints through `__()`, `_e()`, `esc_html__()`, `_x()`, `_n()` etc. The screen is in **Languages → Strings Translation**.

**Scanning.** On the first visit to the screen the module scans the PHP files of the parent and child theme. The result is stored in the database, so later visits don't scan again. The scan repeats automatically when the theme or its version changes (e.g. after an update), and you can run it manually with **Rescan theme**.
- The scanner uses the PHP tokenizer, not regular expressions. It correctly reads strings with commas, quotes and concatenation (`'a' . 'b'`), and skips method calls (`$obj->__()`).
- It records the context (`_x`), plurals (`_n`), translator comments (`/* translators: … */`) and references (`file.php:line`).
- It skips the `vendor`, `node_modules` and `tests` directories and files larger than 1 MB.
- A test theme (twentytwentyone, 197 calls) scans in about 0.4 s.

**Table.** It is paginated (set the number of rows in "Screen Options") and has:
- a **language** selector (all Bogo languages, including the default one, for when the theme is written in English and the site is in Polish) and a **text domain** selector (the theme's domain by default; other domains used in the theme files are listed too),
- a search box and **All / Untranslated / Translated** views with counts,
- plural form fields matching the language's rules (3 forms for Polish, 2 for German),
- the current translation from the theme or language pack as a hint in an empty field. An empty field keeps that translation.

**Protection against PHP errors.** A translation that needs more arguments (`%s`, `%d`, `%2$s`) than the original is rejected with a message. In PHP 8 such a string in `printf()` causes a fatal error on the site.

**Files.** Saving creates standard gettext files:

```text
wp-content/languages/bogo-extended/{domain}-{locale}.po         editable e.g. in Poedit
wp-content/languages/bogo-extended/{domain}-{locale}.mo
wp-content/languages/bogo-extended/{domain}-{locale}.l10n.php   fast PHP format (WordPress 6.5+)
```

**How WordPress uses them:**
- There are no `gettext` filters on the front end. WordPress loads these files like any other translation, through `load_textdomain()`, and prefers the `.l10n.php` file, which is cached by OPcache.
- The add-on only takes care of the **order**: the strings file is loaded **before** the theme's or language pack's file. WordPress takes a translation from the first file that contains it. Your strings win, and every other text still comes from the theme's original, up-to-date translations.
- This also works for themes that ship no translation files at all or never call `load_theme_textdomain()`.
- The separate `bogo-extended` folder (instead of `languages/themes`) is deliberate, because language pack updates overwrite files in `languages/themes`.
- The database is the source of truth. If the files disappear (e.g. after a deployment), saving any page of the table recreates them.

**Limitations:**
- Only the theme's PHP files are scanned. Strings from JavaScript (`wp.i18n`) and `theme.json` need a different format (JSON) and are not supported.
- Dynamically built strings such as `__( $variable )` cannot be detected statically.
- **Content** (posts, pages, menus) is translated with the other modules. This one is only for texts in code.

### 18. Translated ACF options pages (ACF PRO, off by default)

ACF PRO options pages (`acf_add_options_page()` and pages added in the ACF admin) have one set of values for the whole site. With **Languages → Bogo Extended → ACF options pages translations** enabled, every language can have its own values, e.g. a different header tagline, different footer links or a different CTA text.

**In the admin** language tabs appear above the options page form. The default-language tab edits the existing values. Another language's tab edits a separate set of values:

| Tab state | Look | Meaning |
|---|---|---|
| language without its own values | dashed border | the form and the site show the default-language values |
| language with its own values | solid border | the form and the site show that language's values |

- **The first save** of a language tab creates a full copy of the values for that language. The form is pre-filled with the default-language values, so you only change what should differ. From then on the language is independent and changes in the default language no longer affect it.
- **"Delete this language's values"** removes the copy. The language goes back to the default-language values.
- **Shared fields.** Mark a field that should be the same in every language (phone, email, API key, logo) as **"Shared by all languages"** in its field settings in the field group. In other languages' tabs a note replaces the field saying it is edited in the default language, and the site always shows the default value. The setting applies to top-level fields; fields inside a group or repeater follow their parent.
- **Fields added later** to the field group use the default-language value in a language that already has its own values, until you save that tab.
- A repeater, flexible content or group is treated as a whole. A language has either its own rows or the default-language rows, never a mix.

**On the site** nothing has to change in the theme. `get_field( 'tagline', 'option' )`, `have_rows( 'links', 'option' )` etc. return the values of the page's language. This also works for options pages with their own `post_id`, e.g. `get_field( 'maps_key', 'api_keys' )`.

**Which pages.** The module settings list the options page IDs (`options` and custom `post_id`s). An unticked page has one set of values for all languages and no tabs. Subpages with the same `post_id` (usually `options`) share the setting.

The module works with the other ACF features: posts selected in Relationship and Post Object fields on an options page are swapped for their translations. In the "own relations per language" mode a language tab lists posts in that language.

Values are stored by ACF itself, as options with the language code: `options_de_DE_tagline` instead of `options_tagline`. Options pages that store their values on a post (numeric `post_id`) are not supported.

---

## Permissions

| Action | Required capability |
|---|---|
| Creating a post translation | Bogo endpoint: `create_posts` for the type, `edit_post` (or a published original) and access to the language (`bogo_access_locale`) |
| Editor sidebar (read) | `edit_post` for the edited post |
| Term translations | `edit_term` + nonce |
| Menu translations | `edit_theme_options` + the menus screen nonce |
| CF7 translations | `wpcf7_edit_contact_form` + nonce |
| Settings | `manage_options` + nonce |
| Strings translation | `manage_options` (filter `bogoext_strings_capability`) + nonce |
| ACF options page values | the options page's capability (`capability`) and access to the language in Bogo. Deleting a language's values also requires a nonce |

All form input is sanitized and all output is escaped.

---

## Stored data

| Key | Where | Content |
|---|---|---|
| `_locale`, `_original_post` | postmeta | Bogo's data (the add-on does not change it) |
| `_bogoext_i18n` | termmeta | `[ locale => [ name, description ] ]` |
| `_bogoext_slug_{locale}` | termmeta | translated term slug (e.g. `_bogoext_slug_de_DE`) |
| `_bogoext_i18n` | menu item postmeta | `[ locale => [ title, description, url ] ]` |
| `_bogoext_cf7_source` | CF7 form postmeta | ID of the group's source form |
| `_locale` | CF7 form postmeta | form language (CF7's native field) |
| `bogoext_settings` | options | module settings, translatable content types (`post_types`), taxonomies with translation fields |
| `bogoext_slugs` | options | translated URL bases: `[ pt / tax => [ name => [ locale => [ base, archive ] ] ] ]` |
| `bogoext_st_index` | options (no autoload) | theme scan result: strings, domains, references |
| `bogoext_st_tr_{locale}` | options (no autoload) | string translations of a language |
| `bogoext_st_compiled` | options | list of generated files (domain → languages) |
| `wp-content/languages/bogo-extended/` | files | generated `.po`, `.mo`, `.l10n.php` |
| `{post_id}_{locale}_{field}` | options | ACF options page values of a language, stored by ACF (e.g. `options_de_DE_tagline`) |
| `bogoext_shared` | ACF field settings | field shared by all languages |

Keys starting with an underscore are hidden from the custom fields box. Uninstalling removes only the settings; translations (content, strings and their files) are kept.

---

## For developers

### Functions

```php
// ID of a post's translation (0 when missing); published only by default
bogoext_get_translation_id( $post_id, 'de_DE', $published_only = true );

// The whole group: [ locale => [ id, status, type ] ] (request cache)
BogoExt_Translations::get_group( $post_id );
BogoExt_Translations::prime( $post_ids ); // load many groups at once

// URL in the current (or given) language
bogoext_localize_url( 'https://example.com/kontakt/', 'de_DE' );

// Term translation (empty string when missing)
bogoext_term_field( $term_id, 'de_DE', 'name' );

// Translated term slug ('' when missing) and term by translated slug
bogoext_term_slug( $term_id, 'de_DE' );                              // 'neuigkeiten'
bogoext_term_by_translated_slug( 'category', 'de_DE', 'neuigkeiten' ); // WP_Term|null

// CF7 forms: [ locale => form_id ]
bogoext_cf7_group( $form_id );

// Translated URL base ('' when missing) and fixing the base in any URL
bogoext_slugs_get( 'pt', 'realizacje', 'de_DE' );          // 'projekte'
bogoext_slugs_normalize_url( 'https://example.com/de/realizacje/x/' ); // …/de/projekte/x/
```

Bogo functions worth knowing: `bogo_get_post_translations()`, `bogo_get_post_translation()`, `bogo_get_post_locale()`, `bogo_language_switcher_links()`, `bogo_url()`.

### Filters

| Filter | Description |
|---|---|
| `bogoext_module_enabled( $enabled, $key )` | force a module on or off in code |
| `bogoext_settings( $settings )` | modify the settings |
| `bogoext_localizable_taxonomies( $list )` | taxonomies with translation fields |
| `bogoext_sync_taxonomies( $list, $post )` | taxonomies synced to translations |
| `bogoext_duplicate_excluded_meta( $keys, $post, $locale )` | meta not copied when creating a translation |
| `bogoext_hide_translations_in_link_picker( true )` | `false` = the link dialog shows translations too |
| `bogoext_acf_hide_translations( true, $field )` | `false` = ACF pickers show translations too |
| `bogoext_acf_own_relations( $enabled, $field, $post_id )` | "own relations per language" mode for a given field |
| `bogoext_acf_translatable_options( $ids )` | options page IDs with separate values per language |
| `bogoext_acf_remap_on_duplicate( true, $field, $post, $locale )` | `false` = "Add translation" copies relations without replacing them with translations |
| `bogoext_hreflang( $languages )` | final hreflang list |
| `bogoext_switcher_links( $links )` | `[bogo-dropdown]` switcher data |
| `bogoext_dropdown_html( $html, $links, $atts )` | switcher HTML |
| `bogoext_cf7_locale( $locale )` | language used to pick the CF7 form |
| `bogoext_strings_capability( 'manage_options' )` | capability for the "Strings Translation" screen |

The `bogoext_loaded` action fires after the modules are loaded.

Content types can also be made translatable in code, with Bogo's filter. Types enabled this way are shown as locked in the settings:

```php
add_filter( 'bogo_localizable_post_types', function ( $types ) {
	$types[] = 'product';
	return $types;
} );
```

---

## Limitations and notes

- Bogo stores translations as separate posts (`_locale`, `_original_post`), not in separate tables. The add-on keeps that model, so after deactivating it everything still works in plain Bogo (without the extra features).
- Automatic link localization covers posts, pages and the home page. Links to category archives in the content are not rewritten (in menus they are).
- In Bogo a term is shared by all languages, so a translated slug is an alias in termmeta, not a separate term. WordPress's native "Slug" field always shows the original slug.
- Category links typed manually into the content (`/de/category/aktualnosci/`) are not rewritten, but they redirect with a 301 to the right URL.
- A common permalink structure prefix (e.g. `/blog/%postname%/`) is not translated.
- After changing a base on a live site, clear the page cache (LiteSpeed, WP Rocket etc.). Old URLs redirect by themselves.
- A CF7 form has a single language. A translation is a separate form, so field changes have to be made in every version.
- The ACF, CF7 and TSF modules stay idle when the respective plugin is not active.
- In the "own relations" mode, ACF bidirectional relationships are not filled in on the other side on "Add translation", because the replacement happens before the post is saved. When you change the field manually and save, ACF updates the other side normally.

---

## File structure

```text
bogo-extended/
├── bogo-extended.php            bootstrap, checks that Bogo is present
├── uninstall.php
├── README.md                    documentation (English)
├── README-pl.md                 documentation (Polish)
├── includes/
│   ├── settings.php             settings (loaded first)
│   ├── functions.php            helper functions
│   ├── class-translations.php   translation groups (batch SQL + request cache)
│   ├── class-url-resolver.php   URL → post → translation URL
│   ├── slugs-data.php           data and validation of translated URL bases
│   ├── admin/                   settings screen
│   ├── strings/                 strings translation: scanner, storage and file compiler, screen
│   └── modules/                 one file = one module
├── assets/                      css, js (no build step)
└── languages/                   .pot + pl_PL
```

There is no build step (webpack, npm). The JS is plain ES2017 using WordPress's global `wp.*` packages.

---

## Changelog

**1.6.0**
- New module (off by default): translated ACF PRO options pages. Language tabs on the options page, default-language values for languages without their own values, "Shared by all languages" fields, deleting a language's values and choosing the translated pages in the settings.
- English README as the main documentation; the Polish one moved to `README-pl.md`.

**1.5.0**
- ACF: optional "own relations per language" mode (off by default). Relationship, Post Object and Page Link fields on a translated post list posts in its language. "Add translation" replaces the copied relations with published translations, and posts in another language are marked with the language code.

**1.4.1**
- `[bogo-dropdown style="list"]` – a plain list of links without the plugin's styles and scripts.

**1.4.0**
- Translated slugs of categories, tags and terms of other taxonomies: a "Slug" field per language, links, switcher and hreflang with the language's slug, 301 from the original slug, nested categories, collision validation.

**1.3.0**
- New "Strings translation" module: scanning the parent and child theme, a paginated translation table, saving to native `.po` / `.mo` / `.l10n.php` files loaded before the theme's translations, placeholder validation.

**1.2.0**
- Choosing translatable content types (CPTs) in the settings, without code.
- Language views with counts in post lists, next to "Originals only" and "All languages".
- New module: hide Bogo's language menu in the admin toolbar (on by default).

**1.1.0**
- New module: translated URL bases of content types and taxonomies, with 301 redirects, collision validation and correct switcher and hreflang on archives.
- The settings screen loads its own stylesheet.

**1.0.0**
- First release.
