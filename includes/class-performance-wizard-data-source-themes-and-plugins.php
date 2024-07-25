<?php
/**
 * A class describing the Themes and Plugins data source.
 */

 class Performance_Wizard_Data_Source_Themes_And_Plugins extends Performance_Wizard_Data_Source_Base {

	/**
	 * Construct the class, setting key variables
	 */
	function __construct() {
		parent::__constructor();
		$this->set_name( 'Themes and Plugins' );
		$this->set_prompt( 'Collecting data about the themes and plugins used on the site...' );
		$this->set_description( "The Themes and Plugins data source provides a list of the theme and plugins installed on the website, as well as meta data about those plugins." );
		$this->set_analysis_strategy( 'The Themes and Plugins data source can be analyzed by looking for common performance issues for the listed themes and plugins and combined with the HTML and Lighthouse data to make reccomendations about the instlled theme and plugins.' );
	}

	/**
	 * Get the active theme and plugins data and return in a structured data object.
	 */
	public function get_data() {
		$active_theme   = wp_get_theme();
		$active_plugins = get_option( 'active_plugins' );

		// bootstrap wp-admin/includes/plugin.php so we can use it to get plugin data.
		require_once ( ABSPATH . 'wp-admin/includes/plugin.php' );

		$plugins_data = array();
		foreach ( $active_plugins as $plugin ) {
			$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
			$plugin_data = get_plugin_data( $plugin_file );
			$plugins_data[] = array(
				'name'        => $plugin_data['Name'],
				'version'     => $plugin_data['Version'],
				'author'      => $plugin_data['Author'],
				'description' => $plugin_data['Description'],
				'PluginURI'   => $plugin_data['PluginURI'],
			);
		}
		$theme_data = array(
			'name'        => $active_theme->get( 'Name' ),
			'version'     => $active_theme->get( 'Version' ),
			'author'      => $active_theme->get( 'Author' ),
			'description' => $active_theme->get( 'Description' ),
		);
		$to_return = array(
			'active_theme'   => $theme_data,
			'active_plugins' => $plugins_data,
		);

		// Log the themes and plugins data to be returned.
		error_log( 'Themes and Plugins data: ' . wp_json_encode( $to_return, JSON_PRETTY_PRINT ) );

		return wp_json_encode( $to_return );
	}

 }