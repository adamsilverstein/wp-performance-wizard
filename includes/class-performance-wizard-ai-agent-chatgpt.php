<?php
/**
 * Performance Wizard AI Agent for ChatGPT.
 *
 * This file contains the ChatGPT AI agent implementation for the WordPress Performance Wizard.
 * Requests go through the WordPress 7.0 AI Client API (wp_ai_client_prompt) so credentials
 * resolved by the Connectors API are applied automatically and model selection is delegated
 * to the registry instead of being hardcoded.
 *
 * @package wp-performance-wizard
 */

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;

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
	 * Send a single prompt to ChatGPT via the WordPress AI Client API.
	 *
	 * @param string $prompt               The prompt to pass to the agent.
	 * @param int    $current_step         The current step in the process.
	 * @param array  $previous_steps       The previous steps in the process.
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
	 * Send prompts to ChatGPT via the WordPress AI Client API.
	 *
	 * @param array $prompts              The prompts to pass to the agent.
	 * @param int   $current_step         The current step in the process.
	 * @param array $previous_steps       The previous steps in the process.
	 * @param bool  $additional_questions Whether to ask additional questions.
	 * @return string The response from the API.
	 */
	public function send_prompts( array $prompts, int $current_step, array $previous_steps, bool $additional_questions ): string {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return 'WordPress AI Client API (wp_ai_client_prompt) is unavailable. WordPress 7.0+ is required.';
		}

		$history = array();
		for ( $i = 1; $i < $current_step; $i++ ) {
			if ( ! isset( $previous_steps[ $i ] ) ) {
				continue;
			}
			$step = $previous_steps[ $i ];
			if ( isset( $step['prompts'] ) && '' !== $step['prompts'] ) {
				$history[] = new Message(
					MessageRoleEnum::user(),
					array( new MessagePart( (string) $step['prompts'] ) )
				);
			}
			if ( isset( $step['response'] ) && '' !== $step['response'] ) {
				$history[] = new Message(
					MessageRoleEnum::model(),
					array( new MessagePart( (string) $step['response'] ) )
				);
			}
		}

		$current_prompt = implode( PHP_EOL, $prompts );

		$builder = wp_ai_client_prompt( $current_prompt )
			->using_provider( $this->get_connector_id() )
			->using_system_instruction( $this->get_system_instructions() )
			->using_temperature( 0.7 )
			->using_max_tokens( 4000 );

		if ( count( $history ) > 0 ) {
			$builder = $builder->with_history( ...$history );
		}

		$result = $builder->generate_text();

		if ( is_wp_error( $result ) ) {
			error_log( '[WP Performance Wizard][ChatGPT] generate_text WP_Error: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return 'ChatGPT API error: ' . $result->get_error_message();
		}

		return $result;
	}
}
