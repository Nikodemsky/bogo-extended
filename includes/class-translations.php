<?php
/**
 * Fast, request-cached lookup of translation groups.
 *
 * Bogo stores the group in the `_original_post` meta (the GUID of the
 * first post in the group) and the language in `_locale`. Core Bogo runs one
 * WP_Query per post; list tables and menus need dozens of lookups, so this
 * class loads many groups with two SQL queries.
 *
 * There is no persistent cache: nothing can go stale, nothing has to be
 * invalidated, and memory use is proportional to what the page shows.
 */

defined( 'ABSPATH' ) || exit;

final class BogoExt_Translations {

	/**
	 * Post ID => cache key ("post_type|group").
	 *
	 * @var array
	 */
	private static $post_key = array();

	/**
	 * Cache key => [ locale => [ id, status, type ] ].
	 *
	 * @var array
	 */
	private static $groups = array();

	/**
	 * Loads groups for many posts at once.
	 *
	 * @param int[]|WP_Post[] $posts Posts or IDs.
	 */
	public static function prime( array $posts ) {
		global $wpdb;

		$ids = array();

		foreach ( $posts as $post ) {
			$id = $post instanceof WP_Post ? $post->ID : (int) $post;

			if ( $id > 0 && ! isset( self::$post_key[ $id ] ) ) {
				$ids[] = $id;
			}
		}

		$ids = array_values( array_unique( $ids ) );

		if ( ! $ids ) {
			return;
		}

		// Warms the post + meta caches with one query each.
		_prime_post_caches( $ids, false, true );

		$keys_by_type = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( ! $post || ! bogo_is_localizable_post_type( $post->post_type ) ) {
				self::$post_key[ $id ] = '';
				continue;
			}

			$group = (string) get_post_meta( $id, '_original_post', true );

			if ( '' === $group ) {
				$group = (string) $id; // Back-compat, same as Bogo core.
			}

			self::$post_key[ $id ] = $post->post_type . '|' . $group;

			if ( ! isset( self::$groups[ self::$post_key[ $id ] ] ) ) {
				$keys_by_type[ $post->post_type ][ $group ] = true;
			}
		}

		foreach ( $keys_by_type as $post_type => $groups ) {
			$groups = array_map( 'strval', array_keys( $groups ) );

			foreach ( array_chunk( $groups, 500 ) as $chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );

				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$sql = $wpdb->prepare(
					"SELECT o.post_id, o.meta_value AS grp, l.meta_value AS locale, p.post_status
					FROM {$wpdb->postmeta} AS o
					INNER JOIN {$wpdb->posts} AS p ON p.ID = o.post_id
					LEFT JOIN {$wpdb->postmeta} AS l ON l.post_id = o.post_id AND l.meta_key = '_locale'
					WHERE o.meta_key = '_original_post'
					AND o.meta_value IN ( $placeholders )
					AND p.post_type = %s
					AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
					ORDER BY p.ID ASC",
					array_merge( $chunk, array( $post_type ) )
				);

				$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				foreach ( $chunk as $group ) {
					self::$groups[ $post_type . '|' . $group ] = array();
				}

				foreach ( (array) $rows as $row ) {
					self::add_member( $post_type . '|' . $row->grp, (int) $row->post_id, (string) $row->locale, $row->post_status, $post_type );
				}

				// Back-compat: the "group" may be a plain post ID of a post
				// that has no `_original_post` meta itself.
				foreach ( $chunk as $group ) {
					if ( ctype_digit( $group ) ) {
						$post = get_post( (int) $group );

						if ( $post && $post->post_type === $post_type && ! in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
							self::add_member( $post_type . '|' . $group, $post->ID, (string) get_post_meta( $post->ID, '_locale', true ), $post->post_status, $post_type );
						}
					}
				}
			}
		}

		// Posts that are not in their own group yet (no meta at all).
		foreach ( $ids as $id ) {
			$key = self::$post_key[ $id ];

			if ( '' === $key ) {
				continue;
			}

			$post = get_post( $id );
			self::add_member( $key, $id, (string) get_post_meta( $id, '_locale', true ), $post->post_status, $post->post_type );
		}
	}

	/**
	 * Adds a post to a group (first post per locale wins, published preferred).
	 */
	private static function add_member( $key, $id, $locale, $status, $type ) {
		if ( '' === $locale ) {
			$locale = bogo_get_default_locale();
		}

		if ( ! bogo_is_available_locale( $locale ) ) {
			return;
		}

		if ( ! isset( self::$groups[ $key ] ) ) {
			self::$groups[ $key ] = array();
		}

		$existing = self::$groups[ $key ][ $locale ] ?? null;

		if ( $existing ) {
			if ( $existing['id'] === $id ) {
				return;
			}

			// Keep the first one unless the new one is published and the old one is not.
			if ( 'publish' === $existing['status'] || 'publish' !== $status ) {
				return;
			}
		}

		self::$groups[ $key ][ $locale ] = array(
			'id'     => $id,
			'status' => $status,
			'type'   => $type,
		);
	}

	/**
	 * Returns the translation group of a post, including the post itself.
	 *
	 * @param int|WP_Post $post Post.
	 * @return array locale => [ id, status, type ]
	 */
	public static function get_group( $post ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;

		if ( $id <= 0 ) {
			return array();
		}

		if ( ! isset( self::$post_key[ $id ] ) ) {
			self::prime( array( $id ) );
		}

		$key = self::$post_key[ $id ] ?? '';

		return '' === $key ? array() : ( self::$groups[ $key ] ?? array() );
	}

	/**
	 * Forgets cached data (e.g. after creating a translation).
	 *
	 * @param int|null $post_id Post ID or null for everything.
	 */
	public static function flush( $post_id = null ) {
		if ( null === $post_id ) {
			self::$post_key = array();
			self::$groups   = array();
			return;
		}

		$key = self::$post_key[ $post_id ] ?? '';

		foreach ( self::$post_key as $id => $k ) {
			if ( $k === $key ) {
				unset( self::$post_key[ $id ] );
			}
		}

		unset( self::$groups[ $key ], self::$post_key[ $post_id ] );
	}
}

add_action( 'save_post', static function ( $post_id ) {
	BogoExt_Translations::flush( (int) $post_id );
}, 99 );

add_action( 'deleted_post', static function ( $post_id ) {
	BogoExt_Translations::flush( (int) $post_id );
} );
