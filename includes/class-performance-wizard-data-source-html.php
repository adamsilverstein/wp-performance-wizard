<?php
/**
 * A class describing the HTML data source.
 *
 * @package wp-performance-wizard
 */

/**
 * Define the HTML data source class.
 */
class Performance_Wizard_Data_Source_HTML extends Performance_Wizard_Data_Source_Base {

	/**
	 * Construct the class, setting key variables
	 */
	public function __construct() {
		parent::__construct();
		$this->set_name( 'HTML' );
		$this->set_prompt( 'Collecting HTML source for the site, attempting to grab the home page, a recent post and an archive page.' );
		$this->set_description( 'The HTML data source provides the HTML of the website as retrieved from the front end by an unauthenticated user.' );
		$this->set_analysis_strategy( 'The data returned is JSON encoded and includes an array with three keys: "home_page", "most_recent_post" and "archive_page". keys value contains the HTML of the respective page. The HTML data can be analyzed by looking for common performance issues in the HTML. Particular attention can be paid to issues identified in the Lighthouse audit at the beginning of the analysis. Files loaded from a WordPress plugin or theme can typically be identified by their path: /wp-content/plugins/<plugin-slug> or /wp-content/themes/<theme-slug>. This analysis is for the website so scripts from other domains (so called "third party scripts") should be reviewed individually for their potential performance impact. Also, consider the Lighthouse report script details from the previous step when considering which scripts are having the most impact.' );
	}

	/**
	 * Get the HTML of three pages and return in a structured data object
	 *
	 * The home page, the most recent published post and an archive page should be analyzed.
	 *
	 * @return string JSON encoded string of the HTML data.
	 */
	public function get_data(): string {
		/**
		 * Filter the site URL used for performance wizard analysis
		 *
		 * @param string $site_url The site URL.
		 * @return string The filtered site URL.
		 */
		$site_url       = apply_filters( 'wp_performance_wizard_site_url', get_site_url() );
		$home_page      = wp_remote_get( $site_url );
		$home_page_body = wp_remote_retrieve_body( $home_page );

		// Get the most recent post.
		$posts                 = get_posts( array( 'numberposts' => 1 ) );
		$most_recent_post      = wp_remote_get( get_permalink( $posts[0]->ID ) );
		$most_recent_post_body = wp_remote_retrieve_body( $most_recent_post );

		$archive_page      = wp_remote_get( get_post_type_archive_link( 'post' ) );
		$archive_page_body = wp_remote_retrieve_body( $archive_page );

		$to_return = array(
			'home_page'        => $home_page_body,
			'most_recent_post' => $most_recent_post_body,
			'archive_page'     => $archive_page_body,
		);

		return wp_json_encode( $to_return );
	}
}
