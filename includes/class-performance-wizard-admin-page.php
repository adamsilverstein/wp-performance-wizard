<?php
/**
 * Add a Admin page to wp-admin under Tools->Performance Wizard.
 */
class Performance_Wizard_Admin_Page {
	// Construct the class by adding the primary callback.
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Add the menu to wp-admin.
	 *
	 * @return void
	 */
	public function add_menu() {
		error_log( 'add submenu page' );
		add_submenu_page(
			'tools.php',
			__( 'Performance Wizard', 'performance-wizard' ),
			__( 'Performance Wizard', 'performance-wizard' ),
			'manage_options',
			'wp-performance-wizard',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the Admin page. This will show an interactive terminals where users can
	 * monitor and interact with the AI agent.
	 */
	public function render_page() {
		error_log( 'render_page' );
		// Check if the user has the correct permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add the page title.
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

		// Add the terminal.
		echo '<div id="performance-wizard-terminal"></div>';

	}

	/**
	 * Enqueue the scripts and styles.
	 */
	public function admin_enqueue_scripts() {
		error_log( 'admin_enqueue_scripts' );

		// Only enqueue scripts on the Performance Wizard page.
		if ( ! isset( $_GET['page'] ) || 'wp-performance-wizard' !== $_GET['page'] ) {
			return;
		}

		// Force refresh with a timestamp version.
		$timestamp_version = "";//time();

		// use the '.min' extension, unless WP_DEBUG is enabled.
		$suffix = '.min';
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$suffix = '';
		}


		// Enqueue the terminal script.
		wp_enqueue_script( 'performance-wizard-terminal', plugin_dir_url( __FILE__ ) . 'js/jquery.terminal' . $suffix . '.js', array( 'jquery', 'wp-api-fetch' ), '1.0.0', array( 'strategy' => 'defer' ) );

		// Enqueue the terminal styles.
		wp_enqueue_style( 'performance-wizard-terminal', plugin_dir_url( __FILE__ ) . 'css/jquery.terminal' . $suffix . '.css', array(), '1.0.0' );

		// Enqueue the bootstrap script.
		wp_enqueue_script( 'wp-performance-wizard', plugin_dir_url( __FILE__ ) . 'js/wp-performance-wizard.js', array( 'performance-wizard-terminal' ), WP_PERFORMANCE_WIZARD_VERSION . $timestamp_version, array( 'strategy' => 'defer' ) );

	}

}