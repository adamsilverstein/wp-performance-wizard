<?php
/**
 * A class describing the HTML data source.
 */

class Performance_Wizard_Data_Source_HTML extends Performance_Wizard_Data_Source_Base {

	/**
	 * Construct the class, setting key variables
	 */
	function __construct() {
		parent::__constructor();
		$this->set_name( 'HTML' );
		$this->set_prompt( 'Collecting HTML source for the site, attempting to grab the home page, a recent post and an archive page.' );
		$this->set_description( "The HTML data source provides the HTML of the website as retrieved from the front end by an unauthenticated user." );
		$this->set_analysis_strategy( 'The HTML data source can be analyzed by looking for common performance issues in the HTML. Particular attention can be paid to issues identified in the Lighthouse audit at the beginning of the analysis. Files loaded from a WordPress plugin or theme can typically be identified by their path: /wp-content/plugins/<plugin-slug> or /wp-content/themes/<theme-slug>. This analysis is for the website so scripts from other domains (so called "third party scripts") should be reviewed individually for their potential performance impact.' );
	}

	/**
	 * Get the HTML of three pages and return in a structured data object
	 *
	 * The home page, the most recent published post and an archive page should be analyzed.
	 */
	public function get_data() {
		$site_url       = wp_performance_wizard_get_site_url();
		$home_page      = wp_remote_get( $site_url );
		$home_page_body = wp_remote_retrieve_body( $home_page );

		// Get the most recent post.
		$posts                 = get_posts( array( 'numberposts' => 1 ) );
		$most_recent_post      = wp_remote_get( get_permalink( $posts[0]->ID ) );
		$most_recent_post_body = wp_remote_retrieve_body( $most_recent_post );

		$archive_page      = wp_remote_get( get_post_type_archive_link( 'post' ) );
		$archive_page_body = wp_remote_retrieve_body( $archive_page );

		return array(
			'home_page'        => is_wp_error( $home_page_body ) ? 'error' : $home_page_body,
			'most_recent_post' => is_wp_error( $most_recent_post_body ) ? 'error' : $most_recent_post_body,
			'archive_page'     => is_wp_error( $archive_page_body ) ? 'error' : $archive_page_body,
		);
	}

}