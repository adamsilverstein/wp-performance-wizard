<?php
/**
 * Add a Admin page to wp-admin under Tools->Performance Wizard.
 *
 * @package wp-performance-wizard
 */

/**
 * The Admin page class.
 */
class Performance_Wizard_Admin_Page {
	/**
	 * Construct the class by adding the primary callback.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Add the menu to wp-admin.
	 */
	public function add_menu(): void {

		add_submenu_page(
			'tools.php',
			__( 'Performance Wizard', 'wp-performance-wizard' ),
			__( 'Performance Wizard', 'wp-performance-wizard' ),
			'manage_options',
			'wp-performance-wizard',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the Admin page. This will show an interactive terminals where users can
	 * monitor and interact with the AI agent.
	 */
	public function render_page(): void {

		// Check if the user has the correct permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add the page title.
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

		// Add the description.
		echo '<p>' . esc_html__( 'The Performance Wizard will analyze your site using an AI agent, then make recommendations to improve performance.', 'wp-performance-wizard' ) . '</p>';

		// Add a start button.
		echo '<p><button id="performance-wizard-start" class="button button-primary">' . esc_html__( 'Start analysis', 'wp-performance-wizard' ) . '</button></p>';

		// Add the terminal.
		echo '<div id="performance-wizard-terminal"></div>';
	}

	/**
	 * Enqueue the scripts and styles.
	 */
	public function admin_enqueue_scripts(): void {

		// Only enqueue scripts on the Performance Wizard page.
		if ( ! isset( $_GET['page'] ) || 'wp-performance-wizard' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Force refresh with a timestamp version.
		$timestamp_version = '';

		// use the '.min' extension, unless WP_DEBUG is enabled.
		$suffix = '.min';
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$suffix = '';
		}

		// Enqueue the terminal script.
		wp_enqueue_script( 'performance-wizard-terminal', plugin_dir_url( __FILE__ ) . 'js/jquery.terminal' . $suffix . '.js', array( 'jquery', 'wp-api-fetch' ), '1.0.0', array( 'strategy' => 'defer' ) );

		// Enqueue the terminal styles.
		wp_enqueue_style( 'performance-wizard-terminal', plugin_dir_url( __FILE__ ) . 'css/jquery.terminal' . $suffix . '.css', array(), '1.0.0' );

		// Enqueue the plugin styles.
		wp_enqueue_style( 'wp-performance-wizard', plugin_dir_url( __FILE__ ) . 'css/wp-performance-wizard.css', array(), '1.0.0' );

		// Enqueue the bootstrap script.
		wp_enqueue_script( 'wp-performance-wizard', plugin_dir_url( __FILE__ ) . 'js/wp-performance-wizard.js', array( 'performance-wizard-terminal' ), '1.0.0' . $timestamp_version, array( 'strategy' => 'defer' ) );
	}
}
