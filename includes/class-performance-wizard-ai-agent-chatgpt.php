<?php
/**
 * Performance Wizard AI Agent for ChatGPT.
 *
 * This file contains the ChatGPT AI agent implementation for the WordPress Performance Wizard.
 * It handles API connections to OpenAI's ChatGPT/GPT service.
 *
 * Credentials are supplied through the WordPress 7.0 Connectors API. Users
 * configure their key from the core Connectors admin screen.
 *
 * @package wp-performance-wizard
 */

/**
 * A class that enables connections to OpenAI ChatGPT.
 */
class Performance_Wizard_AI_Agent_ChatGPT extends Performance_Wizard_AI_Agent_Base {

	/**
	 * Construct the agent.
	 *
	 * @param WP_Performance_Wizard $wizard The performance wizard.
	 */
	public function __construct( WP_Performance_Wizard $wizard ) {
		$this->set_name( 'ChatGPT' );
		$this->set_wizard( $wizard );
		$this->set_description( 'ChatGPT is a generative AI chatbot developed by OpenAI.' );
		$this->set_connector_id( 'openai' );
	}

	/**
	 * Send a single prompt to the ChatGPT API.
	 *
	 * @param string $prompt The prompt to pass to the agent.
	 * @param int    $current_step The current step in the process.
	 * @param array  $previous_steps The previous steps in the process.
	 * @param bool   $additional_questions Whether to ask additional questions.
	 * @return string The response from the API.
	 */
	public function send_prompt( string $prompt, int $current_step, array $previous_steps, bool $additional_questions ): string {
		if ( $additional_questions ) {
			$prompt .= PHP_EOL . $this->get_additional_questions_prompt();
		}
		return $this->send_prompts( array( $prompt ), $current_step, $previous_steps, $additional_questions );
	}

	/**
	 * Send prompts to the ChatGPT API.
	 *
	 * @param array $prompts The prompts to pass to the agent.
	 * @param int   $current_step The current step in the process.
	 * @param array $previous_steps The previous steps in the process.
	 * @param bool  $additional_questions Whether to ask additional questions.
	 * @return string The response from the API.
	 */
	public function send_prompts( array $prompts, int $current_step, array $previous_steps, bool $additional_questions ): string {
		$api_url            = 'https://api.openai.com/v1/chat/completions';
		$api_key            = $this->get_api_key();
		$system_instruction = $this->get_system_instructions();

		$messages = array();

		// Add system instruction as the first message.
		if ( '' !== $system_instruction ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_instruction,
			);
		}

		// Add previous conversation history.
		$max_steps = $current_step;
		for ( $i = 1; $i < $max_steps; $i++ ) {
			if ( ! isset( $previous_steps[ $i ] ) ) {
				continue;
			}
			$step = $previous_steps[ $i ];
			if ( isset( $step['prompts'] ) ) {
				$messages[] = array(
					'role'    => 'user',
					'content' => $step['prompts'],
				);
			}
			if ( isset( $step['response'] ) ) {
				$messages[] = array(
					'role'    => 'assistant',
					'content' => $step['response'],
				);
			}
		}

		// Add current prompts.
		$messages[] = array(
			'role'    => 'user',
			'content' => implode( PHP_EOL, $prompts ),
		);

		$data = array(
			'model'       => 'gpt-3.5-turbo', // Use the latest free model.
			'messages'    => $messages,
			'temperature' => 0.7,
			'max_tokens'  => 4000,
		);

		// Log the size of the data payload for reference.
		error_log( 'ChatGPT data payload size: ' . strlen( wp_json_encode( $data ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		$response = wp_remote_post(
			$api_url,
			array(
				'body'    => wp_json_encode( $data ),
				'timeout' => 180, // Allow up to 3 minutes.
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
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

		// Check if we have a valid response structure.
		if ( isset( $response_data['choices'][0]['message']['content'] ) ) {
			return $response_data['choices'][0]['message']['content'];
		}

		return 'No response from ChatGPT.';
	}
}
