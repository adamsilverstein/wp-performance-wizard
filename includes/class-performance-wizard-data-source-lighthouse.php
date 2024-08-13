<?php
/**
 * A class describing the lighthouse data source.
 *
 * @package wp-performance-wizard
 */

/**
 * Define the Lighthouse data source class.
 */
class Performance_Wizard_Data_Source_Lighthouse extends Performance_Wizard_Data_Source_Base {

		/**
		 * Construct the class, setting key variables
		 */
	public function __construct() {
		parent::__construct();
		$this->set_name( 'Lighthouse' );
		$this->set_prompt( 'Gathering Lighthouse data for the site, this may take a moment.' );
		$this->set_description( "Lighthouse is an open-source, automated tool for improving the quality of web pages, including performance data. This page describes how Lighthouse weighs it's performance scores: https://developer.chrome.com/docs/lighthouse/performance/performance-scoring." );
		$this->set_analysis_strategy( 'The data returned against the website is the performance category described here: https://developers.google.com/speed/docs/insights/v5/reference/pagespeedapi/runpagespeed#response. The Lighthouse audits can indicate top opportunities for performance and provide an overall guide to making improvements. The site you are analyzing is a WordPress site, so look for common pitfalls and issues that affect WordPress sites. Assets served from WordPress plugins will include the plugin slug in their path (typically /wp-content/plugins/{slug}/path...), this information can be useful when evaluating WordPress plugins in a later step. Data will include mobile and desktop reports - compare these to note any differences that could be worth addresing. When sites have Real User Metrics available in the CrUX dataset, the API will return those as well - in this case compare the RUM metrics with the Lab metrics to discern anything noteworthy to highlight to the user. ' );
		$this->set_data_shape( 'The results include the `lighthouseResult` object which is a Lighthouse Results Object (LHR), documented here: https://github.com/GoogleChrome/lighthouse/blob/main/docs/understanding-results.md. The object structure for the audits is described here: https://github.com/GoogleChrome/lighthouse/blob/main/docs/understanding-results.md#audits. The audits themselves are open source audits run against the page and available for review here: https://github.com/GoogleChrome/lighthouse/tree/main/core/audits.' );
	}

		/**
		 * Get the data from Lighthouse.
		 *
		 * @return mixed The data from the data source.
		 */
	public function get_data() {
		// Get the data from the PageSpeed Insights API.
		$site_url     = apply_filters( 'wp_performance_wizard_site_url', get_site_url() );
		$api_base     = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
		$query_params = array(
			'url'      => $site_url,
			'category' => 'performance',
		);

		// Log this request.

		$response = wp_remote_get(
			add_query_arg( $query_params, $api_base ),
			array(
				'timeout' => 3 * 60,
			)
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {

			return $response;
		}

		// Return the data.
		$results = wp_remote_retrieve_body( $response );

		// Remove binary image blobs starting with "data:image/*"".
		// User a regex to replace all instances of the pattern. Replace with a placeholder.
		$results = preg_replace( '/(data:image\/[^"]*)/', 'BINARY_DATA_REMOVED', $results );

		// Log the results.

		return $results;
	}
}
