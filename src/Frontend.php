<?php
namespace WpDesignReview;

defined( 'ABSPATH' ) || exit;

/**
 * Handles requests that carry a review link: gates them, applies the pending
 * look for that request only, and adds the thin review bar.
 */
final class Frontend {

	const COOKIE = 'wpdr_review';

	/** @var array|null Review in play for this request (in scope only after resolve_scope). */
	private static ?array $review = null;
	private static bool $apply_look = false;
	private static string $mode = 'active'; // active | compare | live
	private static array $block_map = array();
	private static array $block_seen = array();
	private static bool $in_content = false;

	public static function init(): void {
		add_action( 'init', array( self::class, 'detect' ), 20 );
	}

	public static function is_review_request(): bool {
		return null !== self::$review;
	}

	/* ---------------------------------------------------------------------
	 * Detect and gate
	 * ------------------------------------------------------------------ */

	public static function detect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$from_query = isset( $_GET['dr_review'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$token      = $from_query ? sanitize_text_field( wp_unslash( $_GET['dr_review'] ) ) : ( isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $token ) {
			return;
		}

		$review = Reviews::find_by_token( $token );
		$status = $review ? Reviews::status( $review ) : 'unavailable';

		if ( ! $review || ! in_array( $status, array( 'active', 'live' ), true ) ) {
			if ( $from_query ) {
				self::end_page( $status );
			}
			// A stale cookie just means normal browsing.
			self::forget_cookie();
			return;
		}

		if ( $review['password'] && ! self::password_ok( $review ) ) {
			if ( $from_query ) {
				self::password_page( $review );
			}
			return;
		}

		if ( $from_query ) {
			self::remember_cookie( $review );
			Reviews::record_view( $review );
		}

		self::$review = $review;
		self::$mode   = 'live' === $status ? 'live' : ( isset( $_GET['dr_compare'] ) ? 'compare' : 'active' ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'active' === self::$mode ) {
			add_filter( 'wp_theme_json_data_user', array( self::class, 'filter_styles' ) );
			if ( $review['unpublished'] ) {
				add_filter( 'posts_pre_query', array( self::class, 'show_unpublished_target' ), 10, 2 );
				// A draft has no canonical address to bounce to.
				add_filter( 'redirect_canonical', '__return_false' );
			}
		}
		add_action( 'wp', array( self::class, 'resolve_scope' ), 1 );
	}

	public static function password_ok( array $review ): bool {
		$cookie = isset( $_COOKIE[ 'wpdr_ok_' . $review['id'] ] ) ? (string) wp_unslash( $_COOKIE[ 'wpdr_ok_' . $review['id'] ] ) : ''; // phpcs:ignore
		return '' !== $cookie && hash_equals( self::ok_value( $review ), $cookie );
	}

	private static function ok_value( array $review ): string {
		return hash_hmac( 'sha256', $review['id'] . '|' . $review['token'] . '|' . $review['password'], wp_salt( 'auth' ) );
	}

	private static function cookie( string $name, string $value, int $expires ): void {
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			$name,
			$value,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	private static function remember_cookie( array $review ): void {
		$exp = 'live' === Reviews::status( $review ) ? time() + DAY_IN_SECONDS : $review['expires'];
		self::cookie( self::COOKIE, $review['token'], $exp );
		$_COOKIE[ self::COOKIE ] = $review['token'];
	}

	private static function forget_cookie(): void {
		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			self::cookie( self::COOKIE, '', time() - YEAR_IN_SECONDS );
		}
	}

	/* ---------------------------------------------------------------------
	 * Scope and the pending look
	 * ------------------------------------------------------------------ */

	public static function resolve_scope(): void {
		$r = self::$review;
		if ( ! $r ) {
			return;
		}
		$in_scope = true;
		if ( 'page' === $r['scope'] ) {
			$in_scope = is_singular() && get_queried_object_id() === $r['target'];
		}
		if ( is_feed() || is_robots() || is_favicon() ) {
			$in_scope = false;
		}
		if ( ! $in_scope ) {
			self::$review = null;
			if ( self::$apply_look ) {
				self::$apply_look = false;
				wp_clean_theme_json_cache();
			}
			return;
		}

		if ( 'active' === self::$mode && $r['look'] ) {
			self::$apply_look = true;
			// Anything computed before we knew the scope is stale.
			wp_clean_theme_json_cache();
		}

		// Trust and hygiene: never cached, never indexed, no editor traces.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Cache-Control: private, no-store, max-age=0' );
			header( 'X-Robots-Tag: noindex, nofollow' );
			header( 'Referrer-Policy: same-origin' );
		}
		add_filter( 'show_admin_bar', '__return_false' );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'rsd_link' );

		add_action( 'wp_head', array( self::class, 'print_head_style' ), 99 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_bar' ) );

		if ( is_singular() && 'live' !== self::$mode ) {
			self::prepare_block_tags( get_queried_object() );
		}
	}

