<?php
/**
 * A class describing analysis plan.
 *
 * @package wp-performance-wizard
 */

/**
 * A class that organizes the strategy to be used by the AI agent when
 * analyzing the performance of a WordPress site. Describes the steps the agent will use
 * and the prompts it will use to gather data.
 *
 * Includes functions matching the rest api endpoints: one to start the process, one to
 * retrieve the next step, and one to run the next step.
 */
class Performance_Wizard_Analysis_Plan {

	/**
	 * The current step in the process.
	 *
	 * @var int
	 */
	private $current_step = 0;

	/**
	 * Track the steps the plan will follow.
	 *
	 * @var array
	 */
	private $steps = array();

	/**
	 * The data sources to be used by the AI agent when analyzing the performance of a WordPress site.
	 *
	 * @var array
	 */
	private $data_sources = array(
		'Performance_Wizard_Data_Source_Lighthouse' => 'class-performance-wizard-data-source-lighthouse.php',
		'Performance_Wizard_Data_Source_HTML'       => 'class-performance-wizard-data-source-html.php',
		'Performance_Wizard_Data_Source_Themes_And_Plugins' => 'class-performance-wizard-data-source-themes-and-plugins.php',
	);

	/**
	 * The primary prompt is used to set up the LLM, instructing it on its goals, behavior and the steps it will use.
	 *
	 * @var string
	 */
	private $system_instructions = 'You will play the role of a web performance expert. You will receive a series of data points about
	the website you are analyzing. For each data point, you will provide a summary of the information received and how it reflects
	on the performance of the site. For each step, you will offer recommendations for how the performance might be improved.
	You will remember the results of each step and at the end of the process, you will provide an overall summary and set of recommendations
	for how the site performance might be improved. You will assist the user in making these changes, then repeat the performance
	analysis steps, comparing the new results with the previous results.';

	/**
	 * The prompt to send before each data point analysis,
	 *
	 * @var string
	 */
	private $data_point_prompt = 'You will now analyze a data point. Remember the analysis for this data point so you can refer to it in future steps.';

	/**
	 * The prompt to send after each data point analysis.
	 *
	 * @var string
	 */
	private $data_point_summary_prompt = 'Analyze the data, while also consider analysis from previous steps, then please provide a summary of the information received and how it reflects on the performance of the site. ';

	/**
	 * Keep a handle on the base wizard class.
	 *
	 * @var WP_Performance_Wizard
	 */
	private $wizard;

	/**
	 * Helper to get the current step.
	 *
	 * @return int The current step.
	 */
	public function get_current_step(): int {
		return $this->current_step;
	}

	/**
	 * Get the system instructions for the AI agent.
	 *
	 * @return string The system instructions for the AI agent.
	 */
	public function get_system_instructions(): string {
		return $this->system_instructions;
	}

