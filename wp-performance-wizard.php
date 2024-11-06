<?php
/**
 * Plugin Name: WP Performance Wizard
 * Plugin URI: https://github.com/adamsilverstein/wp-performance-wizard
 * Description: A plugin that uses AI to help you optimize your WordPress site for performance.
 * Version: 1.3.1
 * Author: Adam Silverstein, Google
 * Author URI: https://github.com/adamsilverstein
 * License: GPLv2 or later
 * Text Domain: wp-performance-wizard
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package wp-performance-wizard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the plugin.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-performance-wizard.php';
$pw = new WP_Performance_Wizard();
