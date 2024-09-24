<?php
/**
 * A class that enables connections to Gemini AI.
 *
 * You can get a key for Gemini API by visiting https://aistudio.google.com/app/apikey
 *
 * Copy the key into a file named gemini-key.json and place it in the .keys folder in the plugin directory.
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
	 * @param bool     $additional_questions Whether to ask additional questions.
	 *
	 * @return string The response from the API.
	 */
	public function send_prompt( string $prompt, int $current_step, array $previous_steps, bool $additional_questions ): string {
		if ( $additional_questions ) {
			$prompt .= PHP_EOL . $this->getAdditionalQuestionsPrompt();
		}
		return $this->send_prompts( array( $prompt ), $current_step, $previous_steps, $additional_questions );
	}

	/**
	 * A method for calling the API of the AI agent.
	 *
	 * @param array $prompts        The prompts to pass to the agent.
	 * @param int   $current_step   The current step in the process.
	 * @param array $previous_steps The previous steps in the process.
	 * @param bool  $additional_questions Whether to ask additional questions.
	 *
	 * @return string The response from the API.
	 */
	public function send_prompts( array $prompts, int $current_step, array $previous_steps, bool $additional_questions ): string {

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
			if ( ! isset( $previous_steps[ $i ] ) ) {
				continue;
			}
			$step = $previous_steps[ $i ];
			if ( isset( $step['prompts'] ) ) {
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
			if ( isset( $step['response'] ) ) {
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
					'text' => $this->get_system_instructions(),
				),
			),
			'contents'           => $contents,
		);

		// Log the size of the data payload for reference.
		error_log( 'Gemini data payload size: ' . strlen( wp_json_encode( $data ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

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
	 * Request additional questions from the AI agent.
	 */
	public function getAdditionalQuestionsPrompt(): string {
		return 'Finally,  based on the data collected and recommendations so far, provide two suggestions for follow up questions that the user could ask to get more information or further recommendations. For these questions, provide them as HTML buttons that the user can click to ask the question. Keep the questions succinct, a maximum of 16 words. For example: "<button class="wp-wizard-follow-up-question">What is the best way to optimize my LCP image?</button>"';
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
	}
}
