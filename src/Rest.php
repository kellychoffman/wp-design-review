<?php
namespace WpDesignReview;

defined( 'ABSPATH' ) || exit;

/**
 * Two audiences: editors (cookie + nonce, capability checked) and review
 * visitors (the link token, no account).
 */
final class Rest {

	const NS = 'wp-design-review/v1';

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route( self::NS, '/looks', array(
			'methods'             => 'GET',
			'callback'            => static fn() => array( 'looks' => Reviews::looks() ),
			'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
		) );

		register_rest_route( self::NS, '/reviews', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'list_reviews' ),
				'permission_callback' => array( self::class, 'can_edit_target' ),
				'args'                => array( 'post_id' => array( 'type' => 'integer', 'required' => true ) ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'create_review' ),
				'permission_callback' => array( self::class, 'can_edit_target' ),
				'args'                => array(
					'post_id'     => array( 'type' => 'integer', 'required' => true ),
					'look'        => array( 'type' => 'string', 'default' => '' ),
					'days'        => array( 'type' => 'integer', 'default' => 7 ),
					'can'         => array( 'type' => 'string', 'default' => 'comment' ),
					'password'    => array( 'type' => 'string', 'default' => '' ),
					'unpublished' => array( 'type' => 'boolean', 'default' => false ),
				),
			),
		) );

		register_rest_route( self::NS, '/reviews/(?P<id>\d+)/revoke', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'revoke_review' ),
			'permission_callback' => array( self::class, 'can_manage_review' ),
		) );

		// Visitor endpoints, authorized by the review token inside the callback.
		register_rest_route( self::NS, '/feedback', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'post_feedback' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/feedback/status', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'feedback_status' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/approve', array(
			'methods'             => 'POST',
			'callback'            => array( self::class, 'approve' ),
			'permission_callback' => '__return_true',
		) );
	}

	/* ---- editor side ------------------------------------------------- */

	public static function can_edit_target( \WP_REST_Request $req ) {
		return current_user_can( 'edit_post', (int) $req['post_id'] );
	}

	public static function can_manage_review( \WP_REST_Request $req ) {
		$r = Reviews::get( (int) $req['id'] );
		if ( ! $r ) {
			return false;
		}
		return 'site' === $r['scope'] ? current_user_can( 'edit_theme_options' ) : current_user_can( 'edit_post', $r['target'] );
	}

	private static function present( array $r ): array {
		$feedback = Feedback::for_review( $r['id'] );
		return array(
			'id'        => $r['id'],
			'label'     => $r['label'],
			'status'    => Reviews::status( $r ),
			'scope'     => $r['scope'],
			'look'      => $r['look'],
			'can'       => $r['can'],
			'expiry'    => Reviews::expiry_label( $r ),
			'url'       => Reviews::url( $r ),
			'views'     => $r['views'],
			'password'  => (bool) $r['password'],
			'approvals' => count( $r['approvals'] ),
			'open'      => count( array_filter( $feedback, static fn( $f ) => ! $f['resolved'] ) ),
			'comments'  => count( $feedback ),
		);
	}

	public static function list_reviews( \WP_REST_Request $req ) {
		return array_map( array( self::class, 'present' ), Reviews::for_post( (int) $req['post_id'] ) );
	}

	public static function create_review( \WP_REST_Request $req ) {
		$r = Reviews::create(
			array(
				'scope'       => 'page',
				'target'      => (int) $req['post_id'],
				'look'        => (string) $req['look'],
				'days'        => (int) $req['days'],
				'can'         => (string) $req['can'],
				'password'    => (string) $req['password'],
				'unpublished' => (bool) $req['unpublished'],
			)
		);
		return is_wp_error( $r ) ? $r : self::present( $r );
	}

	public static function revoke_review( \WP_REST_Request $req ) {
		Reviews::revoke( (int) $req['id'] );
		return self::present( Reviews::get( (int) $req['id'] ) );
	}

	/* ---- visitor side ------------------------------------------------ */

	/** @return array|\WP_Error The review if this request may act on it. */
	private static function authorize( \WP_REST_Request $req, string $need ) {
		$deny   = new \WP_Error( 'wpdr_denied', __( 'This review link is not available.', 'wp-design-review' ), array( 'status' => 403 ) );
		$review = Reviews::find_by_token( (string) $req->get_header( 'x-wpdr-token' ) );
		if ( ! $review || 'active' !== Reviews::status( $review ) ) {
			return $deny;
		}
		if ( $review['password'] && ! Frontend::password_ok( $review ) ) {
			return $deny;
		}
		$rank = array( 'view' => 0, 'comment' => 1, 'approve' => 2 );
		if ( $rank[ $review['can'] ] < $rank[ $need ] ) {
			return $deny;
		}
		return $review;
	}

	public static function post_feedback( \WP_REST_Request $req ) {
		$review = self::authorize( $req, 'comment' );
		if ( is_wp_error( $review ) ) {
			return $review;
		}
		$f = Feedback::submit( $review, (array) $req->get_json_params() );
		if ( is_wp_error( $f ) ) {
			return $f;
		}
		return array( 'id' => $f['id'], 'linked' => (bool) $f['note'] );
	}

	/** Lets a visitor see which of their own comments were addressed. */
	public static function feedback_status( \WP_REST_Request $req ) {
		$review = self::authorize( $req, 'view' );
		if ( is_wp_error( $review ) ) {
			return $review;
		}
		$ids = array_slice( array_map( 'intval', (array) ( $req->get_json_params()['ids'] ?? array() ) ), 0, 100 );
		$out = array();
		foreach ( $ids as $id ) {
			$f = Feedback::get( $id );
			if ( $f && $f['review'] === $review['id'] ) {
				$out[ $id ] = array( 'resolved' => $f['resolved'] );
			}
		}
		return $out;
	}

	public static function approve( \WP_REST_Request $req ) {
		$review = self::authorize( $req, 'approve' );
		if ( is_wp_error( $review ) ) {
			return $review;
		}
		$name = mb_substr( trim( sanitize_text_field( (string) ( $req->get_json_params()['name'] ?? '' ) ) ), 0, 60 );
		$name = '' === $name ? __( 'Reviewer', 'wp-design-review' ) : $name;

		$list   = $review['approvals'];
		$list[] = array( 'name' => $name, 'time' => time() );
		update_post_meta( $review['id'], '_dr_approvals', $list );

		$user = get_userdata( $review['creator'] );
		if ( $user ) {
			/* translators: 1: reviewer name, 2: review label */
			wp_mail( $user->user_email, sprintf( __( '%1$s approved "%2$s"', 'wp-design-review' ), $name, $review['label'] ), __( 'They said it looks good to them. Publishing is still up to you.', 'wp-design-review' ) . "\n\n" . admin_url( 'admin.php?page=wp-design-review' ) );
		}
		return array( 'approvals' => count( $list ) );
	}
}
