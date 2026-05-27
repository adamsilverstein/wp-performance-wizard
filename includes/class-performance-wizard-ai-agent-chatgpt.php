<?php
/**
 * Performance Wizard AI Agent for ChatGPT.
 *
 * Requests go through the WordPress 7.0 AI Client API via the shared
 * Performance_Wizard_AI_Agent_Base::send_via_ai_client() helper, which adds an
 * extended request timeout and exponential-backoff retries for transient
 * failures. Credentials are resolved by the Connectors API.
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
	 * Send a single prompt to ChatGPT.
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
	 * Send prompts to ChatGPT.
	 *
	 * @param array $prompts              The prompts to pass to the agent.
	 * @param int   $current_step         The current step in the process.
	 * @param array $previous_steps       The previous steps in the process.
	 * @param bool  $additional_questions Whether to ask additional questions.
	 * @return string The response from the API.
	 */
	public function send_prompts( array $prompts, int $current_step, array $previous_steps, bool $additional_questions ): string {
		return $this->send_via_ai_client(
			$prompts,
			$current_step,
			$previous_steps,
			array(
				'max_tokens'  => 4000,
				'temperature' => 0.7,
			)
		);
	}
}