	/** Swap the pending look into the site's styles, for this request only. */
	public static function filter_styles( $theme_json ) {
		if ( ! self::$apply_look || ! self::$review || ! self::$review['look'] ) {
			return $theme_json;
		}
		$look = Reviews::look_data( self::$review['look'] );
		if ( null === $look ) {
			return $theme_json;
		}
		return $theme_json->update_with(
			array(
				'version'  => $look['version'] ?? \WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => $look['settings'] ?? new \stdClass(),
				'styles'   => $look['styles'] ?? new \stdClass(),
			)
		);
	}

	/**
	 * Let the shared page render even though it is a draft, if the owner said
	 * so for this link. Only that one post, only for that link.
	 */
	public static function show_unpublished_target( $posts, \WP_Query $query ) {
		$r = self::$review;
		if ( null !== $posts || ! $r || ! $r['unpublished'] || 'page' !== $r['scope'] || ! $query->is_main_query() ) {
			return $posts;
		}
		$post = get_post( $r['target'] );
		if ( ! $post ) {
			return $posts;
		}
		$asked = (int) ( $query->get( 'p' ) ?: $query->get( 'page_id' ) );
		$named = (string) ( $query->get( 'pagename' ) ?: $query->get( 'name' ) );
		if ( $asked !== $r['target'] && ( '' === $named || $named !== $post->post_name ) ) {
			return $posts;
		}
		if ( 'publish' === $post->post_status || 'trash' === $post->post_status ) {
			return $posts;
		}
		// Present it as published for this request so themes render it normally.
		$copy              = clone $post;
		$copy->post_status = 'publish';
		wp_cache_set( $copy->ID, $copy, 'posts' );
		$query->found_posts   = 1;
		$query->max_num_pages = 1;
		return array( $copy );
	}

	/* ---------------------------------------------------------------------
	 * Block tagging, so a tap can find its block
	 * ------------------------------------------------------------------ */

	private static function prepare_block_tags( $post ): void {
		if ( ! $post instanceof \WP_Post || ! self::can( 'comment' ) ) {
			return;
		}
		self::$block_map = array();
		self::index_blocks( parse_blocks( $post->post_content ), array() );
		add_filter( 'the_content', function ( $c ) use ( $post ) {
			self::$in_content = ( get_the_ID() === $post->ID );
			return $c;
		}, 8 );
		add_filter( 'the_content', function ( $c ) {
			self::$in_content = false;
			return $c;
		}, 11 );
		add_filter( 'render_block', array( self::class, 'tag_block' ), 20, 2 );
	}

	private static function block_key( array $b ): string {
		return md5( $b['blockName'] . wp_json_encode( $b['attrs'] ) . wp_json_encode( $b['innerContent'] ) );
	}

	private static function index_blocks( array $blocks, array $prefix ): void {
		foreach ( $blocks as $i => $b ) {
			$path = array_merge( $prefix, array( $i ) );
			if ( ! empty( $b['blockName'] ) ) {
				self::$block_map[ self::block_key( $b ) ][] = implode( '.', $path );
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				self::index_blocks( $b['innerBlocks'], $path );
			}
		}
	}

