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
		$this->set_description( 'The HTML data source provides the raw, server-rendered HTML of three representative front-end pages of the site, as retrieved over HTTP by an unauthenticated visitor: the home page, the most recently published post, and the post-type archive page. This is the markup before any client-side JavaScript runs.' );
		$this->set_data_shape( 'The data is a JSON object with exactly three string keys: "home_page", "most_recent_post" and "archive_page". Each value is the full HTML document returned for that page. Any of these strings may be empty if the page could not be retrieved.' );
		$this->set_analysis_strategy( 'Inspect the markup of each page for common front-end performance problems: render-blocking scripts and stylesheets in the <head>, missing async/defer on scripts, large or unoptimized inline scripts and styles, missing image dimensions, lack of lazy-loading on below-the-fold images, missing preconnect/preload hints, and excessive third-party embeds. Identify the origin of each asset: files under /wp-content/plugins/<plugin-slug>/ or /wp-content/themes/<theme-slug>/ belong to that plugin or theme, while assets on other domains are third-party scripts and should each be assessed individually for their performance cost. Prioritize issues that correspond to failing audits from the earlier Lighthouse step, and reference the specific tag or asset URL from the HTML when describing a problem. Only report issues you can actually see in the provided markup.' );
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
