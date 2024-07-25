<?php
/**
 * A class describing the lighthouse data source.
 */

class Performance_Wizard_Data_Source_Lighthouse extends Performance_Wizard_Data_Source_Base {

		/**
		 * Construct the class, setting key variables
		 */
		function __construct() {
			parent::__constructor();
			$this->set_name( 'Lighthouse' );
			$this->set_prompt( 'Gathering Lighthouse data for the site, this may take a moment.' );
			$this->set_description( "Lighthouse is an open-source, automated tool for improving the quality of web pages. You can run it against any web page, public or requiring authentication. It has audits for performance, accessibility, progressive web apps, SEO and more. The PageSpeed Insights API is used to collect Lighthouse data. This API is described here: https://developers.google.com/speed/docs/insights/v5/about. This page describes how Lighthouse weighs it's performance scores: https://developer.chrome.com/docs/lighthouse/performance/performance-scoring" );
			$this->set_analysiss_strategy( 'The data returned is a PageSpeed Insights run against the website as described here: https://developers.google.com/speed/docs/insights/v5/reference/pagespeedapi/runpagespeed#response. Only the performance category is run. The results include the `lighthouseResult` object which is a Lighthouse Results Object (LHR), documented here: https://github.com/GoogleChrome/lighthouse/blob/main/docs/understanding-results.md. The object structure for the audits is described here: https://github.com/GoogleChrome/lighthouse/blob/main/docs/understanding-results.md#audits. The audits themselves are open source audits run against the page and available for review here: https://github.com/GoogleChrome/lighthouse/tree/main/core/audits. The Lighthouse audits can indicate top opportunities for performance and provide an overall' );
		}

		/**
		* Get the data from Lighthouse.
		*
		* @return mixed The data from the data source.
		*/
		public function get_data() {
			// Get the data from the PageSpeed Insights API.
			$site_url = wp_performance_wizard_get_site_url();
			$api_base = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
			$query_params = array(
				'url' => $site_url,
				'category' => 'performance',
			);

			// Log this request.
			error_log( 'Requesting Lighthouse data from ' . $site_url );

			$response = wp_remote_get(
				add_query_arg( $query_params, $api_base ),
					array(
						'timeout' => 3 * 60,
					)
				);

			// Check for errors.
			if ( is_wp_error( $response ) ) {
				error_log( 'Error getting Lighthouse data: ' . $response->get_error_message() );
				return $response;
			}

			// Return the data.
			$results = wp_remote_retrieve_body( $response );

			// Remove binary image blobs starting with "data:image/*"".
			// User a regex to replace all instances of the pattern. Replace with a placeholder.
			$results = preg_replace( '/(data:image\/[^"]*)/', 'BINARY_DATA_REMOVED', $results );


			// Log the results.
			error_log( 'Lighthouse data: ' . $results );

			return $results;
		}
}