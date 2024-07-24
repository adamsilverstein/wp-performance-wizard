<?php
/**
 * A class that encapulates the strategy to be used by the AI agent when
 * analyzing the performance of a WordPress site. Describes the steps the agernt will use
 * and the prompts it will use to gather data.
 *
 * Includes functions matching the rest api endpoints: one to start the process, one to
 * retrieve the next step, and one to run the next step.
 */

class Performance_Wizard_Analysis_Plan {

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
	 * Track the steps the plan will follow.
	 */
	private $steps = array();

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
	private $primary_prompt = 'You will play the role of a web performance expert. You will receive a series of data points about
	the website you are analyzing. For each data point, you will provide a summary of the information received and how it reflects
	on the performance of the site. For each step, you will offer recommendations for how the performance might be improved.
	You will remember the results of each step and at the end of the process, you will provide an overall summary and set of recommendations
	for how the site performance might be improved. You will assist the user in making threse changes, then repeat the performance
	analysis steps, comparing the new results with the previous results.';

	/**
	 * The prompt to send before each data point analysis,
	 *
	 * @var string
	 */
	private $data_point_prompt = 'You will now analyze a data point.';

	/**
	 * The prompt to send after each data point analysis.
	 *
	 * @var string
	 */
	private $data_point_summary_prompt = 'Please provide a summary of the information received and how it reflects on the performance of the site.';



	/**
	 * Keep a handle on the base wizard class.
	 *
	 * @var Performance_Wizard
	 */
	private $wizard;

	/**
	 * Construct the class, setting up the plan.
	 */
	function __construct( $wizard ) {
		$this->wizard = $wizard;
		require_once plugin_dir_path( __FILE__ ) . 'class-performance-wizard-data-source-base.php';

		$this->set_up_plan();
	}

	/**
	 * Set up the plan.
	 *
	 * Each step of of the plan will be constructed of the following data:
	 * - A title for the step
	 * - A user_prompt to show to the user
	 * - A data source to use for the step
	 */
	public function set_up_plan() {
		$steps = array();

		// The first step is to introduce the user to the process.
		$steps[] = array(
			'title'       => 'Introduction',
			'user_prompt' => 'The Performance Wizard will analyze the performance of your WordPress site.',
			'action'      => 'continue',
		);

		// Next, add a step for each data source.
		foreach ( $this->data_sources as $source_name => $data_source ) {
		include_once plugin_dir_path( __FILE__ ) . $data_source;
			$source = new $source_name();
			$steps[] = array(
				'title'       => $source->get_name(),
				'user_prompt' => $source->get_user_prompt(),
				'source'      => $source,
				'action'      => 'run_action',
			);
		}
		error_log( json_encode( $steps, JSON_PRETTY_PRINT ) );

		// Finally, add the wrap up step.
		$steps[] = array(
			'title'       => 'Wrap Up',
			'user_prompt' => 'I have analyzed the performance of your WordPress site. Here are my recommendations.',
			'source'      => null,
			'action'      => 'complete',
		);

		$this->steps = $steps;
	}


	/**
	 * Get the next action in the analysis process.
	 *
	 * @param int $step The current step in the process.
	 */
	public function get_next_action( $step ) {
		$step = $this->steps[ $step ];
		return $step;
	}

	/**
	 * Start the process.
	 *
	 */
	public function start() {
	}

	/**
	 * Run the next action in the analysis process.
	 *
	 * @param int $step The current step in the process.
=	 *
	 * @return mixed The result of the action.
	 */
	public function run_action( $step ) {
		if ( empty( $this->steps[ $step ] ) ) {
			return 'No more steps to run.';
		}
		$action = $this->steps[ $step ];
		return $this->do_run_action( $action );
	}

	/**
	 * Sent a prompt, adding it to the conversation.
	 *
	 * @param string $prompt       The prompt to send.
	 * @param array  $conversation The conversation to add the prompt to.
	 *
	 * @return array The updated conversation.
	 */
	private function send_prompt_with_conversation( $prompt, $conversation ) {
		$response = $this->wizard->get_ai_agent()->send_prompt( $prompt );
		array_push( $conversation,
			'>Q: ' . $prompt,
			'>A: ' . $response
		);
		return $conversation;
	}

	/**
	 * Run an action in the analysis process.
	 */
	private function do_run_action( $action ) {

		$data_source = $action['source'];
		$conversation = [];

		// Send the before data analysis prompt.
		$prompt = $this->data_point_prompt;
		$this->send_prompt_with_conversation( $prompt, $conversation );

		// Get the prompt to use when passing the data to the AI agent.
		$prompt = $data_source->get_prompt();
		$this->send_prompt_with_conversation( $prompt, $conversation );

		// Send the data to the AI agent.
		// @todo this can run async
		$prompt = $data_source->get_data();
		$this->send_prompt_with_conversation( $prompt, $conversation );

		// Get the plaintext description of how to process the data.
		$prompt = $data_source->get_description();
		$this->send_prompt_with_conversation( $prompt, $conversation );

		// Get the shape of the data returned from the data source.
		$data_shape = $data_source->get_data_shape();

		if ( ! empty( $data_shape ) ) {
			$prompt = 'Data shape: ' . $data_shape;
			$this->send_prompt_with_conversation( $prompt, $conversation );
		}

		// Get the description of a strategy that can be used to analyze this data source.
		$analysis_strategy = $data_source->get_analysis_strategy();

		if ( ! empty( $analysis_strategy ) ) {
			$prompt = 'Analysis strategy: ' . $analysis_strategy;
			$this->send_prompt_with_conversation( $prompt, $conversation );
		}

		// Send the post data analysis prompt.
		$prompt = $this->data_point_summary_prompt;
		$this->send_prompt_with_conversation( $prompt, $conversation );

		return $conversation;
	}

	/**
	 * Pass a prompt to the agent.
	 *
	 * @param string $prompt The prompt to pass to the agent.
	 *
	 */
	public function prompt( $prompt ) {
		return 'OK';
	}


		// Send the wrap up prompt to the agent asking it to synthesize all of the data points.

		// Ask the agent for its final analysis.



}