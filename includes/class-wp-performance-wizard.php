<?php
/**
 * Load the performance wizard plugin.
 *
 * @package wp-performance-wizard
 */
class WP_Performance_Wizard {
	// Properties

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
	 *
	 *
	 */

	/**
	 * The analysis plan.
	 * @var Performance_Wizard_Analysis_Plan
	 */
	private $analysis_plan;

	/**
	 * The AI Agent.
	 *
	 * @var Performance_Wizard_AI_Agent
	 */
	private $ai_agent;

	/**
	 * Set up the plugin, bootstrapping required classes.
	 */
	public function __construct() {

		// only initiate the plugin on the Tools->Performance Wizard page.
		if ( ! wp_is_json_request() && ( ! isset( $_GET['page'] ) || 'wp-performance-wizard' !== $_GET['page'] ) ) {
			return;
		}

		$this->load_required_files();

		// Load the wp-admin page.
		new Performance_Wizard_Admin_Page();


		// Load the AI Agent.
		$this->ai_agent = new Performance_Wizard_AI_Agent_Gemini();
		$api_key        = $this->get_api_key( $this->ai_agent->get_name() );
		$this->ai_agent->set_api_key( $api_key );

		// Load the REST API handler.
		new Performance_Wizard_Rest_API( $this );


		// Load the Analysis plan
		$this->analysis_plan = new Performance_Wizard_Analysis_Plan( $this );


	}

	/**
	 * Load the required files for the plugin.
	 */
	private function load_required_files() {
		// Load all required files.
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-admin-page.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-analysis-plan.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-rest-api.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-ai-agent-base.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-ai-agent-gemini.php';
	}


	/**
	 * Function to get the api key for a specific AI agent.
	 *
	 * // Key is stored in a JSON file with the key "apikey"
	 */
	public function get_api_key( $agent_name ) {
		if ( empty( $agent_name ) ) {
			return '';
		}
		$filename = plugin_dir_path( __FILE__ ) . '../.keys/' . strtolower( $agent_name ) . '-key.json';
		$keydata  = json_decode( file_get_contents( $filename ) );
		return $keydata->apikey;
	}
	/**
	 * Get the analysis plan class.
	 *
	 * @return Performance_Wizard_Analysis_Plan
	 */
	public function get_analysis_plan() {
		return $this->analysis_plan;
	}

	/**
	 * Get the ai agent.
	 */
	public function get_ai_agent() {
		return $this->ai_agent;
	}

}

/**
 * Get the site URL for the AI agent.
 */
function wp_performance_wizard_get_site_url() {
	$site_url = get_site_url();
	/**
	 * Filter the site URL used for performance wizard analysis
	 *
	 * @param string $site_url The site URL.
	 * @return string The filtered site URL.
	 */
	return apply_filters( 'wp_performance_wizard_site_url', $site_url );
}