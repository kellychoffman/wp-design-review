<?php
namespace WpDesignReview;

defined( 'ABSPATH' ) || exit;

/**
 * The Design Reviews screen (all links, feedback, branding) and the editor
 * sidebar panel assets.
 */
final class Admin {

	const SLUG = 'wp-design-review';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_assets' ) );
		foreach ( array( 'create_site', 'revoke', 'delete', 'publish_look', 'resolve', 'save_brand' ) as $action ) {
			add_action( 'admin_post_wpdr_' . $action, array( self::class, 'handle_' . $action ) );
		}
	}

	public static function menu(): void {
		add_menu_page(
			__( 'Design Reviews', 'wp-design-review' ),
			__( 'Design Reviews', 'wp-design-review' ),
			'edit_pages',
			self::SLUG,
			array( self::class, 'render' ),
			'dashicons-visibility',
			58
		);
	}

	public static function editor_assets(): void {
		wp_enqueue_script(
			'wpdr-editor',
			WPDR_URL . 'assets/editor.js',
			array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
			WPDR_VERSION . '.' . filemtime( dirname( WPDR_FILE ) . '/assets/editor.js' ),
			true
		);
		wp_add_inline_script( 'wpdr-editor', 'window.WPDR_EDITOR=' . wp_json_encode( array( 'looks' => Reviews::looks() ) ) . ';', 'before' );
	}

	/* ---- actions ----------------------------------------------------- */

	private static function back( string $notice, string $type = 'success' ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'wpdr_notice' => rawurlencode( $notice ), 'wpdr_type' => $type ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function guard( string $nonce_action, string $cap = 'edit_pages' ): void {
		check_admin_referer( $nonce_action );
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-design-review' ), 403 );
		}
	}

	private static function review_from_request(): array {
		$r = Reviews::get( (int) ( $_REQUEST['review'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $r ) {
			self::back( __( 'That review no longer exists.', 'wp-design-review' ), 'error' );
		}
		$cap_ok = 'site' === $r['scope'] ? current_user_can( 'edit_theme_options' ) : current_user_can( 'edit_post', $r['target'] );
		if ( ! $cap_ok ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-design-review' ), 403 );
		}
		return $r;
	}

	public static function handle_create_site(): void {
		self::guard( 'wpdr_create_site', 'edit_theme_options' );
		$r = Reviews::create(
			array(
				'scope'    => 'site',
				'look'     => sanitize_text_field( wp_unslash( $_POST['look'] ?? '' ) ),
				'days'     => (int) ( $_POST['days'] ?? 7 ),
				'can'      => sanitize_key( $_POST['can'] ?? 'comment' ),
				'password' => (string) wp_unslash( $_POST['password'] ?? '' ),
			)
		);
		is_wp_error( $r ) ? self::back( $r->get_error_message(), 'error' ) : self::back( __( 'Review link created.', 'wp-design-review' ) );
	}

	public static function handle_revoke(): void {
		self::guard( 'wpdr_revoke' );
		Reviews::revoke( self::review_from_request()['id'] );
		self::back( __( 'Link revoked. It stopped working immediately.', 'wp-design-review' ) );
	}

	public static function handle_delete(): void {
		self::guard( 'wpdr_delete' );
		Reviews::delete( self::review_from_request()['id'] );
		self::back( __( 'Review and its feedback log deleted.', 'wp-design-review' ) );
	}

	public static function handle_publish_look(): void {
		self::guard( 'wpdr_publish_look', 'edit_theme_options' );
		$r   = self::review_from_request();
		$res = Reviews::publish_look( $r['look'] );
		is_wp_error( $res ) ? self::back( $res->get_error_message(), 'error' ) : self::back( __( 'Look published. Review links for it now say "This redesign is live."', 'wp-design-review' ) );
	}

	public static function handle_resolve(): void {
		self::guard( 'wpdr_resolve' );
		$f = Feedback::get( (int) ( $_POST['feedback'] ?? 0 ) );
		if ( $f ) {
			$r = Reviews::get( $f['review'] );
			if ( $r && ( 'site' === $r['scope'] ? current_user_can( 'edit_theme_options' ) : current_user_can( 'edit_post', $r['target'] ) ) ) {
				Feedback::resolve( $f['id'] );
			}
		}
		self::back( __( 'Marked as addressed. The reviewer will see that.', 'wp-design-review' ) );
	}

	public static function handle_save_brand(): void {
		self::guard( 'wpdr_save_brand', 'manage_options' );
		$accent = sanitize_hex_color( wp_unslash( $_POST['accent'] ?? '' ) );
		update_option(
			'wpdr_brand',
			array(
				'name'   => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
				'logo'   => esc_url_raw( wp_unslash( $_POST['logo'] ?? '' ) ),
				'accent' => $accent ? $accent : '',
			)
		);
		self::back( __( 'Branding saved.', 'wp-design-review' ) );
	}

	/* ---- screen ------------------------------------------------------ */

	private static function post_button( string $action, int $review, string $label, string $class = 'button', string $confirm = '' ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline"' . ( $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="wpdr_' . esc_attr( $action ) . '"><input type="hidden" name="review" value="' . (int) $review . '">';
		wp_nonce_field( 'wpdr_' . $action );
		echo '<button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form> ';
	}

	public static function render(): void {
		$notice = isset( $_GET['wpdr_notice'] ) ? sanitize_text_field( wp_unslash( rawurldecode( $_GET['wpdr_notice'] ) ) ) : ''; // phpcs:ignore
		$type   = ( $_GET['wpdr_type'] ?? '' ) === 'error' ? 'error' : 'success'; // phpcs:ignore
		$looks  = Reviews::looks();
		$brand  = get_option( 'wpdr_brand', array() );
		$pills  = array( 'active' => '#1f7a4d', 'live' => '#3858e9', 'expired' => '#6b6b70', 'ended' => '#6b6b70', 'revoked' => '#b3261e' );

		echo '<div class="wrap"><h1>' . esc_html__( 'Design Reviews', 'wp-design-review' ) . '</h1>';
		echo '<p>' . esc_html__( 'Share the look, not the editor. To share a page, open it in the editor and use the Share review panel. To share a whole-site look, create a link below.', 'wp-design-review' ) . '</p>';
		if ( $notice ) {
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
		}

		// New whole-site review.
		if ( current_user_can( 'edit_theme_options' ) ) {
			echo '<h2>' . esc_html__( 'Share a whole-site look', 'wp-design-review' ) . '</h2>';
			if ( ! $looks ) {
				echo '<p><em>' . esc_html__( 'The active theme has no style variations to preview.', 'wp-design-review' ) . '</em></p>';
			} else {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="wpdr_create_site">';
				wp_nonce_field( 'wpdr_create_site' );
				echo '<table class="form-table"><tr><th>' . esc_html__( 'Look', 'wp-design-review' ) . '</th><td><select name="look">';
				foreach ( $looks as $l ) {
					echo '<option>' . esc_html( $l ) . '</option>';
				}
				echo '</select></td></tr><tr><th>' . esc_html__( 'Expires in', 'wp-design-review' ) . '</th><td><select name="days">';
				foreach ( array( 1, 3, 7, 14, 30 ) as $d ) {
					/* translators: %d: number of days */
					echo '<option value="' . (int) $d . '"' . selected( 7, $d, false ) . '>' . esc_html( sprintf( _n( '%d day', '%d days', $d, 'wp-design-review' ), $d ) ) . '</option>';
				}
				echo '</select></td></tr><tr><th>' . esc_html__( 'Visitors can', 'wp-design-review' ) . '</th><td><select name="can"><option value="view">' . esc_html__( 'View only', 'wp-design-review' ) . '</option><option value="comment" selected>' . esc_html__( 'Comment', 'wp-design-review' ) . '</option><option value="approve">' . esc_html__( 'Comment and approve', 'wp-design-review' ) . '</option></select></td></tr>';
				echo '<tr><th>' . esc_html__( 'Password', 'wp-design-review' ) . '</th><td><input type="text" name="password" class="regular-text" autocomplete="off" placeholder="' . esc_attr__( 'Optional', 'wp-design-review' ) . '"></td></tr></table>';
				echo '<p><button class="button button-primary">' . esc_html__( 'Create review link', 'wp-design-review' ) . '</button></p></form>';
			}
		}

		// All reviews.
		echo '<h2>' . esc_html__( 'Review links', 'wp-design-review' ) . '</h2>';
		$reviews = Reviews::all();
		if ( ! $reviews ) {
			echo '<p>' . esc_html__( 'No review links yet.', 'wp-design-review' ) . '</p>';
		}
		foreach ( $reviews as $r ) {
			$status   = Reviews::status( $r );
			$feedback = Feedback::for_review( $r['id'] );
			$open     = count( array_filter( $feedback, static fn( $f ) => ! $f['resolved'] ) );
			$scope    = 'site' === $r['scope'] ? __( 'Whole site', 'wp-design-review' ) : ( get_the_title( $r['target'] ) ?: __( '(page removed)', 'wp-design-review' ) );

			echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px 16px;margin:0 0 12px;max-width:900px">';
			echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><strong style="font-size:15px">' . esc_html( $r['label'] ) . '</strong>';
			echo '<span style="background:' . esc_attr( $pills[ $status ] ) . ';color:#fff;border-radius:999px;padding:1px 10px;font-size:12px">' . esc_html( ucfirst( $status ) ) . '</span>';
			echo '<span style="color:#646970">' . esc_html( $scope . ( $r['look'] ? ' · ' . $r['look'] : '' ) . ' · ' . Reviews::expiry_label( $r ) ) . '</span></div>';
			printf(
				'<p style="color:#646970;margin:6px 0">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: views, 2: open comments, 3: approvals */
						__( '%1$s · %2$s · %3$s', 'wp-design-review' ),
						/* translators: %d: number of times the link was opened */
						sprintf( _n( '%d open', '%d opens', $r['views'], 'wp-design-review' ), $r['views'] ),
						/* translators: %d: number of unresolved comments */
						sprintf( _n( '%d open comment', '%d open comments', $open, 'wp-design-review' ), $open ),
						/* translators: %d: number of approvals */
						sprintf( _n( '%d approval', '%d approvals', count( $r['approvals'] ), 'wp-design-review' ), count( $r['approvals'] ) )
					) . ( $r['unpublished'] ? ' · ' . __( 'shows unpublished copy', 'wp-design-review' ) : '' ) . ( $r['password'] ? ' · ' . __( 'password protected', 'wp-design-review' ) : '' )
				)
			);
			if ( 'active' === $status || 'live' === $status ) {
				echo '<input type="text" readonly class="large-text code" style="max-width:640px" value="' . esc_attr( Reviews::url( $r ) ) . '" onfocus="this.select()"> ';
			}
			echo '<p style="margin:8px 0 0">';
			if ( 'active' === $status ) {
				self::post_button( 'revoke', $r['id'], __( 'Revoke', 'wp-design-review' ), 'button', __( 'Revoke this link? It stops working immediately.', 'wp-design-review' ) );
				if ( $r['look'] && current_user_can( 'edit_theme_options' ) ) {
					self::post_button( 'publish_look', $r['id'], __( 'Publish this look', 'wp-design-review' ), 'button button-primary', __( 'Publish this look to the live site? It replaces the current site styles (kept in style revisions).', 'wp-design-review' ) );
				}
			}
			self::post_button( 'delete', $r['id'], __( 'Delete', 'wp-design-review' ), 'button-link-delete button-link', __( 'Delete this review and its feedback log?', 'wp-design-review' ) );
			echo '</p>';

			if ( $r['approvals'] ) {
				echo '<p style="margin:8px 0 0"><strong>' . esc_html__( 'Approved by:', 'wp-design-review' ) . '</strong> ';
				echo esc_html( implode( ', ', array_map( static fn( $a ) => $a['name'] . ' (' . wp_date( 'M j, g:i a', $a['time'] ) . ')', $r['approvals'] ) ) ) . '</p>';
			}

			if ( $feedback ) {
				echo '<details style="margin-top:10px"' . ( $open ? ' open' : '' ) . '><summary>' . esc_html( sprintf( /* translators: %d: comments */ _n( '%d comment', '%d comments', count( $feedback ), 'wp-design-review' ), count( $feedback ) ) ) . '</summary>';
				foreach ( $feedback as $f ) {
					echo '<div style="border-top:1px solid #dcdcde;padding:8px 0;display:flex;gap:12px;justify-content:space-between">';
					echo '<div><strong>' . esc_html( $f['name'] ) . '</strong> <span style="color:#646970">' . esc_html( $f['viewport'] . ( $f['mood'] ? ' · ' . $f['mood'] : '' ) . ( $f['compare'] ? ' · ' . __( 'compare was on', 'wp-design-review' ) : '' ) . ' · ' . human_time_diff( $f['date'] ) . ' ' . __( 'ago', 'wp-design-review' ) ) . '</span>';
					echo '<div>' . esc_html( $f['text'] ) . '</div>';
					if ( $f['snippet'] ) {
						echo '<div style="color:#646970;font-size:12px">' . esc_html__( 'On:', 'wp-design-review' ) . ' &ldquo;' . esc_html( $f['snippet'] ) . '&rdquo;</div>';
					}
					$edit = $f['post'] ? get_edit_post_link( $f['post'] ) : '';
					echo '<div style="font-size:12px">' . ( $f['note'] ? esc_html__( 'Added as a Note on the block.', 'wp-design-review' ) : esc_html__( 'Not tied to a block, so it is logged here only.', 'wp-design-review' ) ) . ( $edit ? ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Open in editor', 'wp-design-review' ) . '</a>' : '' ) . '</div></div>';
					echo '<div>';
					if ( $f['resolved'] ) {
						echo '<span style="color:#1f7a4d">' . esc_html__( 'Addressed', 'wp-design-review' ) . '</span>';
					} else {
						echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="wpdr_resolve"><input type="hidden" name="feedback" value="' . (int) $f['id'] . '">';
						wp_nonce_field( 'wpdr_resolve' );
						echo '<button class="button">' . esc_html__( 'Mark addressed', 'wp-design-review' ) . '</button></form>';
					}
					echo '</div></div>';
				}
				echo '</details>';
			}
			echo '</div>';
		}

		// Branding.
		if ( current_user_can( 'manage_options' ) ) {
			echo '<h2>' . esc_html__( 'Branding', 'wp-design-review' ) . '</h2><p>' . esc_html__( 'Your name, logo and color appear on the review bar. Expiry and status wording are fixed so reviewers can always trust them.', 'wp-design-review' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="wpdr_save_brand">';
			wp_nonce_field( 'wpdr_save_brand' );
			echo '<table class="form-table"><tr><th>' . esc_html__( 'Name', 'wp-design-review' ) . '</th><td><input name="name" class="regular-text" value="' . esc_attr( $brand['name'] ?? '' ) . '" placeholder="' . esc_attr( get_bloginfo( 'name' ) ) . '"></td></tr>';
			echo '<tr><th>' . esc_html__( 'Logo URL', 'wp-design-review' ) . '</th><td><input name="logo" class="regular-text" value="' . esc_attr( $brand['logo'] ?? '' ) . '" placeholder="https://"></td></tr>';
			echo '<tr><th>' . esc_html__( 'Accent color', 'wp-design-review' ) . '</th><td><input type="color" name="accent" value="' . esc_attr( ! empty( $brand['accent'] ) ? $brand['accent'] : '#3858e9' ) . '"></td></tr></table>';
			echo '<p><button class="button">' . esc_html__( 'Save branding', 'wp-design-review' ) . '</button></p></form>';
		}
		echo '</div>';
	}
}
