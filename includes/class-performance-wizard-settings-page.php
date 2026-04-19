<?php
/**
 * Settings page for the Performance Wizard.
 *
 * @package wp-performance-wizard
 */

/**
 * Registers a Settings submenu under the Performance Wizard menu and manages
 * plugin-level options such as whether to include plugin source code in the
 * data collected for analysis.
 */
class Performance_Wizard_Settings_Page {

	/**
	 * Option name that stores all settings as a single array.
	 */
	const OPTION_NAME = 'wp_performance_wizard_options';

	/**
	 * Languages that can be collected from plugin source trees.
	 *
	 * @var string[]
	 */
	const SUPPORTED_LANGUAGES = array( 'php', 'js', 'css', 'html' );

	/**
	 * Wire up admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_submenu_page' ), 20 );
		add_action( 'admin_post_wp_perf_wizard_save_settings', array( $this, 'handle_submission' ) );
	}

	/**
	 * Register the Settings submenu under the Performance Wizard menu.
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			'wp-performance-wizard',
			__( 'Performance Wizard Settings', 'wp-performance-wizard' ),
			__( 'Settings', 'wp-performance-wizard' ),
			'manage_options',
			'wp-performance-wizard-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Return the full options array with defaults applied.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_options(): array {
		$stored   = get_option( self::OPTION_NAME, array() );
		$defaults = array(
			'collect_plugin_sources'  => false,
			'plugin_source_languages' => array( 'php' ),
			'use_expert_skills'       => true,
		);
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( $defaults, $stored );
	}

	/**
	 * Whether plugin source collection is enabled.
	 *
	 * Filterable via `wp_performance_wizard_collect_plugin_sources`.
	 */
	public static function collect_plugin_sources(): bool {
		$options = self::get_options();
		$enabled = (bool) $options['collect_plugin_sources'];
		/**
		 * Filters whether plugin source collection is enabled.
		 *
		 * @param mixed $enabled Current value from settings (bool).
		 */
		return (bool) apply_filters( 'wp_performance_wizard_collect_plugin_sources', $enabled );
	}

