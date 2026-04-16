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
	 * Maximum size in bytes for a single source file included in the prompt.
	 */
	const MAX_SOURCE_FILE_BYTES = 65536;

	/**
	 * Maximum number of source files collected per plugin.
	 */
	const MAX_SOURCE_FILES_PER_PLUGIN = 50;

	/**
	 * Maximum total bytes of source code across all plugins for one collection run.
	 */
	const MAX_SOURCE_TOTAL_BYTES = 524288;

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
		$this->set_data_shape( "The returned data includes an 'active_theme' object and an 'active_plugins' array. The active_theme object contains name, slug, version, author, description, is_block_theme (boolean indicating Full Site Editing support), and a 'theme_api_data' field with metadata from the wordpress.org theme API. Each entry in active_plugins includes the name, slug, version, author, description, URI and a field named 'plugin_api_data' which contains the metadata about the plugin from the wordpress.org plugin API. This metadata includes a rating field with an overall rating (0-100) of the plugin, as well as a ratings object with the number of 1 star (worst) thru 5 star (best) reviews, as well as fields for support_threads and support_threads_resolved. When the site administrator has opted into plugin source collection, a plugin entry may also include an optional 'source_files' array, where each entry has a 'relative_path' (path within the plugin) and 'source' (file contents). Source files are capped in size and count and only included for plugins hosted on WordPress.org." );
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

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
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
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Get the active theme and plugins data and return in a structured data object.
	 *
	 * The returned JSON contains an 'active_theme' object (including wordpress.org
	 * theme API metadata and a block theme indicator) and an 'active_plugins' array
	 * with per-plugin metadata augmented by wordpress.org plugin API data.
	 *
	 * @return string JSON encoded string of the active theme and plugins data.
	 */
	public function get_data(): string {
		global $wp_filesystem;

		$active_theme   = wp_get_theme();
		$active_plugins = get_option( 'active_plugins' );

		// bootstrap wp-admin/includes/plugin.php so we can use it to get plugin data.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Determine whether to collect plugin source code, and prepare the
		// filesystem and budget once for the whole loop.
		$collect_sources    = class_exists( 'Performance_Wizard_Settings_Page' )
			&& Performance_Wizard_Settings_Page::collect_plugin_sources();
		$source_languages   = $collect_sources ? Performance_Wizard_Settings_Page::plugin_source_languages() : array();
		$total_bytes_budget = self::MAX_SOURCE_TOTAL_BYTES;
		$filesystem_ready   = false;
		if ( $collect_sources ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$filesystem_ready = WP_Filesystem();
		}

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

				// Optionally, collect the plugin source code from WordPress.org.
				if ( $collect_sources && $filesystem_ready && $total_bytes_budget > 0 ) {
					$decoded       = json_decode( $plugin_api_data );
					$download_link = is_object( $decoded ) && isset( $decoded->download_link ) ? (string) $decoded->download_link : '';
					if ( '' !== $download_link ) {
						$source_files = $this->collect_plugin_source_files(
							$plugin_slug,
							$download_link,
							$source_languages,
							$total_bytes_budget
						);
						if ( count( $source_files ) > 0 ) {
							$plugin_entry['source_files'] = $source_files;
						}
					}
				}
			}
			$plugins_data[] = $plugin_entry;
		}

		// Theme slug is usually the stylesheet (directory) name.
		$theme_slug     = $active_theme->get_stylesheet();
		$theme_api_data = $this->get_theme_data_from_dotorg_api( $theme_slug );

		// Detect if the theme is a block theme (WP 5.9+).
		$is_block_theme = function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : ( file_exists( get_theme_root() . '/' . $theme_slug . '/theme.json' ) );

		$theme_data = array(
			'name'           => $active_theme->get( 'Name' ),
			'version'        => $active_theme->get( 'Version' ),
			'author'         => $active_theme->get( 'Author' ),
			'description'    => $active_theme->get( 'Description' ),
			'slug'           => $theme_slug,
			'is_block_theme' => $is_block_theme,
			'theme_api_data' => $theme_api_data,
		);

		$to_return = array(
			'active_theme'   => $theme_data,
			'active_plugins' => $plugins_data,
		);

		return wp_json_encode( $to_return );
	}

	/**
	 * Download a plugin zip from WordPress.org and collect selected source files.
	 *
	 * Only downloads URLs hosted on downloads.wordpress.org. The slug is
	 * sanitized before being used in any path. The temp directory is always
	 * cleaned up, regardless of how this method exits.
	 *
	 * @param string   $slug                The plugin slug.
	 * @param string   $download_link       The download URL reported by the plugin API.
	 * @param string[] $languages           File extensions to collect (e.g. ['php', 'js']).
	 * @param int      $total_bytes_budget  Remaining byte budget across the whole run; updated by reference.
	 *
	 * @return array<int,array<string,string>> List of {relative_path, source} entries.
	 */
	private function collect_plugin_source_files( string $slug, string $download_link, array $languages, int &$total_bytes_budget ): array {
		$collected = array();

		$parsed_host = wp_parse_url( $download_link, PHP_URL_HOST );
		if ( 'downloads.wordpress.org' !== $parsed_host ) {
			return $collected;
		}

		$safe_slug = sanitize_key( $slug );
		if ( '' === $safe_slug ) {
			return $collected;
		}

		$temp_root = trailingslashit( get_temp_dir() ) . 'wp-perf-wizard-' . wp_generate_password( 8, false, false );
		if ( ! wp_mkdir_p( $temp_root ) ) {
			return $collected;
		}

		$zip_file = '';
		try {
			$download_result = download_url( $download_link );
			if ( is_wp_error( $download_result ) ) {
				return $collected;
			}
			$zip_file = $download_result;

			$unzip = unzip_file( $zip_file, $temp_root );
			if ( is_wp_error( $unzip ) ) {
				return $collected;
			}

			$plugin_root  = trailingslashit( $temp_root ) . $safe_slug;
			$iterate_from = is_dir( $plugin_root ) ? $plugin_root : $temp_root;

			$iterator    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $iterate_from, FilesystemIterator::SKIP_DOTS ) );
			$files_count = 0;

			foreach ( $iterator as $file ) {
				if ( $file->isDir() ) {
					continue;
				}
				if ( $files_count >= self::MAX_SOURCE_FILES_PER_PLUGIN ) {
					break;
				}
				if ( $total_bytes_budget <= 0 ) {
					break;
				}

				$extension = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
				if ( ! in_array( $extension, $languages, true ) ) {
					continue;
				}

				$size = (int) $file->getSize();
				if ( $size <= 0 || $size > self::MAX_SOURCE_FILE_BYTES ) {
					continue;
				}
				if ( $size > $total_bytes_budget ) {
					continue;
				}

				// Local temp file: read with native PHP rather than the WP_Filesystem
				// abstraction, which may be initialized with FTP/SSH on some hosts and
				// would fail on local paths.
				$source = file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( false === $source || '' === $source ) {
					continue;
				}

				$relative_path = ltrim( substr( $file->getPathname(), strlen( $iterate_from ) ), '/\\' );

				$collected[] = array(
					'relative_path' => $relative_path,
					'source'        => $source,
				);

				$total_bytes_budget -= $size;
				++$files_count;
			}
		} finally {
			if ( '' !== $zip_file && file_exists( $zip_file ) ) {
				wp_delete_file( $zip_file );
			}
			$this->recursive_rmdir( $temp_root );
		}

		return $collected;
	}

	/**
	 * Recursively remove a directory created by this data source.
	 *
	 * Uses native PHP rather than WP_Filesystem because the directory always
	 * lives under the local system temp dir, where the FTP/SSH transports
	 * WP_Filesystem may select would fail. Restricted to paths under the
	 * temp base to prevent accidental deletion of anything else.
	 *
	 * @param string $dir Absolute path to the directory to remove.
	 */
	private function recursive_rmdir( string $dir ): void {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}
		$temp_base = trailingslashit( get_temp_dir() );
		if ( 0 !== strpos( $dir, $temp_base ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $entry ) {
			if ( $entry->isDir() ) {
				@rmdir( $entry->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			} else {
				wp_delete_file( $entry->getPathname() );
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
