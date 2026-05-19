<?php
/**
 * Performance Wizard AI Agent for Claude.
 *
 * This file contains the Claude AI agent implementation for the WordPress Performance Wizard.
 * It handles API connections to Anthropic's Claude AI service.
 *
 * Credentials are supplied through the WordPress 7.0 Connectors API. Users
 * configure their key from the core Connectors admin screen.
 *
 * @package wp-performance-wizard
 */

/**
 * A class that enables connections to Anthropic Claude AI.
 */
class Performance_Wizard_AI_Agent_Claude extends Performance_Wizard_AI_Agent_Base {

	/**
	 * Construct the agent.
	 *
	 * @param WP_Performance_Wizard $wizard The performance wizard.
	 */
	public function __construct( WP_Performance_Wizard $wizard ) {
		$this->set_name( 'Claude' );
		$this->set_wizard( $wizard );
		$this->set_description( 'Claude is a generative AI chatbot developed by Anthropic.' );
		$this->set_connector_id( 'anthropic' );
	}

	/**
	 * Send a single prompt to the Claude API.
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
	 * Send prompts to the Claude API.
	 *
	 * @param array $prompts The prompts to pass to the agent.
	 * @param int   $current_step The current step in the process.
	 * @param array $previous_steps The previous steps in the process.
	 * @param bool  $additional_questions Whether to ask additional questions.
	 * @return string The response from the API.
	 */
	public function send_prompts( array $prompts, int $current_step, array $previous_steps, bool $additional_questions ): string {
		$api_url            = 'https://api.anthropic.com/v1/messages';
		$api_key            = $this->get_api_key();
		$system_instruction = $this->get_system_instructions();

		$messages  = array();
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
		$messages[] = array(
			'role'    => 'user',
			'content' => implode( PHP_EOL, $prompts ),
		);

		$data = array(
			'model'      => 'claude-3-7-sonnet-latest',
			'system'     => $system_instruction,
			'messages'   => $messages,
			'max_tokens' => 2048,
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'body'    => wp_json_encode( $data ),
				'headers' => array(
					'Content-Type'      => 'application/json',
					'X-API-Key'         => $api_key,
					'Anthropic-Version' => '2023-06-01',
				),
				'timeout' => 180,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}
		if ( 200 !== $response['response']['code'] ) {
			return $response['response']['message'];
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( isset( $data['content'][0]['text'] ) ) {
			return $data['content'][0]['text'];
		}
		return 'No response from Claude.';
	}
}
