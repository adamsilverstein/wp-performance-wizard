<?php
/**
 * Test bootstrap: defines just enough WordPress surface for the base agent
 * class to load outside of a real WordPress environment.
 *
 * @package wp-performance-wizard
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

if ( ! isset( $GLOBALS['wp_performance_wizard_test_options'] ) ) {
	$GLOBALS['wp_performance_wizard_test_options'] = array();
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Minimal get_option() stub backed by $GLOBALS.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value when the option is missing.
	 * @return mixed
	 */
	function get_option( string $name, $default = false ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound
		if ( isset( $GLOBALS['wp_performance_wizard_test_options'][ $name ] ) ) {
			return $GLOBALS['wp_performance_wizard_test_options'][ $name ];
		}
		return $default;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-performance-wizard-ai-agent-base.php';
