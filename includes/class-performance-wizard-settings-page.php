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
			'ai_models'               => array(),
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
	 * The models that can be selected for each AI provider, keyed by connector ID.
	 *
	 * Each provider maps to a label and an ordered list of model choices (model
	 * ID => human-readable label), cheapest first, with an empty model ID
	 * meaning "use the provider's default model" (the AI Client's behavior when
	 * no model preference is given). Model IDs are passed to the AI Client's
	 * using_model_preference(), which falls back to a compatible model when the
	 * exact ID is unavailable, so a slightly outdated ID degrades gracefully.
	 *
	 * Filterable via `wp_performance_wizard_models` so the list can be kept
	 * current or extended without a plugin update.
	 *
	 * @return array<string,mixed> Map keyed by connector ID; each value is an
	 *                             array with 'label' and 'models' (model ID => label).
	 */
	public static function get_supported_models(): array {
		$models = array(
			'anthropic' => array(
				'label'  => __( 'Anthropic Claude', 'wp-performance-wizard' ),
				'models' => array(
					''                  => __( 'Provider default', 'wp-performance-wizard' ),
					'claude-haiku-4-5'  => __( 'Claude Haiku (lowest cost)', 'wp-performance-wizard' ),
					'claude-sonnet-4-6' => __( 'Claude Sonnet (balanced cost and quality)', 'wp-performance-wizard' ),
					'claude-opus-4-8'   => __( 'Claude Opus (highest quality, highest cost)', 'wp-performance-wizard' ),
				),
			),
			'gemini'    => array(
				'label'  => __( 'Google Gemini', 'wp-performance-wizard' ),
				'models' => array(
					''                 => __( 'Provider default', 'wp-performance-wizard' ),
					'gemini-2.5-flash' => __( 'Gemini Flash (lowest cost)', 'wp-performance-wizard' ),
					'gemini-2.5-pro'   => __( 'Gemini Pro (higher cost)', 'wp-performance-wizard' ),
				),
			),
			'openai'    => array(
				'label'  => __( 'OpenAI ChatGPT', 'wp-performance-wizard' ),
				'models' => array(
					''           => __( 'Provider default', 'wp-performance-wizard' ),
					'gpt-5-mini' => __( 'GPT-5 mini (lower cost)', 'wp-performance-wizard' ),
					'gpt-5'      => __( 'GPT-5 (higher cost)', 'wp-performance-wizard' ),
				),
			),
		);

		/**
		 * Filters the selectable models for each AI provider connector.
		 *
		 * @param mixed $models Map keyed by connector ID; each value is an array
		 *                      with 'label' and 'models' (model ID => label).
		 */
		$models = apply_filters( 'wp_performance_wizard_models', $models );
		return is_array( $models ) ? $models : array();
	}

	/**
	 * The model ID selected for a given provider connector.
	 *
	 * Returns an empty string (meaning "use the provider default model") when
	 * nothing is selected or the stored value is not a recognized choice for
	 * that connector.
	 *
	 * Filterable via `wp_performance_wizard_selected_model` so the model can be
	 * forced in code (for example, from a constant) per connector.
	 *
	 * @param string $connector_id The provider connector ID (e.g. 'anthropic').
	 *
	 * @return string The selected model ID, or an empty string for the default.
	 */
	public static function selected_model( string $connector_id ): string {
		$options   = self::get_options();
		$selected  = isset( $options['ai_models'] ) && is_array( $options['ai_models'] ) ? $options['ai_models'] : array();
		$model     = isset( $selected[ $connector_id ] ) ? (string) $selected[ $connector_id ] : '';
		$supported = self::get_supported_models();

		// Only honor a stored value that is a recognized choice for the connector.
		if ( '' !== $model
			&& ( ! isset( $supported[ $connector_id ]['models'][ $model ] ) ) ) {
			$model = '';
		}

		/**
		 * Filters the model ID used for a given provider connector.
		 *
		 * @param mixed  $model        The selected model ID, or an empty string for the provider default.
		 * @param string $connector_id The provider connector ID.
		 */
		$model = apply_filters( 'wp_performance_wizard_selected_model', $model, $connector_id );
		return is_string( $model ) ? $model : '';
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
		 * @param mixed $key The API key from settings, or an empty string. Cast to
		 *                   a string before use, so any value type is tolerated.
		 */
		$key = (string) apply_filters( 'wp_performance_wizard_pagespeed_api_key', $key );
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
		$selected_models     = isset( $options['ai_models'] ) && is_array( $options['ai_models'] ) ? $options['ai_models'] : array();
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

		echo '<h2>' . esc_html__( 'Model Selection', 'wp-performance-wizard' ) . '</h2>';
		echo '<p>' . esc_html__( 'Choose which model each AI provider uses for the analysis. Lower-cost models (such as Claude Haiku or Gemini Flash) can reduce the cost of a run substantially. "Provider default" leaves the choice to the AI Client. The selection only applies to whichever provider you run the analysis with.', 'wp-performance-wizard' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( self::get_supported_models() as $connector_id => $provider ) {
			if ( ! isset( $provider['models'] ) || ! is_array( $provider['models'] ) ) {
				continue;
			}
			$field_id = 'wp-perf-wizard-model-' . sanitize_key( $connector_id );
			$current  = isset( $selected_models[ $connector_id ] ) ? (string) $selected_models[ $connector_id ] : '';
			$label    = isset( $provider['label'] ) ? (string) $provider['label'] : $connector_id;

			echo '<tr><th scope="row"><label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label></th><td>';
			echo '<select id="' . esc_attr( $field_id ) . '" name="ai_models[' . esc_attr( $connector_id ) . ']">';
			foreach ( $provider['models'] as $model_id => $model_label ) {
				$model_id    = (string) $model_id;
				$model_label = (string) $model_label;
				echo '<option value="' . esc_attr( $model_id ) . '"' . selected( $current, $model_id, false ) . '>' . esc_html( $model_label ) . '</option>';
			}
			echo '</select>';
			echo '</td></tr>';
		}
		echo '<tr><td colspan="2"><p class="description">' . esc_html__( 'Model IDs can be kept current or extended in code via the wp_performance_wizard_models filter. Unavailable models fall back to a compatible one automatically.', 'wp-performance-wizard' ) . '</p></td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'PageSpeed Insights API Key', 'wp-performance-wizard' ) . '</h2>';
		echo '<p>';
		printf(
			wp_kses(
				/* translators: 1: link to the Google PageSpeed Insights API key page. */
				__( 'The Lighthouse data source fetches results from the Google PageSpeed Insights API. Without an API key, requests are anonymous and share a near-zero quota, so they fail with a "Queries per day" quota error even on the first run. Provide a free key from %1$s (the project must have the PageSpeed Insights API enabled) to get the free 25,000 requests/day allowance.', 'wp-performance-wizard' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			),
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

		// Accept a model selection per provider, but only when the submitted
		// value is a recognized choice for that connector. An empty value means
		// "use the provider default" and is stored as such. Seed from the
		// existing saved selections so a provider that is filtered out of
		// get_supported_models() (and therefore not rendered or submitted) keeps
		// its stored selection instead of being silently dropped.
		$supported_models = self::get_supported_models();
		$current_options  = self::get_options();
		$submitted_models = isset( $current_options['ai_models'] ) && is_array( $current_options['ai_models'] ) ? $current_options['ai_models'] : array();
		if ( isset( $_POST['ai_models'] ) && is_array( $_POST['ai_models'] ) ) {
			foreach ( wp_unslash( $_POST['ai_models'] ) as $connector_id => $model_id ) {
				$connector_id = sanitize_key( (string) $connector_id );
				$model_id     = sanitize_text_field( (string) $model_id );
				if ( ! isset( $supported_models[ $connector_id ]['models'] ) ) {
					continue;
				}
				if ( '' === $model_id || isset( $supported_models[ $connector_id ]['models'][ $model_id ] ) ) {
					$submitted_models[ $connector_id ] = $model_id;
				}
			}
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
		$options['ai_models']               = $submitted_models;
		$options['pagespeed_api_key']       = $pagespeed_api_key;

		update_option( self::OPTION_NAME, $options );

		wp_safe_redirect( add_query_arg( 'info', 'saved', $redirect ) );
		exit;
	}
}
