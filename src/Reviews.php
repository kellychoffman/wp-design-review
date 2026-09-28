<?php
namespace WpDesignReview;

defined( 'ABSPATH' ) || exit;

/**
 * Storage and lifecycle for review links.
 *
 * A review is a private post of type dr_review. The token is stored so the
 * owner can copy the link again later; it is never exposed outside the
 * capability-gated editor and admin screens.
 */
final class Reviews {

	const CPT      = 'dr_review';
	const FEEDBACK = 'dr_feedback';
	const GRACE    = 7 * DAY_IN_SECONDS; // How long a link says "now live" after publish.

	public static function register(): void {
		$args = array(
			'public'              => false,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => false,
			'supports'            => array( 'title' ),
		);
		register_post_type( self::CPT, $args );
		register_post_type( self::FEEDBACK, $args );

		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 10, 3 );
	}

	/**
	 * Create a review. Returns the normalized review or WP_Error.
	 *
	 * @param array $a scope, target, look, days, password, can, unpublished, label.
	 */
	public static function create( array $a ) {
		$scope  = 'site' === ( $a['scope'] ?? '' ) ? 'site' : 'page';
		$target = (int) ( $a['target'] ?? 0 );
		$look   = (string) ( $a['look'] ?? '' );
		$can    = in_array( $a['can'] ?? '', array( 'view', 'comment', 'approve' ), true ) ? $a['can'] : 'comment';
		$days   = max( 1, min( 30, (int) ( $a['days'] ?? 7 ) ) );

		if ( '' !== $look && null === self::look_data( $look ) ) {
			return new \WP_Error( 'wpdr_bad_look', __( 'That look no longer exists in this theme.', 'wp-design-review' ), array( 'status' => 400 ) );
		}

		$unpublished = ! empty( $a['unpublished'] );
		if ( 'page' === $scope ) {
			$post = $target ? get_post( $target ) : null;
			if ( ! $post ) {
				return new \WP_Error( 'wpdr_bad_post', __( 'Choose a page to share.', 'wp-design-review' ), array( 'status' => 400 ) );
			}
			// Drafts can only ever be shown by explicit opt-in.
			if ( 'publish' !== $post->post_status ) {
				$unpublished = true;
			}
			$label = $a['label'] ?? get_the_title( $post );
		} else {
			$target = 0;
			$unpublished = false;
			if ( '' === $look ) {
				return new \WP_Error( 'wpdr_need_look', __( 'A whole-site review needs a look to preview.', 'wp-design-review' ), array( 'status' => 400 ) );
			}
			$label = $a['label'] ?? $look;
		}

		$id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => wp_strip_all_tags( (string) $label ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$meta = array(
			'_dr_token'       => bin2hex( random_bytes( 16 ) ),
			'_dr_scope'       => $scope,
			'_dr_target'      => $target,
			'_dr_look'        => $look,
			'_dr_expires'     => time() + $days * DAY_IN_SECONDS,
			'_dr_password'    => ! empty( $a['password'] ) ? wp_hash_password( (string) $a['password'] ) : '',
			'_dr_can'         => $can,
			'_dr_unpublished' => $unpublished ? 1 : 0,
			'_dr_state'       => 'active',
			'_dr_views'       => 0,
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		return self::get( $id );
	}

	public static function get( int $id ): ?array {
		$p = get_post( $id );
		if ( ! $p || self::CPT !== $p->post_type ) {
			return null;
		}
		$m = static fn( string $k ) => get_post_meta( $id, $k, true );
		return array(
			'id'          => $id,
			'label'       => $p->post_title,
			'token'       => (string) $m( '_dr_token' ),
			'scope'       => (string) $m( '_dr_scope' ),
			'target'      => (int) $m( '_dr_target' ),
			'look'        => (string) $m( '_dr_look' ),
			'expires'     => (int) $m( '_dr_expires' ),
			'password'    => (string) $m( '_dr_password' ),
			'can'         => (string) $m( '_dr_can' ),
			'unpublished' => (bool) $m( '_dr_unpublished' ),
			'state'       => (string) $m( '_dr_state' ),
			'live_at'     => (int) $m( '_dr_live_at' ),
			'views'       => (int) $m( '_dr_views' ),
			'first_open'  => (int) $m( '_dr_first_open' ),
			'last_open'   => (int) $m( '_dr_last_open' ),
			'approvals'   => is_array( $m( '_dr_approvals' ) ) ? $m( '_dr_approvals' ) : array(),
			'creator'     => (int) $p->post_author,
			'created'     => strtotime( $p->post_date_gmt . ' UTC' ),
		);
	}

	public static function find_by_token( string $token ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}
		$ids = get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => '_dr_token', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $token, // phpcs:ignore WordPress.DB.SlowDBQuery
				'suppress_filters' => true,
			)
		);
		return $ids ? self::get( (int) $ids[0] ) : null;
	}

	/** @return array[] */
	public static function all( array $extra = array() ): array {
		$ids = get_posts(
			array_merge(
				array(
					'post_type'   => self::CPT,
					'post_status' => 'publish',
					'numberposts' => 200,
					'fields'      => 'ids',
					'orderby'     => 'date',
					'order'       => 'DESC',
				),
				$extra
			)
		);
		return array_values( array_filter( array_map( array( self::class, 'get' ), $ids ) ) );
	}

	public static function for_post( int $post_id ): array {
		return self::all(
			array(
				'meta_key'   => '_dr_target', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $post_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}

	/** One of: active, live, expired, ended, revoked. */
	public static function status( array $r ): string {
		if ( 'revoked' === $r['state'] ) {
			return 'revoked';
		}
		if ( 'live' === $r['state'] ) {
			return ( $r['live_at'] && time() > $r['live_at'] + self::GRACE ) ? 'ended' : 'live';
		}
		return time() > $r['expires'] ? 'expired' : 'active';
	}

	public static function url( array $r ): string {
		$base = home_url( '/' );
		if ( 'page' === $r['scope'] && $r['target'] ) {
			$link = get_permalink( $r['target'] );
			$base = $link ? $link : $base;
		}
		return add_query_arg( 'dr_review', $r['token'], $base );
	}

	public static function revoke( int $id ): void {
		update_post_meta( $id, '_dr_state', 'revoked' );
		update_post_meta( $id, '_dr_revoked_at', time() );
		update_post_meta( $id, '_dr_revoked_by', get_current_user_id() );
	}

	public static function delete( int $id ): void {
		foreach ( Feedback::for_review( $id ) as $f ) {
			wp_delete_post( $f['id'], true );
		}
		wp_delete_post( $id, true );
	}

	public static function mark_live( int $id ): void {
		update_post_meta( $id, '_dr_state', 'live' );
		update_post_meta( $id, '_dr_live_at', time() );
	}

	public static function record_view( array $r ): void {
		update_post_meta( $r['id'], '_dr_views', $r['views'] + 1 );
		update_post_meta( $r['id'], '_dr_last_open', time() );
		if ( ! $r['first_open'] ) {
			update_post_meta( $r['id'], '_dr_first_open', time() );
		}
	}

	/** When a shared page is published, every open link to it flips to "now live". */
	public static function on_transition( string $new, string $old, \WP_Post $post ): void {
		if ( 'publish' !== $new || 'publish' === $old || self::CPT === $post->post_type || self::FEEDBACK === $post->post_type ) {
			return;
		}
		foreach ( self::for_post( $post->ID ) as $r ) {
			if ( 'active' === self::status( $r ) ) {
				self::mark_live( $r['id'] );
			}
		}
	}

	/**
	 * The theme's style variations, one per title. Themes can ship a full
	 * variation and a colors-only one with the same name; the fuller one wins.
	 *
	 * @return array<string,array> Keyed by title.
	 */
	private static function variations(): array {
		if ( ! class_exists( '\WP_Theme_JSON_Resolver' ) ) {
			return array();
		}
		$out = array();
		foreach ( \WP_Theme_JSON_Resolver::get_style_variations() as $v ) {
			$title = $v['title'] ?? '';
			if ( '' === $title ) {
				continue;
			}
			if ( ! isset( $out[ $title ] ) || strlen( wp_json_encode( $v ) ) > strlen( wp_json_encode( $out[ $title ] ) ) ) {
				$out[ $title ] = $v;
			}
		}
		return $out;
	}

	/** Titles of the looks (style variations) the active theme ships. */
	public static function looks(): array {
		return array_keys( self::variations() );
	}

	/** Raw variation data for a look title, or null. */
	public static function look_data( string $title ): ?array {
		$all = self::variations();
		if ( ! isset( $all[ $title ] ) ) {
			return null;
		}
		$v = $all[ $title ];
		unset( $v['title'], $v['slug'] );
		return $v;
	}

	/** Save a look as the site's live styles. This is the "publish" for a look. */
	public static function publish_look( string $title ) {
		$v = self::look_data( $title );
		if ( null === $v ) {
			return new \WP_Error( 'wpdr_bad_look', __( 'That look no longer exists in this theme.', 'wp-design-review' ) );
		}
		$post = \WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
		if ( empty( $post['ID'] ) ) {
			return new \WP_Error( 'wpdr_no_styles', __( 'Could not open the site styles to update.', 'wp-design-review' ) );
		}
		$data = array(
			'version'                     => $v['version'] ?? \WP_Theme_JSON::LATEST_SCHEMA,
			'isGlobalStylesUserThemeJSON' => true,
			'settings'                    => $v['settings'] ?? new \stdClass(),
			'styles'                      => $v['styles'] ?? new \stdClass(),
		);
		$res  = wp_update_post( wp_slash( array( 'ID' => $post['ID'], 'post_content' => wp_json_encode( $data ) ) ), true );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		foreach ( self::all() as $r ) {
			if ( 'active' === self::status( $r ) && $r['look'] === $title ) {
				self::mark_live( $r['id'] );
			}
		}
		return true;
	}

	public static function expiry_label( array $r ): string {
		$status = self::status( $r );
		if ( 'live' === $status ) {
			return __( 'Now live', 'wp-design-review' );
		}
		$diff = $r['expires'] - time();
		if ( $diff < DAY_IN_SECONDS ) {
			/* translators: %s: time of day */
			return sprintf( __( 'Expires today at %s', 'wp-design-review' ), wp_date( 'g:i a', $r['expires'] ) );
		}
		if ( $diff < 6 * DAY_IN_SECONDS ) {
			/* translators: %s: weekday */
			return sprintf( __( 'Expires %s', 'wp-design-review' ), wp_date( 'l', $r['expires'] ) );
		}
		/* translators: %s: date */
		return sprintf( __( 'Expires %s', 'wp-design-review' ), wp_date( 'M j', $r['expires'] ) );
	}

	public static function brand(): array {
		$b = get_option( 'wpdr_brand', array() );
		return array(
			'name'   => ! empty( $b['name'] ) ? $b['name'] : get_bloginfo( 'name' ),
			'logo'   => $b['logo'] ?? '',
			'accent' => ! empty( $b['accent'] ) ? $b['accent'] : '#3858e9',
		);
	}
}
