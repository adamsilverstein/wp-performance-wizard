<?php
/**
 * Plugin Name: WP Performance Wizard
 * Plugin URI: https://github.com/adamsilverstein/wp-performance-wizard
 * Description: A plugin that uses AI to help you optimize your WordPress site for performance.
 * Version: 2.0.0
 * Author: Adam Silverstein, Google
 * Author URI: https://github.com/adamsilverstein
 * License: GPLv2 or later
 * Text Domain: wp-performance-wizard
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 7.0
 * Requires PHP: 7.4
 *
 * @package wp-performance-wizard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimum WordPress version required.
 *
 * Tied to the Connectors API, which ships in WordPress 7.0.
 */
define( 'WP_PERFORMANCE_WIZARD_MIN_WP_VERSION', '7.0' );

/**
 * Check that the WordPress version satisfies the plugin's minimum.
 *
 * @return bool True if the current WordPress version is supported.
 */
function wp_performance_wizard_is_supported_wp(): bool {
	global $wp_version;
	return version_compare( (string) $wp_version, WP_PERFORMANCE_WIZARD_MIN_WP_VERSION, '>=' );
}

/**
 * Render an admin notice when the WordPress version is too old.
 */
function wp_performance_wizard_unsupported_wp_notice(): void {
	echo '<div class="notice notice-error"><p>';
	printf(
		/* translators: %s: minimum WordPress version */
		esc_html__( 'WP Performance Wizard requires WordPress %s or later because it uses the core Connectors API. Please upgrade WordPress to continue using the plugin.', 'wp-performance-wizard' ),
		esc_html( WP_PERFORMANCE_WIZARD_MIN_WP_VERSION )
	);
	echo '</p></div>';
}

// Bail early on unsupported WordPress versions; show a notice instead.
if ( ! wp_performance_wizard_is_supported_wp() ) {
	add_action( 'admin_notices', 'wp_performance_wizard_unsupported_wp_notice' );
	return;
}

// Load the plugin.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-performance-wizard.php';
$pw = new WP_Performance_Wizard();
