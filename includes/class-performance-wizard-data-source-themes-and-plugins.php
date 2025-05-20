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
		$this->set_analysis_strategy(
			'The Themes and Plugins data source can be analyzed by looking for common performance issues for the listed themes and plugins and combined with the HTML and Lighthouse data to make recommendations about the installed theme and plugins.
		In particular, review the audits from the Lighthouse data and for each audit failure, try to identify the specific plugin from the site that could be causing the issue.
		Lighthouse provides a path to scripts that are causing performance issues and for assets enqueued by plugins, this will usually include the plugin slug as part of the path (typically /wp-content/plugins/{slug}/path...). Use this information to correlate plugins to the assets they enqueue.
		The plugin meta data returned includes additional quality signals, such as an overall rating, counts of 1-5 star reviews, and fields for support_threads and support_threads_resolved which indicate support responsiveness. Use this information when comparing plugins or considering disabling a plugin.'
		);
		$this->set_data_shape( "The returned data for each plugin includes the name, slug, version, author, description, URI  and a field named 'plugin_api_data' which contains the metadata about the plugin from the wordpress.org plugin API. This metadata includes a rating field with an overall rating (0-100) of the plugin, as well as a ratings object with the number of 1 star (worst) thru 5 star (best) reviews, as well as fields for support_threads and support_threads_resolved ." );
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

	/**
	 * Helper function to retrieve all of the meta data about the theme that is available from the wordpress.org theme REST API.
	 *
	 * @param string $slug The slug of the theme to get the data for.
	 * @return string The meta data about the theme.
	 */
	public function get_theme_data_from_dotorg_api( string $slug ): string {
		$api_base = 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&request[slug]=';
		$response = wp_remote_get( $api_base . $slug );
		if ( is_wp_error( $response ) ) {
			return '';
		}
		return wp_remote_retrieve_body( $response );
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
			$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
			$plugin_data = get_plugin_data( $plugin_file );
			$plugin_slug = $plugin_data['TextDomain'];

			// Skip the wp-performance-wizard plugin.
			if ( 'wp-performance-wizard' === $plugin_slug ) {
				continue;
			}

			$plugin_entry = array(
				'name'        => $plugin_data['Name'],
				'slug'        => $plugin_slug,
				'version'     => $plugin_data['Version'],
				'author'      => $plugin_data['Author'],
				'description' => $plugin_data['Description'],
				'URI'         => $plugin_data['PluginURI'],
			);

			// Also, get the data from the Plugin API.
			$plugin_api_data = $this->get_plugin_data_from_dotorg_api( $plugin_slug );
			if ( '' !== $plugin_api_data ) {
				$plugin_entry['plugin_api_data'] = $plugin_api_data;
			}
			$plugins_data[] = $plugin_entry;
		}

		// Theme slug is usually the stylesheet (directory) name.
		$theme_slug = $active_theme->get_stylesheet();
		$theme_api_data = $this->get_theme_data_from_dotorg_api( $theme_slug );

		// Detect if the theme is a block theme (WP 5.9+).
		$is_block_theme = function_exists('wp_is_block_theme') ? wp_is_block_theme() : ( file_exists( get_theme_root() . '/' . $theme_slug . '/theme.json' ) );

		$theme_data = array(
			'name'           => $active_theme->get( 'Name' ),
			'version'        => $active_theme->get( 'Version' ),
			'author'         => $active_theme->get( 'Author' ),
			'description'    => $active_theme->get( 'Description' ),
			'slug'           => $theme_slug,
			'is_block_theme' => (bool) $is_block_theme,
			'theme_api_data' => $theme_api_data,
		);

		$to_return  = array(
			'active_theme'   => $theme_data,
			'active_plugins' => $plugins_data,
		);

		return wp_json_encode( $to_return );
	}
}
