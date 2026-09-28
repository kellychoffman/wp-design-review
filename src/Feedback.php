<?php
namespace WpDesignReview;

defined( 'ABSPATH' ) || exit;

/**
 * Visitor feedback. Every comment is logged as a dr_feedback record, and when
 * it points at a block it also becomes a real Note on that block.
 */
final class Feedback {

	const MOODS     = array( 'love', 'off', 'unreadable' );
	const VIEWPORTS = array( 'phone', 'tablet', 'desktop' );

	/** @return array|\WP_Error Normalized feedback on success. */
	public static function submit( array $review, array $in ) {
		$text = trim( sanitize_textarea_field( (string) ( $in['text'] ?? '' ) ) );
		if ( '' === $text || mb_strlen( $text ) > 1000 ) {
			return new \WP_Error( 'wpdr_bad_text', __( 'Write a short comment (up to 1000 characters).', 'wp-design-review' ), array( 'status' => 400 ) );
		}
		$post = get_post( (int) ( $in['post_id'] ?? 0 ) );
		if ( ! $post || ! self::visible_to_review( $review, $post ) ) {
			return new \WP_Error( 'wpdr_bad_post', __( 'That page is not part of this review.', 'wp-design-review' ), array( 'status' => 400 ) );
		}

		$limit = self::rate_limited( $review );
		if ( $limit ) {
			return new \WP_Error( 'wpdr_rate', __( 'That is a lot of comments. Please try again a little later.', 'wp-design-review' ), array( 'status' => 429 ) );
		}

		$name     = mb_substr( trim( sanitize_text_field( (string) ( $in['name'] ?? '' ) ) ), 0, 60 );
		$name     = '' === $name ? __( 'Reviewer', 'wp-design-review' ) : $name;
		$mood     = in_array( $in['mood'] ?? '', self::MOODS, true ) ? $in['mood'] : '';
		$viewport = in_array( $in['viewport'] ?? '', self::VIEWPORTS, true ) ? $in['viewport'] : 'desktop';
		$path     = preg_match( '/^\d+(\.\d+)*$/', (string) ( $in['path'] ?? '' ) ) ? (string) $in['path'] : '';
		$snippet  = mb_substr( sanitize_text_field( (string) ( $in['snippet'] ?? '' ) ), 0, 140 );
		$compare  = ! empty( $in['compare'] );

		$note_id = '' !== $path ? self::add_note( $post, $path, $text, $name, $review, $mood, $viewport ) : 0;

		$id = wp_insert_post(
			array(
				'post_type'    => Reviews::FEEDBACK,
				'post_status'  => 'publish',
				'post_parent'  => $review['id'],
				'post_title'   => wp_trim_words( $text, 8, '...' ),
				'post_content' => $text,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		foreach (
			array(
				'_dr_name'     => $name,
				'_dr_mood'     => $mood,
				'_dr_viewport' => $viewport,
				'_dr_post'     => $post->ID,
				'_dr_note'     => $note_id,
				'_dr_snippet'  => $snippet,
				'_dr_compare'  => $compare ? 1 : 0,
			) as $k => $v
		) {
			update_post_meta( $id, $k, $v );
		}

		self::notify_owner( $review, $name, $text, $post );
		return self::get( $id );
	}

	public static function get( int $id ): ?array {
		$p = get_post( $id );
		if ( ! $p || Reviews::FEEDBACK !== $p->post_type ) {
			return null;
		}
		$f = array(
			'id'       => $id,
			'review'   => (int) $p->post_parent,
			'text'     => $p->post_content,
			'name'     => (string) get_post_meta( $id, '_dr_name', true ),
			'mood'     => (string) get_post_meta( $id, '_dr_mood', true ),
			'viewport' => (string) get_post_meta( $id, '_dr_viewport', true ),
			'post'     => (int) get_post_meta( $id, '_dr_post', true ),
			'note'     => (int) get_post_meta( $id, '_dr_note', true ),
			'snippet'  => (string) get_post_meta( $id, '_dr_snippet', true ),
			'compare'  => (bool) get_post_meta( $id, '_dr_compare', true ),
			'date'     => strtotime( $p->post_date_gmt . ' UTC' ),
		);
		$f['resolved'] = self::is_resolved( $f );
		return $f;
	}

	/** @return array[] */
	public static function for_review( int $review_id ): array {
		$ids = get_posts(
			array(
				'post_type'   => Reviews::FEEDBACK,
				'post_status' => 'publish',
				'post_parent' => $review_id,
				'numberposts' => -1,
				'fields'      => 'ids',
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		return array_values( array_filter( array_map( array( self::class, 'get' ), $ids ) ) );
	}

	public static function is_resolved( array $f ): bool {
		if ( get_post_meta( $f['id'], '_dr_resolved', true ) ) {
			return true;
		}
		if ( ! $f['note'] ) {
			return false;
		}
		$replies = get_comments(
			array(
				'type'       => 'note',
				'parent'     => self::thread_root( $f['note'] ),
				'meta_key'   => '_wp_note_status', // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'    => 'comment_ID',
				'order'      => 'DESC',
				'number'     => 1,
				'status'     => 'all',
			)
		);
		return $replies && 'resolved' === get_comment_meta( (int) $replies[0]->comment_ID, '_wp_note_status', true );
	}

	/** Mark addressed on our side and on the Note thread. */
	public static function resolve( int $id ): void {
		update_post_meta( $id, '_dr_resolved', 1 );
		$note = self::thread_root( (int) get_post_meta( $id, '_dr_note', true ) );
		$c    = $note ? get_comment( $note ) : null;
		if ( $c ) {
			$reply = wp_insert_comment(
				array(
					'comment_post_ID'  => $c->comment_post_ID,
					'comment_parent'   => $note,
					'comment_type'     => 'note',
					'comment_approved' => 1,
					'comment_content'  => '',
					'user_id'          => get_current_user_id(),
					'comment_meta'     => array( '_wp_note_status' => 'resolved' ),
				)
			);
			unset( $reply );
		}
	}

	/** Notes resolve per thread, so status replies hang off the first comment. */
	private static function thread_root( int $note ): int {
		$c = $note ? get_comment( $note ) : null;
		return $c && $c->comment_parent ? (int) $c->comment_parent : $note;
	}

	private static function visible_to_review( array $review, \WP_Post $post ): bool {
		if ( 'page' === $review['scope'] ) {
			return $post->ID === $review['target'];
		}
		$type = get_post_type_object( $post->post_type );
		return 'publish' === $post->post_status && $type && $type->public;
	}

	private static function rate_limited( array $review ): bool {
		$key   = 'wpdr_rl_' . $review['id'] . '_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$count = (int) get_transient( $key );
		if ( $count >= 20 ) {
			return true;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	/** Create (or reply to) a Note on the block at $path. Returns the comment ID, or 0. */
	private static function add_note( \WP_Post $post, string $path, string $text, string $name, array $review, string $mood, string $viewport ): int {
		$blocks = parse_blocks( $post->post_content );
		$block  = &self::locate( $blocks, explode( '.', $path ) );
		if ( null === $block || empty( $block['blockName'] ) ) {
			return 0;
		}

		$existing = (int) ( $block['attrs']['metadata']['noteId'] ?? 0 );
		$parent   = $existing && get_comment( $existing ) ? $existing : 0;

		$note_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post->ID,
				'comment_parent'       => $parent,
				'comment_type'         => 'note',
				'comment_approved'     => 1,
				'comment_content'      => $text,
				'comment_author'       => $name,
				'comment_author_email' => '',
				'user_id'              => 0,
				'comment_meta'         => array(
					'dr_review_id' => $review['id'],
					'dr_mood'      => $mood,
					'dr_viewport'  => $viewport,
				),
			)
		);
		if ( ! $note_id ) {
			return 0;
		}

		if ( ! $parent ) {
			$block['attrs']['metadata']['noteId'] = (int) $note_id;
			self::save_content( $post->ID, serialize_blocks( $blocks ) );
		}
		return (int) $note_id;
	}

	/** Follow $path (indexes into parse_blocks output). Returns a reference, or null. */
	private static function &locate( array &$blocks, array $path ) {
		$null  = null;
		$index = (int) array_shift( $path );
		if ( ! isset( $blocks[ $index ] ) ) {
			return $null;
		}
		if ( ! $path ) {
			return $blocks[ $index ];
		}
		return self::locate( $blocks[ $index ]['innerBlocks'], $path );
	}

	/** Write post content without the anonymous-visitor HTML filters mangling it. */
	private static function save_content( int $post_id, string $content ): void {
		$had_kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $had_kses ) {
			kses_remove_filters();
		}
		wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $content ) ) );
		if ( $had_kses ) {
			kses_init_filters();
		}
	}

	private static function notify_owner( array $review, string $name, string $text, \WP_Post $post ): void {
		$user = get_userdata( $review['creator'] );
		if ( ! $user ) {
			return;
		}
		/* translators: 1: reviewer name, 2: review label */
		$subject = sprintf( __( '%1$s left a comment on "%2$s"', 'wp-design-review' ), $name, $review['label'] );
		$body    = $text . "\n\n" . get_edit_post_link( $post->ID, 'raw' ) . "\n" . admin_url( 'admin.php?page=wp-design-review' );
		wp_mail( $user->user_email, $subject, $body );
	}
}