	/**
	 * The languages (file extensions) to collect from plugin source trees.
	 *
	 * Filterable via `wp_performance_wizard_plugin_source_languages`. Always
	 * constrained to SUPPORTED_LANGUAGES.
	 *
	 * @return string[]
	 */
	public static function plugin_source_languages(): array {
		$options   = self::get_options();
		$languages = is_array( $options['plugin_source_languages'] ) ? $options['plugin_source_languages'] : array( 'php' );
		/**
		 * Filters the list of plugin source languages to collect.
		 *
		 * @param mixed $languages Current list from settings (string[]).
		 */
		$languages = apply_filters( 'wp_performance_wizard_plugin_source_languages', $languages );
		if ( ! is_array( $languages ) ) {
			$languages = array( 'php' );
		}
		$languages = array_map( 'sanitize_key', $languages );
		$languages = array_values( array_intersect( self::SUPPORTED_LANGUAGES, $languages ) );
		return 0 === count( $languages ) ? array( 'php' ) : $languages;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options            = self::get_options();
		$collect_enabled    = (bool) $options['collect_plugin_sources'];
		$selected_languages = (array) $options['plugin_source_languages'];
		$skills_enabled     = (bool) $options['use_expert_skills'];

		$notice = isset( $_GET['info'] ) ? sanitize_key( wp_unslash( $_GET['info'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

		if ( 'saved' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wp-performance-wizard' ) . '</p></div>';
		} elseif ( 'nonce_error' === $notice ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'wp-performance-wizard' ) . '</p></div>';
		} elseif ( 'permission_error' === $notice ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to change these settings.', 'wp-performance-wizard' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="wp_perf_wizard_save_settings">';
		wp_nonce_field( 'wp_perf_wizard_save_settings', 'wp_perf_wizard_settings_nonce' );

		echo '<h2>' . esc_html__( 'Plugin Source Collection', 'wp-performance-wizard' ) . '</h2>';
		echo '<div class="notice notice-warning inline"><p>';
		echo esc_html__( 'When enabled, the Themes and Plugins data source will download the source code of each active plugin from WordPress.org and include selected file types in the analysis prompt. This can significantly increase prompt size, token usage, and cost. Only plugins hosted on WordPress.org are supported — paid, private, or custom plugins are skipped automatically.', 'wp-performance-wizard' );
		echo '</p></div>';

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Collect plugin sources', 'wp-performance-wizard' ) . '</th><td>';
		echo '<label><input type="checkbox" name="collect_plugin_sources" value="1"' . checked( $collect_enabled, true, false ) . '> ';
		echo esc_html__( 'Include plugin source code from WordPress.org in analysis data.', 'wp-performance-wizard' );
		echo '</label>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'File types to include', 'wp-performance-wizard' ) . '</th><td><fieldset>';
		echo '<legend class="screen-reader-text">' . esc_html__( 'File types to include', 'wp-performance-wizard' ) . '</legend>';
		foreach ( self::SUPPORTED_LANGUAGES as $language ) {
			$checked = in_array( $language, $selected_languages, true );
			echo '<label style="margin-right:1em;"><input type="checkbox" name="plugin_source_languages[]" value="' . esc_attr( $language ) . '"' . checked( $checked, true, false ) . '> ';
			echo esc_html( strtoupper( $language ) );
			echo '</label>';
		}
		echo '<p class="description">' . esc_html__( 'PHP is recommended for the best signal-to-cost ratio. All file types are capped by size to keep prompt size manageable.', 'wp-performance-wizard' ) . '</p>';
		echo '</fieldset></td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Expert Reference Skills', 'wp-performance-wizard' ) . '</h2>';
		echo '<p>';
		printf(
			/* translators: 1: link to addyosmani/web-quality-skills, 2: link to WordPress/agent-skills */
			esc_html__( 'When enabled, each analysis step includes bundled expert reference material from %1$s (MIT) and %2$s (GPL-2.0-or-later) so recommendations stay grounded in well-known web performance and WordPress best practices. This adds some tokens to each prompt. Disable to reduce cost or if your chosen model already includes equivalent guidance.', 'wp-performance-wizard' ),
			'<a href="https://github.com/addyosmani/web-quality-skills" target="_blank" rel="noreferrer noopener">addyosmani/web-quality-skills</a>',
			'<a href="https://github.com/WordPress/agent-skills" target="_blank" rel="noreferrer noopener">WordPress/agent-skills</a>'
		);
		echo '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Include expert skills', 'wp-performance-wizard' ) . '</th><td>';
		echo '<label><input type="checkbox" name="use_expert_skills" value="1"' . checked( $skills_enabled, true, false ) . '> ';
		echo esc_html__( 'Inject expert reference skills as context in each analysis step.', 'wp-performance-wizard' );
		echo '</label>';
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle the settings form submission.
	 */
	public function handle_submission(): void {
		$redirect = admin_url( 'admin.php?page=wp-performance-wizard-settings' );

		if ( ! isset( $_POST['wp_perf_wizard_settings_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_perf_wizard_settings_nonce'] ) ), 'wp_perf_wizard_save_settings' ) ) {
			wp_safe_redirect( add_query_arg( 'info', 'nonce_error', $redirect ) );
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( add_query_arg( 'info', 'permission_error', $redirect ) );
			exit;
		}

		$collect = isset( $_POST['collect_plugin_sources'] );

		$submitted_languages = array();
		if ( isset( $_POST['plugin_source_languages'] ) && is_array( $_POST['plugin_source_languages'] ) ) {
			foreach ( wp_unslash( $_POST['plugin_source_languages'] ) as $language ) {
				$language = sanitize_key( (string) $language );
				if ( in_array( $language, self::SUPPORTED_LANGUAGES, true ) ) {
					$submitted_languages[] = $language;
				}
			}
		}
		if ( 0 === count( $submitted_languages ) ) {
			$submitted_languages = array( 'php' );
		}

		$skills_enabled = isset( $_POST['use_expert_skills'] );

		$options                            = self::get_options();
		$options['collect_plugin_sources']  = $collect;
		$options['plugin_source_languages'] = array_values( array_unique( $submitted_languages ) );
		$options['use_expert_skills']       = $skills_enabled;

		update_option( self::OPTION_NAME, $options );

		wp_safe_redirect( add_query_arg( 'info', 'saved', $redirect ) );
		exit;
	}
}
