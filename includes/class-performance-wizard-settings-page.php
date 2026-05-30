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
	 * Page types that the URL-dependent data sources can analyze.
	 *
	 * Keyed by the slug stored in the option, with a human-readable label as
	 * the value.
	 *
	 * @var array<string,string>
	 */
	const SUPPORTED_PAGE_TYPES = array(
		'home'    => 'Home page',
		'archive' => 'Posts archive',
		'post'    => 'Most recent post',
	);

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
			'page_types'              => array( 'home' ),
			'pagespeed_api_key'       => '',
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
	 * The page types that URL-dependent data sources (Lighthouse, HTML) should
	 * analyze on each run.
	 *
	 * Filterable via `wp_performance_wizard_page_types`. Always constrained to
	 * SUPPORTED_PAGE_TYPES; if filtering yields an empty list, falls back to
	 * `home` to guarantee at least one URL.
	 *
	 * @return string[] The selected page type slugs.
	 */
	public static function page_types(): array {
		$options    = self::get_options();
		$page_types = is_array( $options['page_types'] ) ? $options['page_types'] : array( 'home' );
		/**
		 * Filters the list of page types to analyze.
		 *
		 * @param mixed $page_types Current list from settings (string[]).
		 */
		$page_types = apply_filters( 'wp_performance_wizard_page_types', $page_types );
		if ( ! is_array( $page_types ) ) {
			$page_types = array( 'home' );
		}
		$page_types = array_map( 'sanitize_key', $page_types );
		$page_types = array_values( array_intersect( array_keys( self::SUPPORTED_PAGE_TYPES ), $page_types ) );
		return 0 === count( $page_types ) ? array( 'home' ) : $page_types;
	}

	/**
	 * The Google PageSpeed Insights API key used for Lighthouse requests.
	 *
	 * Without a key, requests are anonymous and share a near-zero global
	 * quota, so they fail with an HTTP 429 "rateLimitExceeded" error. A key
	 * tied to a Google Cloud project with the PageSpeed Insights API enabled
	 * grants the free 25,000 requests/day quota.
	 *
	 * Filterable via `wp_performance_wizard_pagespeed_api_key` so the key can
	 * be supplied in code (for example, from an environment variable or
	 * constant) instead of being stored in the database.
	 *
	 * @return string The API key, or an empty string when none is configured.
	 */
	public static function pagespeed_api_key(): string {
		$options = self::get_options();
		$key     = isset( $options['pagespeed_api_key'] ) ? (string) $options['pagespeed_api_key'] : '';
		/**
		 * Filters the PageSpeed Insights API key used for Lighthouse requests.
		 *
		 * @param string $key The API key from settings, or an empty string.
		 */
		$key = apply_filters( 'wp_performance_wizard_pagespeed_api_key', $key );
		return trim( $key );
	}

	/**
	 * Resolve the URL to fetch for a given page type slug.
	 *
	 * Returns an empty string when a representative URL cannot be resolved
	 * (for example, no published posts yet).
	 *
	 * @param string $page_type One of the SUPPORTED_PAGE_TYPES keys.
	 *
	 * @return string The URL, or an empty string when unresolved.
	 */
	public static function get_page_type_url( string $page_type ): string {
		$url = '';

		switch ( $page_type ) {
			case 'home':
				$url = get_site_url();
				break;

			case 'post':
				$posts = get_posts( array( 'numberposts' => 1 ) );
				if ( count( $posts ) > 0 ) {
					$permalink = get_permalink( $posts[0]->ID );
					$url       = is_string( $permalink ) ? $permalink : '';
				}
				break;

			case 'archive':
				$archive = get_post_type_archive_link( 'post' );
				$url     = is_string( $archive ) ? $archive : '';
				break;
		}

		/**
		 * Filter the URL used for performance wizard analysis.
		 *
		 * Use this to point the wizard at a staging URL or override the URL
		 * resolved for a specific page type before it is fetched. The filter
		 * runs for every supported page type so staging-site overrides cover
		 * the home page, the posts archive, and individual post permalinks.
		 *
		 * @param string $url       The resolved URL, or an empty string when none could be resolved.
		 * @param string $page_type The page type slug being resolved (one of the SUPPORTED_PAGE_TYPES keys).
		 * @return string The filtered URL.
		 */
		return apply_filters( 'wp_performance_wizard_site_url', $url, $page_type );
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options             = self::get_options();
		$collect_enabled     = (bool) $options['collect_plugin_sources'];
		$selected_languages  = (array) $options['plugin_source_languages'];
		$skills_enabled      = (bool) $options['use_expert_skills'];
		$selected_page_types = (array) $options['page_types'];
		$pagespeed_api_key   = isset( $options['pagespeed_api_key'] ) ? (string) $options['pagespeed_api_key'] : '';

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
			echo '<label style="display:inline-block;margin-right:1em;"><input type="checkbox" name="plugin_source_languages[]" value="' . esc_attr( $language ) . '"' . checked( $checked, true, false ) . '> ';
			echo esc_html( strtoupper( $language ) );
			echo '</label>';
		}
		echo '<p class="description">' . esc_html__( 'PHP is recommended for the best signal-to-cost ratio. All file types are capped by size to keep prompt size manageable.', 'wp-performance-wizard' ) . '</p>';
		echo '</fieldset></td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Page Types to Analyze', 'wp-performance-wizard' ) . '</h2>';
		echo '<p>' . esc_html__( 'Performance characteristics differ between templates. The URL-dependent data sources (Lighthouse, HTML) will run against each selected page type and label the results so the AI can compare template-specific issues. Selecting more page types increases analysis time and Lighthouse API usage.', 'wp-performance-wizard' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Page types', 'wp-performance-wizard' ) . '</th><td><fieldset>';
		echo '<legend class="screen-reader-text">' . esc_html__( 'Page types', 'wp-performance-wizard' ) . '</legend>';
		foreach ( self::SUPPORTED_PAGE_TYPES as $page_type => $label ) {
			$checked = in_array( $page_type, $selected_page_types, true );
			echo '<label style="display:inline-block;margin-right:1em;"><input type="checkbox" name="page_types[]" value="' . esc_attr( $page_type ) . '"' . checked( $checked, true, false ) . '> ';
			echo esc_html( $label );
			echo '</label>';
		}
		echo '<p class="description">' . esc_html__( 'Home page is selected by default. Archive and Most recent post may not resolve to a URL on a brand-new site with no published posts.', 'wp-performance-wizard' ) . '</p>';
		echo '</fieldset></td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'PageSpeed Insights API Key', 'wp-performance-wizard' ) . '</h2>';
		echo '<p>';
		printf(
			/* translators: 1: link to the Google PageSpeed Insights API key page. */
			esc_html__( 'The Lighthouse data source fetches results from the Google PageSpeed Insights API. Without an API key, requests are anonymous and share a near-zero quota, so they fail with a "Queries per day" quota error even on the first run. Provide a free key from %1$s (the project must have the PageSpeed Insights API enabled) to get the free 25,000 requests/day allowance.', 'wp-performance-wizard' ),
			'<a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank" rel="noreferrer noopener">developers.google.com</a>'
		);
		echo '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="wp-perf-wizard-pagespeed-api-key">' . esc_html__( 'API key', 'wp-performance-wizard' ) . '</label></th><td>';
		echo '<input type="password" autocomplete="off" id="wp-perf-wizard-pagespeed-api-key" name="pagespeed_api_key" class="regular-text" value="' . esc_attr( $pagespeed_api_key ) . '">';
		echo '<p class="description">' . esc_html__( 'Stored in the site database. Leave blank to make anonymous (unauthenticated) requests, which are heavily rate limited. Can also be set in code via the wp_performance_wizard_pagespeed_api_key filter.', 'wp-performance-wizard' ) . '</p>';
		echo '</td></tr>';
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

		$submitted_page_types = array();
		if ( isset( $_POST['page_types'] ) && is_array( $_POST['page_types'] ) ) {
			foreach ( wp_unslash( $_POST['page_types'] ) as $page_type ) {
				$page_type = sanitize_key( (string) $page_type );
				if ( isset( self::SUPPORTED_PAGE_TYPES[ $page_type ] ) ) {
					$submitted_page_types[] = $page_type;
				}
			}
		}
		if ( 0 === count( $submitted_page_types ) ) {
			$submitted_page_types = array( 'home' );
		}

		$pagespeed_api_key = '';
		if ( isset( $_POST['pagespeed_api_key'] ) ) {
			$pagespeed_api_key = trim( sanitize_text_field( wp_unslash( $_POST['pagespeed_api_key'] ) ) );
		}

		$options                            = self::get_options();
		$options['collect_plugin_sources']  = $collect;
		$options['plugin_source_languages'] = array_values( array_unique( $submitted_languages ) );
		$options['use_expert_skills']       = $skills_enabled;
		$options['page_types']              = array_values( array_unique( $submitted_page_types ) );
		$options['pagespeed_api_key']       = $pagespeed_api_key;

		update_option( self::OPTION_NAME, $options );

		wp_safe_redirect( add_query_arg( 'info', 'saved', $redirect ) );
		exit;
	}
}
