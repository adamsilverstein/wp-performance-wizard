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
	 * @var array
	 */
	private $data_sources = array();

	// Constructor
	/**
	 * Set up the plugin, bootstrapping required classes.
	 */
	public function __construct() {
		error_log( 'including files' );

		// Load all required files.
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-admin-page.php';

		// Load the wp-admin page.
		new Performance_Wizard_Admin_Page();
	}

}