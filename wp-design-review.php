<?php
/**
 * Plugin Name:       WP Design Review
 * Plugin URI:        https://github.com/kellychoffman/wp-design-review
 * Description:       Share the look, not the editor. Signed, expiring review links that show the real site wearing pending redesign styles, with feedback that lands as Notes.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            Kelly Hoffman
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-design-review
 */

defined( 'ABSPATH' ) || exit;

define( 'WPDR_VERSION', '0.1.0' );
define( 'WPDR_FILE', __FILE__ );
define( 'WPDR_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( static function ( $class ) {
	$prefix = 'WpDesignReview\\';
	if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
		return;
	}
	$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
	if ( is_file( $file ) ) {
		require $file;
	}
} );

add_action( 'init', array( \WpDesignReview\Reviews::class, 'register' ), 1 );
\WpDesignReview\Frontend::init();
\WpDesignReview\Rest::init();
\WpDesignReview\Admin::init();
