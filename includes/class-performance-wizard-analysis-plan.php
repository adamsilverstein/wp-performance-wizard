<?php
/**
 * A class that encapulates the strategy to be used by the AI agent when
 * analyzing the performance of a WordPress site. Describes the steps the agernt will use
 * and the prompts it will use to gather data.
 */

class Performance_Wizard_Analysis_Plan {
	// Properties

	/**
	 * The name of the analysis plan.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The description of the analysis plan.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * The data sources to be used by the AI agent when analyzing the performance of a WordPress site.
	 *
	 * @var array
	 */
	private $data_sources = array (
		'Performance_Wizard_Data_Source_Lighthouse'         => 'class-performance-wizard-data-source-lighthouse.php',
		'Performance_Wizard_Data_Source_HTML'               => 'class-performance-wizard-data-source-html.php',
		'Performance_Wizard_Data_Source_Themes_And_Plugins' => 'class-performance-wizard-data-source-themes-and-plugins.php',
	);

	/**
	 * An array of agents available to use for this analysis plan.
	 *
	 * @var array
	 */
	private $agents = array (
		'Performance_Wizard_AI_Agent_Gemini' => 'class-performance-wizard-ai-agent-gemini.php',
	);

	/**
	 * The prompts to use when interacting with the user.
	 *
	 * @var array
	 */
	private $user_prompts = array(
		'Welcome to the Performance Wizard. I will analyze the performance of your WordPress site.',
		'The analysis will follow a series of steps and I will report on each step as I progress.',
	);

	/**
	 * The primary prompt is used to set up the LLM, instructing it on its goals, behavior and the steps it will use.
	 *
	 * @var string
	 */
	private $primary_prompt = "You will play the role of a web performance expert. You will receive a series of data points about
	the website you are analyzing. For each data point, you will provide a summary of the information received and how it reflects
	on the performance of the site. For each step, you will offer recommendations for how the performance might be improved.
	You will remember the results of each step and at the end of the process, you will provide an overall summary and set of recommendations
	for how the site performance might be improved. You will assist the user in making threse changes, then repeat the performance
	analysis steps, comparing the new results with the previous results."


	/**
	 * Run the analysis process.
	 */
	public function run_analysis() {

		// Connect to the AI agent.
		include_once plugin_dir_path( __FILE__ ) . $this->agents['Performance_Wizard_AI_Agent_Gemini'];
		$agent = new Performance_Wizard_AI_Agent_Gemini();
		$agent->set_api_key( 'YOUR_API_KEY' );




		// Send the initial prompt to the agent explaining the process.

		// Collect the data sources and feed each of them to the agent.
		foreach ( $this->data_sources as $source_name => $data_source ) {

			// Load the data source and set it up.

			// Data_source is the path inside the plugin to the data source class file.
			include_once plugin_dir_path( __FILE__ ) . $data_source;

			$source = new $source_name();

			// Get the data from the data source.
			// @todo this can run async
			$data = $data_source->get_data();

			// Get the prompt to use when passing the data to the AI agent.
			$prompt = $data_source->get_prompt();

			// Send the prompt to the AI agent.

			// Get the plaintext description of how to process the data.
			$description = $data_source->get_description();

			// Send the plaintext description of how to process the data to the AI agent.

			// Get the shape of the data returned from the data source.
			$data_shape = $data_source->get_data_shape();

			// Send the shape of the data returned from the data source to the AI agent.

			// Get the description of a strategy that can be used to analyze this data source.
			$analysis_strategy = $data_source->get_analysis_strategy();

			// Send the description of a strategy that can be used to analyze this data source to the AI agent.

			// Ask the agent for its analysis so far

		}

		// Send the wrap up prompt to the agent asking it to synthesize all of the data points.

		// Ask the agent for its final analysis.

	}

}