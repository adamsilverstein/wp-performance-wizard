<?php
/**
 * A class that enables connections to Gemini AI.
 *
 * @package wp-performance-wizard
 */

/**
 * The Gemini class.
 */
class Performance_Wizard_AI_Agent_Gemini extends Performance_Wizard_AI_Agent_Base {

	/**
	 * A method to send a single prompt to the agent.
	 *
	 * @param string   $prompt         The prompt to pass to the agent.
	 * @param int      $current_step   The current step in the process.
	 * @param string[] $previous_steps The previous steps in the process.
	 *
	 * @return string The response from the API.
	 */
	public function send_prompt( string $prompt, int $current_step, array $previous_steps ): string {
		return $this->send_prompts( array( $prompt ), $current_step, $previous_steps );
	}

	/**
	 * A method for calling the API of the AI agent.
	 *
	 * @param array $prompts        The prompts to pass to the agent.
	 * @param int   $current_step   The current step in the process.
	 * @param array $previous_steps The previous steps in the process.
	 *
	 * @return string The response from the API.
	 */
	public function send_prompts( array $prompts, int $current_step, array $previous_steps ): string {

		// Send a REST API request to the Gemini API, as documented here: https://ai.google.dev/gemini-api/docs/get-started/tutorial?lang=rest.
		$api_base     = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';
		$query_params = array(
			'key' => $this->get_api_key(),
		);

		$parts = array(
			'text' => implode( PHP_EOL, $prompts ),
		);

		$contents  = array();
		$max_steps = $current_step;
		for ( $i = 1; $i < $max_steps; $i++ ) {
			$step = $previous_steps[ $i ];
			if ( ! empty( $step['prompts'] ) ) {
				array_push(
					$contents,
					array(
						'parts' => array(
							'text' => $step['prompts'],
						),
						'role'  => 'user',
					)
				);
			}
			if ( ! empty( $step['response'] ) ) {
				array_push(
					$contents,
					array(
						'parts' => array(
							'text' => $step['response'],
						),
						'role'  => 'model',
					)
				);
			}
		}

		array_push(
			$contents,
			array(
				'parts' => $parts,
				'role'  => 'user',
			)
		);

		$data = array(
			'system_instruction' => array(
				'parts' => array(
					'text' => $this->get_system_instruction(),
				),
			),
			'contents'           => $contents,
		);

		// Log the size of the data payload for reference.
		error_log( 'Gemini data payload size: ' . strlen( wp_json_encode( $data ) ) );

		$response = wp_remote_post(
			add_query_arg( $query_params, $api_base ),
			array(
				'body'    => wp_json_encode( $data ),
				'method'  => 'POST',
				'timeout' => 180, // Allow up to 3 minutes.
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		// Check for errors, then return the response parameters.
		if ( 200 !== $response['response']['code'] ) {
			return $response['response']['message'];
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		return $response_data['candidates'][0]['content']['parts'][0]['text'];
	}

	/**
	 * Construct the agent.
	 *
	 * @param WP_Performance_Wizard $wizard The performance wizard.
	 */
	public function __construct( WP_Performance_Wizard $wizard ) {
		// Set the name.
		$this->set_name( 'Gemini' );
		$this->set_wizard( $wizard );
		$this->set_description( 'Gemini is a is a generative artificial intelligence chatbot developed by Google.' );
		$this->set_system_instruction(
			"As a web performance expert, you will analyze provided data points and give a summary and recommendations for each step. You will retain information from each step and provide an overall summary and set of actionable recommendations with testing methods at the end.

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

    * Optimize server-side code and database queries.

    * Consider using a Content Delivery Network (CDN) to reduce latency.

     * Consider adding a page caching solution.

    * Test the impact of caching mechanisms on the server.

* **Testing:** Monitor the TTFB after implementing changes using web performance tools like WebPageTest or Google PageSpeed Insights.
"
		);
	}
}
