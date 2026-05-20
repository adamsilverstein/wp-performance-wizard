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
		$this->set_prompt( 'Collecting HTML source for the site for each selected page type (home, archive, and/or most recent post).' );
		$this->set_description( 'The HTML data source provides the raw, server-rendered HTML of one or more representative front-end page types of the site, as retrieved over HTTP by an unauthenticated visitor. Which page types are included is configurable in the plugin settings (home page, posts archive, and/or most recently published post). This is the markup before any client-side JavaScript runs.' );
		$this->set_data_shape( 'The data is a JSON object whose keys are the selected page types from the set "home", "archive", and "post" (only the selected types are present). Each value is an object with three string fields: "url" (the URL fetched), "label" (a short human-readable name for the page type), and "html" (the full HTML document returned for that URL, or an empty string when the page could not be retrieved or no URL could be resolved).' );
		$this->set_analysis_strategy( 'Inspect the markup of each included page type for common front-end performance problems: render-blocking scripts and stylesheets in the <head>, missing async/defer on scripts, large or unoptimized inline scripts and styles, missing image dimensions, lack of lazy-loading on below-the-fold images, missing preconnect/preload hints, and excessive third-party embeds. Identify the origin of each asset: files under /wp-content/plugins/<plugin-slug>/ or /wp-content/themes/<theme-slug>/ belong to that plugin or theme, while assets on other domains are third-party scripts and should each be assessed individually for their performance cost. When more than one page type is included, compare across them and call out template-specific issues - for example a problem present on the post template but absent from the home template. Prioritize issues that correspond to failing audits from the earlier Lighthouse step, and reference the specific tag or asset URL from the HTML when describing a problem. Only report issues you can actually see in the provided markup.' );
	}

	/**
	 * Get the HTML of each selected page type and return in a structured data object.
	 *
	 * The set of page types is configurable via the plugin's Settings page and
	 * the `wp_performance_wizard_page_types` filter.
	 *
	 * @return string JSON encoded string of the HTML data.
	 */
	public function get_data(): string {
		$page_types = Performance_Wizard_Settings_Page::page_types();
		$to_return  = array();
		$cache      = array();

		foreach ( $page_types as $page_type ) {
			$url   = Performance_Wizard_Settings_Page::get_page_type_url( $page_type );
			$label = isset( Performance_Wizard_Settings_Page::SUPPORTED_PAGE_TYPES[ $page_type ] )
				? Performance_Wizard_Settings_Page::SUPPORTED_PAGE_TYPES[ $page_type ]
				: $page_type;
			$body  = '';

			if ( '' !== $url ) {
				if ( array_key_exists( $url, $cache ) ) {
					$body = $cache[ $url ];
				} else {
					$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
					if ( ! is_wp_error( $response ) ) {
						$body = wp_remote_retrieve_body( $response );
					}
					$cache[ $url ] = $body;
				}
			}

			$to_return[ $page_type ] = array(
				'url'   => $url,
				'label' => $label,
				'html'  => $body,
			);
		}

		$encoded = wp_json_encode( $to_return );
		return false === $encoded ? '{}' : $encoded;
	}
}
