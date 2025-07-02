<?php
/**
 * Load the performance wizard plugin.
 *
 * @package wp-performance-wizard
 */

/**
 * The main class for the plugin.
 */
class WP_Performance_Wizard {
	/**
	 * The name of the option to store previous step data.
	 *
	 * @var string
	 */
	private $option_name = 'performance_wizard_analysis_plan_steps';

	/**
	 * The data sources are the data that the AI uses to make its recommendations.
	 *
	 * These will include most or all of the following data sources:
	 *  - A lighthouse report on the site.
	 *  - The list of active theme and plugins, along with their source code and analysis from pluginsreview.com.
	 *  - HTTPArchive data on the site.
	 *  - The HTML of a front end page load for the home page, an archive page and a single post page.
	 *  - The CSS of a front end page load for the home page, an archive page and a single post page.
	 *  - The JavaScript of a front end page load for the home page, an archive page and a single post page.
	 *
	 * plugintests.com
	 * hive.com
	 *
	 *  Potential additional meta-data
	 *  - A list of the site's cron jobs.
	 *  - A list of the site's autoloaded options.
	 *  - A webhostreviewer report on the site's hosting.
	 *  - A list of the site's database tables and their sizes.
	 *  - A server timing report of a front end page load.
	 *  - A list of the site's rewrite rules.
	 *  - A DevTools trace of a front end page load.
	 *  - Details about the images on a front end page load for the home page, an archive page and a single post page.
	 *  - Details about the fonts on a front end page load for the home page, an archive page and a single post page.
	 *  - Details about the third party scripts on a front end page load for the home page, an archive page and a single post page.
	 *  - Performance recommendations for WordPress sites from best practices guides from Google's web.dev, the WordPress developers handbook, 10up's best practices and other sources.
	 *
	 * The data sources will be fed into the AI as part of a series of prompts to help it make its recommendations.
	 */

	/**
	 * The analysis plan.
	 *
	 * @var Performance_Wizard_Analysis_Plan
	 */
	private $analysis_plan;

	/**
	 * The AI Agent.
	 *
	 * @var Performance_Wizard_AI_Agent_Base|null
	 */
	private $ai_agent;

	/**
	 * Supported agents.
	 *
	 * @var array
	 */
	private $supported_agents = array(
		'Gemini'  => 'Performance_Wizard_AI_Agent_Gemini',
		'Claude'  => 'Performance_Wizard_AI_Agent_Claude',
		'ChatGPT' => 'Performance_Wizard_AI_Agent_ChatGPT',
	);
	/**
	 * Get supported agents.
	 *
	 * @return string[] The supported agents.
	 */
	public function get_supported_agents(): array {
		return $this->supported_agents;
	}

	/**
	 * Get available models (agents with valid API keys).
	 *
	 * @return array Array of available models with their details.
	 */
	public function get_available_models(): array {
		$available_models = array();

		foreach ( $this->supported_agents as $agent_name => $agent_class_name ) {
			$agent   = new $agent_class_name( $this );
			$api_key = $agent->get_api_key();

			if ( '' !== $api_key ) {
				$available_models[ $agent_name ] = array(
					'name'        => $agent_name,
					'class'       => $agent_class_name,
					'description' => $agent->get_description(),
				);
			}
		}

		return $available_models;
	}

	/**
	 * Set the AI agent based on the selected model.
	 *
	 * @param string $model_name The name of the model to use.
	 * @return bool True if the model was set successfully, false otherwise.
	 */
	public function set_ai_agent( string $model_name ): bool {
		if ( ! isset( $this->supported_agents[ $model_name ] ) ) {
			return false;
		}

		$agent_class_name = $this->supported_agents[ $model_name ];
		$agent            = new $agent_class_name( $this );

		// Check if the agent has a valid API key.
		if ( '' === $agent->get_api_key() ) {
			return false;
		}

		$this->ai_agent = $agent;
		$this->ai_agent->set_system_instructions( $this->analysis_plan->get_system_instructions() );

		return true;
	}

	/**
	 * Set up the plugin, bootstrapping required classes.
	 */
	public function __construct() {
		$this->load_required_files();

		// Load the wp-admin page.
		new Performance_Wizard_Admin_Page( $this );

		// Load the Analysis plan.
		$this->analysis_plan = new Performance_Wizard_Analysis_Plan( $this );

		// Load the $supported_agents, eg. call new Performance_Wizard_AI_Agent_Gemini( $this ) for each agent.
		foreach ( $this->supported_agents as $agent_name => $agent_class_name ) {
			$agent = new $agent_class_name( $this );

			// Set $this->ai_agent if not set already.
			if ( ! isset( $this->ai_agent ) && '' !== $agent->get_api_key() ) {
				$this->ai_agent = $agent;
			}
		}

		// Ignore WordPress.Security.NonceVerification.Recommended on the next line.
		if ( ( ! isset( $_GET['page'] ) || 'wp-performance-wizard' !== $_GET['page'] ) && ! wp_is_json_request() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( null !== $this->ai_agent ) {
			$this->ai_agent->set_system_instructions( $this->analysis_plan->get_system_instructions() );
		}

		// Load the REST API handler.
		new Performance_Wizard_Rest_API( $this );
	}

	/**
	 * Load the required files for the plugin.
	 */
	private function load_required_files(): void {
		// Load all required files.
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-admin-page.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-analysis-plan.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-rest-api.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-ai-agent-base.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-ai-agent-gemini.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-ai-agent-claude.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-ai-agent-chatgpt.php';
	}


	/**
	 * Function to get the api key for a specific AI agent.
	 *
	 * The key is stored in a JSON file with the key "apikey".
	 *
	 * @param string $agent_name The name of the agent to get the key for.
	 *
	 * @return string The API key.
	 */
	public function get_api_key( string $agent_name ): string {
		global $wp_filesystem;

		if ( '' === $agent_name ) {
			return '';
		}
		$filename = plugin_dir_path( __FILE__ ) . '../.keys/' . strtolower( $agent_name ) . '-key.json';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$keydata = json_decode( $wp_filesystem->get_contents( $filename ) );
		return isset( $keydata->apikey ) ? $keydata->apikey : '';
	}

	/**
	 * Get the analysis plan class.
	 *
	 * @return Performance_Wizard_Analysis_Plan The analysis plan.
	 */
	public function get_analysis_plan(): Performance_Wizard_Analysis_Plan {
		return $this->analysis_plan;
	}

	/**
	 * Get the ai agent.
	 *
	 * @return Performance_Wizard_AI_Agent_Base|null The AI agent.
	 */
	public function get_ai_agent(): ?Performance_Wizard_AI_Agent_Base {
		return $this->ai_agent;
	}

	/**
	 * Get the option name.
	 *
	 * @return string The option name.
	 */
	public function get_option_name(): string {
		return $this->option_name;
	}

	/**
	 * Get the transient key for storing user's AI model preference.
	 *
	 * @param int|null $user_id The user ID. If null, uses current user.
	 * @return string The transient key.
	 */
	public function get_model_preference_transient_key( ?int $user_id = null ): string {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		return 'performance_wizard_selected_model_user_' . $user_id;
	}
}
