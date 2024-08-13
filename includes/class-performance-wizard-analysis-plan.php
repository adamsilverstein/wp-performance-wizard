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
	 * Debug mode: when this is true, the plan will skip all costly API calls.
	 *
	 * @var bool
	 */
	private $debug_mode = false;

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
		'Performance_Wizard_Data_Source_Lighthouse'         => 'class-performance-wizard-data-source-lighthouse.php',
		'Performance_Wizard_Data_Source_HTML'               => 'class-performance-wizard-data-source-html.php',
		'Performance_Wizard_Data_Source_Themes_And_Plugins' => 'class-performance-wizard-data-source-themes-and-plugins.php',
	);

	/**
	 * The prompt to send before each data point analysis,
	 *
	 * @var string
	 */
	private $data_point_prompt = 'You will now analyze a new data point. Remember the analysis for this data point so you can refer to it in future steps.';

	/**
	 * The prompt to send after each data point analysis.
	 *
	 * @var string
	 */
	private $data_point_summary_prompt = 'Analyze the data, while also considering analysis from previous steps. Provide a high level summary of the information received - 2-3 paragraphs at most - and how it reflects on the performance of the site. Highlight the most important findings.';


	/**
	 * The system instructions to pass to the agent when setting up.
	 *
	 * @var string
	 */
	private $system_instructions =
"As a web performance expert, you will analyze provided data points and give a summary and recommendations for each step. You will retain information from each step and provide an overall summary and set of actionable recommendations with testing methods at the end. You will not hallucinate or make up facts about the site. If you don't know or something you will say so. Use plain language an average developer or site builder would understand. Use a professional, positive and friendly tone. Only discuss performance related issues. Do not discuss security, design, or other non-performance related issues.

**Data Point Analysis:**

1. **Receive Data:** Receive and carefully review each data point about the website's performance.

2. **Summarize Findings:** Analyze the data point and summarize its meaning in the context of website performance. Explain the potential impact on user experience and overall site speed.

3. **Recommend Improvements:** Provide specific and actionable recommendations on how to address the identified performance issues based on the data point. Explain the rationale behind each suggestion and the potential benefits.

4. **Remember Context:** Store the findings, summaries, and recommendations for each data point to build a comprehensive understanding of the website's performance profile.

**Overall Assessment and Recommendations:**

1. **Consolidate Findings:** Review all analyzed data points and their respective findings to identify common themes and recurring issues.

2. **Prioritize Recommendations:** Rank the suggested improvements based on their potential impact on overall website performance and user experience. Consider factors such as feasibility, cost, and implementation time.

3. **Present Actionable Plan:** Provide a clear and concise summary of the website's performance strengths and weaknesses. Offer a set of prioritized and actionable recommendations for improvement, outlining the steps required for implementation.

4. **Testing Strategy:** Suggest specific methods to measure the effectiveness of the implemented changes. Include key performance indicators (KPIs) and tools to monitor the impact on metrics such as page load times, bounce rates, and conversion rates.

**Example Data Point:**

* **Data:** The Time to First Byte (TTFB) is 500ms.

* **Summary:** The TTFB indicates a delay in server response time, impacting the initial page loading speed and user experience.

* **Recommendations:**

    * Contact Form 7 loads it's JavaScript on every page. Consider switching to a more lightweight form plugin.

	* Optimize server-side responsiveness by adding a full page caching solution.

    * Consider using a Content Delivery Network (CDN) to reduce latency.

    * Consider adding an image CDN solution to serve optimized images.

    * Test the impact of caching mechanisms on the server.

* **Testing:** Monitor the TTFB after implementing changes using web performance tools like WebPageTest or Google PageSpeed Insights.
";

	/**
	 * Get the system instructions for the AI agent.
	 *
	 * @return string The system instructions.
	 */
	public function get_system_instructions(): string {
		return $this->system_instructions;
	}

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
			'user_prompt' => 'Considering all of the analysis of the previous steps, provide recommendations for improving the performance of the site. This response can be several paragraphs long. First, summarize all of the findings. Next, list the top recommendations for improving the performance of the site. For each point, refer to the plugin that could be causing the issue. Each issue should also be rooted in a specific failing Lighhouse audit - state which audit or problem it is aiming to fix. Do not provide generic recommendations like "consider adding caching". Instead, always provide specific recommendations such as "Try installing a full page caching solution like WP Fastest Cache". Finally, provide a testing strategy for measuring the impact of the recommendations.',
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
		if ( ! isset( $this->steps[ $step ] ) ) {
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
		$response       = $this->debug_mode ? '{debug}' : $this->wizard->get_ai_agent()->send_prompts( $prompts, $this->current_step, $previous_steps );
		if ( $this->debug_mode ) {
			sleep (1);
		}

		$q_and_a        = array(
			'>Q: ' . implode( "<br>", $prompts_for_user ),
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
		if ( '' !== $prompt ) {
			array_push( $prompts, $prompt );
			array_push( $prompts_for_user, $prompt );
		}

		$description = $data_source->get_description();
		if ( '' !== $description ) {
			array_push( $prompts, $description );
			array_push( $prompts_for_user, $description );
		}
		// Send the data to the AI agent.
		$data = $this->debug_mode ? '{debug}' : $data_source->get_data();
		if ( $this->debug_mode ) {
			sleep (1);
		}
		if ( '' !== $data ) {
			$prompt            = '';
			$prompt           .= 'Here is the data: ' . $data . "<br>";
			$for_user          = 'Here is the data: {DATA}' . "<br>"; // A string to show to the user.
			$data_shape        = $data_source->get_data_shape();
			$analysis_strategy = $data_source->get_analysis_strategy();
			$prompt           .= '' !== $data_shape ? '' : 'Here is the data shape: ' . $data_shape . "<br>";
			$for_user         .= '' !== $data_shape ? '' : 'Here is the data shape: ' . $data_shape . "<br>";
			$prompt           .= '' !== $analysis_strategy ? '' : 'Here is the analysis strategy: ' . $analysis_strategy . "<br>";
			$for_user         .= '' !== $analysis_strategy ? '' : 'Here is the analysis strategy: ' . $analysis_strategy . "<br>";

			array_push( $prompts, $prompt );
			array_push( $prompts_for_user, $for_user );
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
