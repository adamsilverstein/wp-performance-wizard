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
	 * The main wizard class.
	 *
	 * @var WP_Performance_Wizard
	 */
	private $wizard;

	/**
	 * Construct the class by adding the primary callback.
	 *
	 * @param WP_Performance_Wizard $wizard The main wizard class.
	 */
	public function __construct( WP_Performance_Wizard $wizard ) {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		$this->wizard = $wizard;
	}

	/**
	 * Add the menu to wp-admin.
	 */
	public function add_menu(): void {

		add_menu_page(
			__( 'Performance Wizard', 'wp-performance-wizard' ),
			__( 'Performance Wizard', 'wp-performance-wizard' ),
			'manage_options',
			'wp-performance-wizard',
			array( $this, 'render_page' ),
			'dashicons-performance',
			90
		);
		// Add submenu pages for all supported agents that have them.
		$supported_agents = $this->wizard->get_supported_agents();
		foreach ( $supported_agents as $agent_name => $agent_class_name ) {
			$agent_class = new $agent_class_name( $this->wizard );
			if ( method_exists( $agent_class, 'add_submenu_page' ) ) {
				$agent_class->add_submenu_page();
			}
		}
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

		echo '<h3>' . esc_html__( 'Select the data sources to use for the analysis:', 'wp-performance-wizard' ) . '</h3>';
		// Add checkboxes for all of the data sources.
		$data_sources = $this->wizard->get_analysis_plan()->get_data_sources();
		foreach ( $data_sources as $data_source ) {
			echo '<label><input type="checkbox" class="performance-wizard-data-source" name="data_source" value="' . esc_attr( $data_source['name'] ) . '" checked>' . esc_html( $data_source['name'] ) . '</label><br>';
		}

		// Add hidden elements for steps that always run. Summarize Results, Wrap Up and Introduction.
		foreach ( array( 'Summarize Results', 'Wrap Up', 'Introduction' ) as $step ) {
			echo '<input type="hidden" class="performance-wizard-data-source" value="' . esc_attr( $step ) . '" checked>';
		}

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

		// Enqueue the plugin styles.
		wp_enqueue_style( 'wp-performance-wizard', plugin_dir_url( __FILE__ ) . 'css/wp-performance-wizard.css', array(), '1.0.0' );

		// Enqueue the marked library.
		wp_enqueue_script( 'marked', plugin_dir_url( __FILE__ ) . 'js/marked.min.js', array(), '1.0.0' . $timestamp_version, true );

		// Enqueue the bootstrap script.
		wp_enqueue_script( 'wp-performance-wizard', plugin_dir_url( __FILE__ ) . 'js/wp-performance-wizard.js', array( 'wp-api-fetch', 'marked' ), '1.0.0' . $timestamp_version, array( 'strategy' => 'defer' ) );
	}
}
