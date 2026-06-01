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
		$this->set_prompt( 'Gathering Lighthouse data for each selected page type, this may take a moment per page type.' );
		$this->set_description( "Lighthouse is an open-source, automated tool for measuring web page quality, including performance. This data point contains the Lighthouse 'performance' category result fetched from the Google PageSpeed Insights API for one or more page types on the site, controlled by the plugin's Page Types setting (home page, posts archive, and/or most recently published post). Each result contains lab metrics (such as First Contentful Paint, Largest Contentful Paint, Total Blocking Time, Cumulative Layout Shift, and Speed Index), an overall performance score, and the individual performance audits with their scores and details. Note that base64-encoded inline image data has been stripped and replaced with the placeholder 'BINARY_DATA_REMOVED' to keep the payload small, so do not treat that placeholder as a finding. This page describes how Lighthouse weighs its performance score: https://developer.chrome.com/docs/lighthouse/performance/performance-scoring." );
		$this->set_analysis_strategy( 'The PageSpeed Insights response shape is documented here: https://developers.google.com/speed/docs/insights/v5/reference/pagespeedapi/runpagespeed#response. Start from the overall performance score and the failing or low-scoring audits, since these indicate the highest-impact opportunities. For each notable failing audit, name the audit and the specific items it flags (URLs, scripts, images, or DOM nodes) and quantify the potential savings the audit reports. The site is a WordPress site, so look for WordPress-specific causes; assets served from a plugin or theme include the slug in their path (typically /wp-content/plugins/{slug}/... or /wp-content/themes/{slug}/...), which you should use to attribute issues to a specific plugin or theme in this and later steps. If a result includes a loadingExperience or originLoadingExperience field, that is real-user (CrUX) field data - compare it against the lab metrics and call out meaningful divergence, but do not assume field data is present if those fields are absent. When more than one page type is included, compare scores and failing audits across page types and call out template-specific regressions or improvements, attributing each finding to the specific page type label.' );
		$this->set_data_shape( 'The data is a JSON object whose keys are the selected page type slugs ("home", "archive", and/or "post"). Each value is an object with three fields: "url" (the URL analyzed), "label" (a short human-readable name for the page type), and "result" - the raw PageSpeed Insights API response for that URL. The PSI response includes the `lighthouseResult` object which is a Lighthouse Results Object (LHR), documented here: https://github.com/GoogleChrome/lighthouse/blob/main/docs/understanding-results.md. The object structure for the audits is described here: https://github.com/GoogleChrome/lighthouse/blob/main/docs/understanding-results.md#audits. The audits themselves are open source audits run against the page and available for review here: https://github.com/GoogleChrome/lighthouse/tree/main/core/audits. When a URL could not be resolved or the API request failed, "result" is an object with a single "error" field describing the problem.' );
	}

		/**
		 * Get the Lighthouse data for each selected page type.
		 *
		 * @return string JSON encoded string of the multi-page Lighthouse data.
		 */
	public function get_data(): string {
		// Each PSI call can take up to 3 minutes, so when several page types
		// are configured the total runtime can easily exceed PHP's default
		// max execution time. set_time_limit() silently returns false when
		// disabled by the host, which is the desired no-op behaviour there.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$page_types = Performance_Wizard_Settings_Page::page_types();
		$collected  = array();
		$cache      = array();

		foreach ( $page_types as $page_type ) {
			$url   = Performance_Wizard_Settings_Page::get_page_type_url( $page_type );
			$label = isset( Performance_Wizard_Settings_Page::SUPPORTED_PAGE_TYPES[ $page_type ] )
				? Performance_Wizard_Settings_Page::SUPPORTED_PAGE_TYPES[ $page_type ]
				: $page_type;

			if ( '' === $url ) {
				$result = array( 'error' => 'No URL could be resolved for this page type.' );
			} elseif ( array_key_exists( $url, $cache ) ) {
				$result = $cache[ $url ];
			} else {
				$result        = $this->fetch_lighthouse_for_url( $url );
				$cache[ $url ] = $result;
			}

			$collected[ $page_type ] = array(
				'url'    => $url,
				'label'  => $label,
				'result' => $result,
			);
		}

		$encoded = wp_json_encode( $collected );
		return false === $encoded ? '{}' : $encoded;
	}

	/**
	 * Fetch the raw PageSpeed Insights response for a single URL.
	 *
	 * Returns the decoded array on success and an `{error: string}` array when
	 * the call fails, so the multi-URL caller can continue with the remaining
	 * page types.
	 *
	 * @param string $url The URL to analyze.
	 *
	 * @return array<string,mixed> Decoded PSI response, or an error array.
	 */
	private function fetch_lighthouse_for_url( string $url ): array {
		$api_base     = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
		$query_params = array(
			'url'      => $url,
			'category' => 'performance',
		);

		// Authenticate the request when a key is configured. Anonymous requests
		// share a near-zero global quota and fail with an HTTP 429
		// "rateLimitExceeded" error; a key grants the free per-project quota.
		$api_key = Performance_Wizard_Settings_Page::pagespeed_api_key();
		if ( '' !== $api_key ) {
			$query_params['key'] = $api_key;
		}

		$response = wp_remote_get(
			add_query_arg( $query_params, $api_base ),
			array(
				'timeout' => 3 * 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $response_code ) {
			$message = '' === $api_key
				? __( 'The PageSpeed Insights API returned an HTTP 429 quota error. Anonymous (unauthenticated) requests share a near-zero daily quota. Add a free PageSpeed Insights API key under Performance Wizard > Settings to get the standard 25,000 requests/day allowance.', 'wp-performance-wizard' )
				: __( 'The PageSpeed Insights API returned an HTTP 429 quota error for the configured API key. The key has exhausted its daily quota, or the PageSpeed Insights API is not enabled for its Google Cloud project. Verify the key in the Google Cloud console and try again later.', 'wp-performance-wizard' );
			return array( 'error' => $message );
		}

		// Normalize any other non-2xx response into an error so a failed request
		// is not mistaken for a valid result object once JSON-decoded below.
		if ( $response_code < 200 || $response_code >= 300 ) {
			return array(
				'error' => sprintf(
					/* translators: 1: HTTP status code, 2: requested URL. */
					__( 'The PageSpeed Insights API request for %2$s failed with HTTP %1$d.', 'wp-performance-wizard' ),
					$response_code,
					$url
				),
			);
		}

		$body = wp_remote_retrieve_body( $response );

		// Remove base64-encoded inline image blobs to keep the payload small.
		$cleaned = preg_replace( '/(data:image\/[^"]*)/', 'BINARY_DATA_REMOVED', $body );
		$body    = is_string( $cleaned ) ? $cleaned : $body;

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return array( 'error' => 'Could not decode PageSpeed Insights response for ' . $url );
		}

		return $decoded;
	}
}
