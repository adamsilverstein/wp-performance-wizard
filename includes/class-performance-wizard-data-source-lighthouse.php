<?php
/**
 * A class describing the lighthouse data source.
 */

 class Performance_Wizard_Data_Source_Lighthouse extends Performance_Wizard_Data_Source_Base {
		// Properties

		/**
		* The name of the data source.
		*
		* @var string
		*/
		private $name = "Lighthouse";

		/**
		* The description of the data source.
		*
		* @var string
		*/
		private $description = "Lighthouse is an open-source, automated tool for improving the quality of web pages. You can run it against any web page, public or requiring authentication. It has audits for performance, accessibility, progressive web apps, SEO and more.

		The PageSpeed Insights API is used to collect Lighthouse data. This API is described here: https://developers.google.com/speed/docs/insights/v5/about.

		This page describes how Lighthouse weighs it's performance scores: https://developer.chrome.com/docs/lighthouse/performance/performance-scoring

		";

		/**
		* The prompt to use when passing the data to the AI agent.
		*
		* @var string
		*/
		private $prompt = "This data source provides the Lighthouse data for the site.";

		/**
		 * The description of a strategy that can be used to analyze this data source.
		 */
		private $analysis_strategy  = "The data returned is a PageSpeed Insights run against the website as described here: https://developers.google.com/speed/docs/insights/v5/reference/pagespeedapi/runpagespeed#response. Only the performance category is run. The results include the `lighthouseResult` object which is a Lighthouse Results Object (LHR), documented here: https://github.com/GoogleChrome/lighthouse/blob/main/docs/understanding-results.md. The object structure for the audits is described here: https://github.com/GoogleChrome/lighthouse/blob/main/docs/understanding-results.md#audits. The audits themselves are open source audits run against the page and available for review here: https://github.com/GoogleChrome/lighthouse/tree/main/core/audits. The Lighthouse audits can indicate top opportunities for performance and provide an overall ";

		/**
		* Get the data from the data source.
		*
		* @return mixed The data from the data source.
		*/
		public function get_data() {
			// To be implemented by subclasses.
		}


 }