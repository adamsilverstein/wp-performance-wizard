<?php
/**
 * A class describing the Themes and Plugins data source.
 *
 * @package wp-performance-wizard
 */

/**
 * Define the theme and plugins data source class.
 */
class Performance_Wizard_Data_Source_Themes_And_Plugins extends Performance_Wizard_Data_Source_Base {

	/**
	 * Construct the class, setting key variables
	 */
	public function __construct() {
		parent::__construct();
		$this->set_name( 'Themes and Plugins' );
		$this->set_prompt( 'Collecting data about the themes and plugins used on the site...' );
		$this->set_description( 'The Themes and Plugins data source provides a list of the theme and plugins installed on the website, as well as meta data about those plugins.' );
		$this->set_analysis_strategy( 'The Themes and Plugins data source can be analyzed by looking for common performance issues for the listed themes and plugins and combined with the HTML and Lighthouse data to make recommendations about the installed theme and plugins. In particular, review the audits from the Lighthouse data and for each audit failure, try to identify the specific plugin from the site. Lighthouse provides a path to problematic scripts and for plugins, this will usually include the plugin slug.' );
		$this->set_data_shape( "The returned data for each plugin includes a field named 'plugin_api_data' which contains the meta data about the plugin from the wordpress.org plugin API. This data includes a 'download' field which is a link you can follow to download a zip archive of the complete plugin source code. The versions field contains links to all versions of the plugin so you can download the version installed on the site you are working on for analysis." );
	}

	/**
	 * Get the active theme and plugins data and return in a structured data object.
	 *
	 * @return string JSON encoded string of the active theme and plugins data.
	 */
	public function get_data(): string {
		global $wp_filesystem;

		$active_theme   = wp_get_theme();
		$active_plugins = get_option( 'active_plugins' );

		// bootstrap wp-admin/includes/plugin.php so we can use it to get plugin data.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins_data = array();
		foreach ( $active_plugins as $plugin ) {
			$plugin_file    = WP_PLUGIN_DIR . '/' . $plugin;
			$plugin_data    = get_plugin_data( $plugin_file );
			$plugins_data[] = array(
				'name'        => $plugin_data['Name'],
				'version'     => $plugin_data['Version'],
				'author'      => $plugin_data['Author'],
				'description' => $plugin_data['Description'],
				'PluginURI'   => $plugin_data['PluginURI'],
			);

			// Also, get the data from the Plugin API.
			$plugin_slug     = dirname( plugin_basename( $plugin_data['PluginURI'] ) );
			$plugin_api_data = $this->get_plugin_data_from_dotorg_api( $plugin_slug );

			if ( '' !== $plugin_api_data ) {
				$plugins_data['plugin_api_data'] = $plugin_api_data;
			}
		}
		$theme_data = array(
			'name'        => $active_theme->get( 'Name' ),
			'version'     => $active_theme->get( 'Version' ),
			'author'      => $active_theme->get( 'Author' ),
			'description' => $active_theme->get( 'Description' ),
		);
		$to_return  = array(
			'active_theme'   => $theme_data,
			'active_plugins' => $plugins_data,
		);

		return wp_json_encode( $to_return );
	}

	/**
	 * Helper function to retrieve all of the meta data about the plugin that is available from the wordpress.org plugin REST API.
	 *
	 * @param string $slug The slug of the plugin to get the data for.
	 *
	 * @return string The meta data about the plugin.
	 */
	public function get_plugin_data_from_dotorg_api( string $slug ): string {
		$api_base = 'https://api.wordpress.org/plugins/info/1.0/';
		$response = wp_remote_get( $api_base . $slug . '.json' );

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			return '';
		}

		// Return the data.
		$results = wp_remote_retrieve_body( $response );

		return $results;
	}
}
