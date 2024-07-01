<?php
/**
 * A class describing the HTML data source.
 */

 class Performance_Wizard_Data_Source_HTML extends Performance_Wizard_Data_Source_Base {
	/**
	 * The name of the data source.
	 */
	private $name = "HTML";

	/**
	 * The description of the data source.
	 */
	private $description = "The HTML data source provides the HTML of the website as retrieved from the front end by an unauthenticated user.";

	/**
	 * The prompt to use when passing the data to the AI agent.
	 */
	private $prompt = "This data source provides the HTML data for the site. The URL of the site you are testing is ${website}.";

	/**
	 * A plain text description of the shape of the data returned from the data source.
	 */
	private $data_shape = "The data returned from the HTML data source is a string containing the HTML of the website.";

	/**
	 * The description of a strategy that can be used to analyze this data source.
	 */
	private $analysis_strategy = "The HTML data source can be analyzed by looking for common performance issues in the HTML. Particular attention can be paid to issues identified in the Lighthouse audit at the beginning of the analysis. Files loaded from a WordPress plugin or theme can typically be identified by their path: /wp-content/plugins/<plugin-slug> or /wp-content/themes/<theme-slug>. This analysis is for the website ${website}. so scripts from other domains (so called 'third party scripts') should be reviewed individually for their potential performance impact.";
 }