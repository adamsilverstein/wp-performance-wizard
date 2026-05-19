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
		$this->set_description( "Lighthouse is an open-source, automated tool for measuring web page quality, including performance. This data point is the Lighthouse 'performance' category result for the site's home page, fetched from the Google PageSpeed Insights API. It contains lab metrics (such as First Contentful Paint, Largest Contentful Paint, Total Blocking Time, Cumulative Layout Shift, and Speed Index), an overall performance score, and the individual performance audits with their scores and details. Note that base64-encoded inline image data has been stripped and replaced with the placeholder 'BINARY_DATA_REMOVED' to keep the payload small, so do not treat that placeholder as a finding. This page describes how Lighthouse weighs its performance score: https://developer.chrome.com/docs/lighthouse/performance/performance-scoring." );
		$this->set_analysis_strategy( 'The response shape is documented here: https://developers.google.com/speed/docs/insights/v5/reference/pagespeedapi/runpagespeed#response. Start from the overall performance score and the failing or low-scoring audits, since these indicate the highest-impact opportunities. For each notable failing audit, name the audit and the specific items it flags (URLs, scripts, images, or DOM nodes) and quantify the potential savings the audit reports. The site is a WordPress site, so look for WordPress-specific causes; assets served from a plugin or theme include the slug in their path (typically /wp-content/plugins/{slug}/... or /wp-content/themes/{slug}/...), which you should use to attribute issues to a specific plugin or theme in this and later steps. If the response includes a loadingExperience or originLoadingExperience field, that is real-user (CrUX) field data - compare it against the lab metrics and call out meaningful divergence, but do not assume field data is present if those fields are absent.' );
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
