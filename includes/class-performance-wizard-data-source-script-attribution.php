<?php
/**
 * Script Attribution Data Source
 *
 * This file contains the Script Attribution data source class which provides
 * information about scripts loaded on the site and their source plugins.
 *
 * @package wp-performance-wizard
 */

/**
 * A class providing script attribution data
 *
 * @package wp-performance-wizard
 */
class Performance_Wizard_Data_Source_Script_Attribution extends Performance_Wizard_Data_Source_Base {

	/**
	 * Plugin data array
	 *
	 * @var array<string,array<string,string>>
	 */
	private $plugins_data = array();

	/**
	 * Construct the class, setting key variables.
	 */
	public function __construct() {
		parent::__construct();
		$this->set_name( 'Script Attribution' );
		$this->set_prompt( 'Collecting data about the scripts on the site...' );
		$this->set_description( 'The Script Attribution data source provides a list of scripts on the page. For each script it provides the path (or URL), as well as the slug and name of the plugin that enqueued the script.' );
		$this->set_analysis_strategy( 'The Script Attribution data source can be combined with the Lighthouse data to include the plugin name when making recommendations. When a Lighthouse audit identifies a script as a performance issue, the script attribution data can be used to identify the specific plugin that is causing the issue.' );
		$this->set_data_shape( 'The returned data is a JSON object with a list of scripts. Each script object includes the path (or URL) of the script, the slug and name of the plugin that enqueued the script' );

		// Collect front end metrics.
		add_action( 'wp_footer', array( $this, 'get_and_store_already_queued_scripts' ), PHP_INT_MAX );
		add_action( 'wp_footer', array( $this, 'get_and_store_manually_output_scripts' ), PHP_INT_MAX );
	}

	/**
	 * Get the script attribution data and return in a structured data object.
	 *
	 * @return string JSON encoded string of the script attribution data.
	 */
	public function get_data(): string {
		$already_queued_scripts  = get_transient( 'performance_wizard_script_attribution_queued' );
		$manually_output_scripts = get_transient( 'performance_wizard_script_attribution_manually_output' );
		$scripts                 = array_merge( $already_queued_scripts, $manually_output_scripts );
		return wp_json_encode( $scripts );
	}

	/**
	 * Get and store all of the manually output scripts.
	 *
	 * Check all plugins hooked to wp_head or wp_footer to see if they are enqueuing scripts.
	 * Run all hooks using output buffering, then review content for any script handles that are enqueued.
	 * Use the HTML API to parse for script handle, then add that to the performance marks.
	 *
	 * For each script, store the plugin slug and name and the script path.
	 */
	public function get_and_store_manually_output_scripts(): void {
		$stored_scripts = get_transient( 'performance_wizard_script_attribution_manually_output' );
		if ( false !== $stored_scripts ) {
			return;
		}

		// Remove own actions to avoid recursion.
		remove_action( 'wp_footer', array( $this, 'get_and_store_already_queued_scripts' ), PHP_INT_MAX );
		remove_action( 'wp_footer', array( $this, 'get_and_store_manually_output_scripts' ), PHP_INT_MAX );

		$scripts = array();
		$hooks   = array(
			'wp_head',
			'wp_footer',
		);
		foreach ( $hooks as $hook ) {
			// Get all callbacks hooked on this hook and invoke them one at a time.
			$callbacks = $GLOBALS['wp_filter'][ $hook ];
			foreach ( $callbacks as $priority => $sub_callbacks ) {
				foreach ( $sub_callbacks as $callback ) {
					// Capture the output HTML.
					ob_start();
					call_user_func( $callback['function'], array() );
					$html = ob_get_clean();
					// Parse the HTML for any script handles.
					if ( '' === $html ) {
						continue;
					}
					$processor = new WP_HTML_Tag_Processor( $html );
					while ( $processor->next_tag() ) {
						if ( 'SCRIPT' === $processor->get_tag() ) {
							$src         = $processor->get_attribute( 'src' );
							$plugin_slug = '';
							if ( null !== $src && '' !== $src ) {
								if ( is_array( $callback['function'] ) ) {
									$class_name  = $callback['function'][0]; // Class.
									$method_name = $callback['function'][1]; // Method.
									try {
										$reflection_method = new ReflectionMethod( $class_name, $method_name );
										$file_path         = $reflection_method->getFileName();
										$plugin_slug       = $this->get_slug_from_path( $file_path );
									} catch ( ReflectionException $e ) {
										continue;
									}
								} else {
									$function_name = $callback['function'];
									try {
										$reflection_function = new ReflectionFunction( $function_name );
										$file_path           = $reflection_function->getFileName();
										$plugin_slug         = $this->get_slug_from_path( $file_path );
									} catch ( ReflectionException $e ) {
										continue;
									}
									$plugin_data = $this->get_plugin_data_by_slug( $plugin_slug );
									$scripts[]   = array(
										'path' => $src,
										'slug' => '' === $plugin_data['slug'] ? 'core' : $plugin_data['slug'],
										'name' => '' === $plugin_data['name'] ? 'Core' : $plugin_data['name'],
									);
								}
							}
						}
					}
				}
			}
		}
		set_transient( 'performance_wizard_script_attribution_manually_output', $scripts, 60 * 60 * 24 ); // 24 hours.
	}