	/**
	 * Construct the class, setting up the plan.
	 *
	 * @param WP_Performance_Wizard $wizard The wizard to use.
	 */
	public function __construct( WP_Performance_Wizard $wizard ) {
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
	public function set_up_plan(): void {
		$steps = array();

		// The first step is to introduce the user to the process.
		$steps[] = array(
			'title'       => 'Introduction',
			'user_prompt' => 'The Performance Wizard will analyze the performance of your WordPress site.',
			'action'      => 'continue',
		);

		// Prompt the agent with the system instructions.
		$steps[] = array(
			'title'       => 'System Instructions',
			'user_prompt' => $this->get_system_instructions(),
			'action'      => 'prompt',
		);

		// Next, add a step for each data source.
		foreach ( $this->data_sources as $source_name => $data_source ) {
			include_once plugin_dir_path( __FILE__ ) . $data_source;
			$source  = new $source_name( $this->wizard );
			$steps[] = array(
				'title'       => $source->get_name(),
				'user_prompt' => $source->get_user_prompt(),
				'source'      => $source,
				'action'      => 'run_action',
			);
		}

		// Finally, add the wrap up steps.
		$steps[] = array(
			'title'       => 'Summarize Results',
			'user_prompt' => 'Considering all of the analysis of the previous steps, provide recommendations for improving the performance of the site.',
			'source'      => null,
			'action'      => 'prompt',
		);

		$steps[] = array(
			'title'       => 'Wrap Up',
			'user_prompt' => 'That is the end of the analysis.',
			'source'      => null,
			'action'      => 'complete',
		);

		$this->steps = $steps;
	}


	/**
	 * Get the next action in the analysis process.
	 *
	 * @param int $step The current step in the process.
	 *
	 * @return array The next step in the process.
	 */
	public function get_next_action( int $step ): array {
		$step = $this->steps[ $step ];
		return $step;
	}

	/**
	 * Start the process.
	 *
	 * @return string The result of the action.
	 */
	public function start(): string {
		return '';
	}

	/**
	 * Run the next action in the analysis process.
	 *
	 * @param int $step The current step in the process.
	 *
	 * @return mixed The result of the action.
	 */
	public function run_action( int $step ) {
		$this->current_step = $step;
		if ( empty( $this->steps[ $step ] ) ) {
			return 'No more steps to run.';
		}
		$action = $this->steps[ $step ];
		return $this->do_run_action( $action );
	}

	/**
	 * Sent a prompt, adding it to the conversation.
	 *
	 * @param array $prompts          The prompt to send.
	 * @param array $conversation     The conversation to add the prompt to.
	 * @param array $prompts_for_user The prompts to show to the user.
	 *
	 * @return string The response from the AI agent.
	 */
	private function send_prompts_with_conversation( array $prompts, array &$conversation, array $prompts_for_user ): string {
		$previous_steps = get_option( $this->wizard->get_option_name(), array() );
		$response       = $this->wizard->get_ai_agent()->send_prompts( $prompts, $this->current_step, $previous_steps );
		$q_and_a        = array(
			'>Q: ' . implode( PHP_EOL, $prompts_for_user ),
			'>A: ' . $response,
		);

		array_push( $conversation, $q_and_a[0], $q_and_a[1] );
		return $response;
	}

	/**
	 * Run an action in the analysis process.
	 *
	 * @param array $action The action to run.
	 *
	 * @return array The conversation with Q&A pairs.
	 */
	private function do_run_action( array $action ): array {

		$data_source  = $action['source'];
		$conversation = array();

		// All of these prompts need to be combined into a single request to theAPI.
		$prompts          = array();
		$prompts_for_user = array();

		// Send the before data analysis prompt.
		$prompt = $this->data_point_prompt;
		array_push( $prompts, $prompt );
		array_push( $prompts_for_user, $prompt );

		$prompt = $data_source->get_prompt();
		if ( ! empty( $prompt ) ) {
			array_push( $prompts, $prompt );
			array_push( $prompts_for_user, $prompt );
		}

		$description = $data_source->get_description();
		if ( ! empty( $description ) ) {
			array_push( $prompts, $description );
			array_push( $prompts_for_user, $description );
		}
		// Send the data to the AI agent.
		$data = $data_source->get_data();
		if ( ! empty( $data ) ) {
			$prompt            = '';
			$prompt           .= 'Here is the data: ' . $data . PHP_EOL;
			$data_shape        = $data_source->get_data_shape();
			$analysis_strategy = $data_source->get_analysis_strategy();
			$prompt           .= empty( $data_shape ) ? '' : 'Here is the data shape: ' . $data_shape . PHP_EOL;
			$prompt           .= empty( $analysis_strategy ) ? '' : 'Here is the analysis strategy: ' . $analysis_strategy . PHP_EOL;
			array_push( $prompts, $prompt );
			array_push( $prompts_for_user, 'DATA' );
		}

		// Send the post data analysis prompt.
		$prompt = $this->data_point_summary_prompt;
		array_push( $prompts, $prompt );
		array_push( $prompts_for_user, $prompt );

		$response = $this->send_prompts_with_conversation( $prompts, $conversation, $prompts_for_user );

		// Store the prompt and response for subsequent steps.
		$this->store_prompts_and_response( implode( PHP_EOL, $prompts ), $response );

		return $conversation;
	}

	/**
	 * Store the prompt and response for future visits.
	 *
	 * @param string $prompts   The prompts to store.
	 * @param string $response  The response to store.
	 */
	private function store_prompts_and_response( string $prompts, string $response ): void {
		$option_name                  = $this->wizard->get_option_name();
		$steps                        = get_option( $option_name, array() );
		$steps[ $this->current_step ] = array(
			'prompts'  => $prompts,
			'response' => $response,
		);
		update_option( $option_name, $steps );
	}
}