	public static function tag_block( $html, $block ) {
		if ( ! self::$in_content || '' === trim( (string) $html ) || empty( $block['blockName'] ) ) {
			return $html;
		}
		$key   = self::block_key( $block );
		$paths = self::$block_map[ $key ] ?? array();
		$n     = self::$block_seen[ $key ] ?? 0;
		if ( ! isset( $paths[ $n ] ) ) {
			return $html;
		}
		self::$block_seen[ $key ] = $n + 1;
		$p                        = new \WP_HTML_Tag_Processor( $html );
		if ( $p->next_tag() ) {
			$p->set_attribute( 'data-dr-block', $paths[ $n ] );
			return $p->get_updated_html();
		}
		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Review bar
	 * ------------------------------------------------------------------ */

	public static function can( string $need ): bool {
		$r = self::$review;
		if ( ! $r ) {
			return false;
		}
		$rank = array( 'view' => 0, 'comment' => 1, 'approve' => 2 );
		return $rank[ $r['can'] ] >= $rank[ $need ];
	}

	public static function print_head_style(): void {
		echo '<style id="wpdr-offset">html{padding-top:52px !important}html.wpdr-picking *{cursor:crosshair !important}</style>' . "\n";
	}

	public static function enqueue_bar(): void {
		$r = self::$review;
		wp_enqueue_script( 'wpdr-bar', WPDR_URL . 'assets/review-bar.js', array(), WPDR_VERSION . '.' . filemtime( dirname( WPDR_FILE ) . '/assets/review-bar.js' ), true );

		$post = is_singular() ? get_queried_object() : null;
		$data = array(
			'rest'          => esc_url_raw( rest_url( 'wp-design-review/v1' ) ),
			'token'         => $r['token'],
			'reviewId'      => $r['id'],
			'mode'          => self::$mode,
			'postId'        => $post instanceof \WP_Post ? $post->ID : 0,
			'canComment'    => self::can( 'comment' ) && 'live' !== self::$mode,
			'canApprove'    => self::can( 'approve' ) && 'live' !== self::$mode,
			'canCompare'    => (bool) ( $r['look'] && ! $r['unpublished'] && 'live' !== self::$mode ),
			'unpublished'   => (bool) $r['unpublished'],
			'expiry'        => Reviews::expiry_label( $r ),
			'siteUrl'       => home_url( '/' ),
			'compareUrl'    => add_query_arg( 'dr_compare', '1' ),
			'reviewUrl'     => remove_query_arg( array( 'dr_compare', 'dr_review' ) ),
			'brand'         => Reviews::brand(),
			'approvedCount' => count( $r['approvals'] ),
		);
		wp_add_inline_script( 'wpdr-bar', 'window.WPDR=' . wp_json_encode( $data ) . ';', 'before' );
	}

	/* ---------------------------------------------------------------------
	 * Stand-alone pages
	 * ------------------------------------------------------------------ */

	private static function shell( string $title, string $body, int $code = 200 ): void {
		status_header( $code );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		$brand  = Reviews::brand();
		$accent = esc_attr( $brand['accent'] );
		echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html( $title ) . '</title>';
		echo '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f4ef;color:#1d1d1f;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}@media(prefers-color-scheme:dark){body{background:#141416;color:#f0f0f2}.card{background:#1e1e22 !important;border-color:#2e2e34 !important}}.card{max-width:420px;margin:24px;padding:32px;border:1px solid #e3dfd6;border-radius:16px;background:#fff;text-align:center}h1{font:600 24px/1.2 Georgia,serif;margin:0 0 8px}p{margin:0 0 16px;opacity:.75}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #c9c5bb;border-radius:10px;font:inherit;margin-bottom:12px}button,.btn{display:inline-block;width:100%;box-sizing:border-box;padding:12px;border:0;border-radius:10px;background:' . $accent . ';color:#fff;font:600 16px inherit;text-decoration:none;cursor:pointer}.brand{font-size:12px;letter-spacing:.1em;text-transform:uppercase;opacity:.6;margin-bottom:16px}.err{color:#b3261e;opacity:1}</style></head><body><div class="card"><div class="brand">' . esc_html( $brand['name'] ) . '</div>' . $body . '</div></body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/** Expired, revoked, live-and-retired, or simply not a link we know. Reveals nothing. */
	private static function end_page( string $status ): void {
		$brand = Reviews::brand();
		/* translators: %s: site or agency name */
		$hint = sprintf( __( 'Ask %s for a new link.', 'wp-design-review' ), $brand['name'] );
		if ( 'ended' === $status ) {
			self::shell(
				__( 'This redesign is live', 'wp-design-review' ),
				'<h1>' . esc_html__( 'This redesign is live.', 'wp-design-review' ) . '</h1><p>' . esc_html__( 'This review has finished.', 'wp-design-review' ) . '</p><a class="btn" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Visit the site', 'wp-design-review' ) . '</a>',
				410
			);
		}
		self::shell(
			__( 'This review has ended', 'wp-design-review' ),
			'<h1>' . esc_html__( 'This review has ended.', 'wp-design-review' ) . '</h1><p>' . esc_html( $hint ) . '</p>',
			410
		);
	}

	private static function password_page( array $review ): void {
		$error = '';
		$key   = 'wpdr_pw_' . $review['id'] . '_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['wpdr_pw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$tries = (int) get_transient( $key );
			if ( $tries >= 10 ) {
				$error = __( 'Too many tries. Please wait a few minutes.', 'wp-design-review' );
			} elseif ( wp_check_password( (string) wp_unslash( $_POST['wpdr_pw'] ), $review['password'] ) ) { // phpcs:ignore
				delete_transient( $key );
				self::cookie( 'wpdr_ok_' . $review['id'], self::ok_value( $review ), $review['expires'] );
				wp_safe_redirect( add_query_arg( 'dr_review', $review['token'], remove_query_arg( 'dr_review' ) ) );
				exit;
			} else {
				set_transient( $key, $tries + 1, 15 * MINUTE_IN_SECONDS );
				$error = __( 'That password is not right.', 'wp-design-review' );
			}
		}
		$body  = '<h1>' . esc_html__( 'Enter the password', 'wp-design-review' ) . '</h1><p>' . esc_html__( 'This review is protected.', 'wp-design-review' ) . '</p>';
		$body .= $error ? '<p class="err">' . esc_html( $error ) . '</p>' : '';
		$body .= '<form method="post"><input type="password" name="wpdr_pw" autocomplete="off" autofocus required><button type="submit">' . esc_html__( 'View the redesign', 'wp-design-review' ) . '</button></form>';
		self::shell( __( 'Enter the password', 'wp-design-review' ), $body );
	}
}