	/**
	 * Helper to get the plugin slug from a file path.
	 *
	 * @param string $file_path The file path.
	 * @return string The plugin slug.
	 */
	private function get_slug_from_path( string $file_path ): string {
		if ( '' === $file_path ) {
			return '';
		}
		$pattern = '#/(?:plugins|themes)/([^/]+)/#'; // Match anything after '/plugins/' or '/themes/' up to the next '/'.
		preg_match( $pattern, $file_path, $matches );
		return $matches[1];
	}

	/**
	 * Helper function to get the plugin slug and name when passed a script path.
	 *
	 * @param string $src The script path.
	 * @return array<string, string> The plugin slug, name and path.
	 */
	private function get_plugin_data_from_src( string $src ): array {

		// Get just the local path for the src (removing the local domain).
		$src = str_replace( get_site_url(), '', $src );

		if ( str_starts_with( $src, '/wp-includes/' ) ) {
			return array(
				'slug' => 'core',
				'name' => 'Core',
				'path' => $src,
			);
		}

		// Extract the slug from $src, eg. "/wp-content/plugins/{slug}/path/to/script.js".
		$slugs = explode( '/', $src );
		$slug  = $slugs[3];

		$plugin_data = $this->get_plugin_data_by_slug( $slug );

		return array(
			'slug' => $plugin_data['slug'],
			'name' => $plugin_data['name'],
			'path' => $src,
		);
	}

	/**
	 * Get data for plugin by slug.
	 *
	 * @param string $slug The plugin slug.
	 * @return array<string, string> The plugin slug and name.
	 */
	private function get_plugin_data_by_slug( string $slug ): array {
		if ( '' === $slug ) {
			return array(
				'slug' => '',
				'name' => '',
			);
		}
		foreach ( $this->plugins_data as $plugin_slug => $plugin_data ) {
			if ( $slug === $plugin_data['TextDomain'] ) {
				return array(
					'slug' => $plugin_data['TextDomain'],
					'name' => $plugin_data['Name'],
				);
			}
		}
		return array(
			'slug' => '',
			'name' => '',
		);
	}

	/**
	 * Helper to get and store already queued scripts.
	 *
	 * Runs on front end loads of the site, storing data in a transient.
	 */
	public function get_and_store_already_queued_scripts(): void {
		global $wp_scripts;

		$scripts = get_transient( 'performance_wizard_script_attribution_queued' );
		if ( false !== $scripts ) {
			return;
		}

		$scripts = array();
		foreach ( $wp_scripts->done as $handle ) {
			$src = $wp_scripts->registered[ $handle ]->src;
			if ( false === $src ) {
				continue;
			}
			// Gather the plugin slug, name at relative path.
			$plugin_data = $this->get_plugin_data_from_src( $src );
			$scripts[]   = array(
				'path' => $plugin_data['path'],
				'slug' => $plugin_data['slug'],
				'name' => $plugin_data['name'],
			);
		}
		set_transient( 'performance_wizard_script_attribution_queued', $scripts, 60 * 60 * 24 ); // 24 hours.
	}
}
