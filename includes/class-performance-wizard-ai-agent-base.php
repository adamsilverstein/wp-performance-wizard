<?php
/**
 * A base class for AI agents, eg. Gemini, ChatGPT, etc.
 *
 * Credentials are resolved through the WordPress 7.0 Connectors API. Agents
 * declare a connector ID and the base class handles the env var / constant /
 * option lookup order defined by that API.
 *
 * @package wp-performance-wizard
 */

/**
 * The agent base class.
 */
class Performance_Wizard_AI_Agent_Base {

	/**
	 * Cached API key lookup result.
	 *
	 * @var string|null
	 */
	private $api_key;

	/**
	 * A reference to the performance wizard.
	 *
	 * @var WP_Performance_Wizard
	 */
	private $wizard;

	/**
	 * The systemInstructions for the AI agent.
	 *
	 * @var string
	 */
	private $system_instructions = '';

	/**
	 * The name of the AI agent.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The description of the AI agent.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * The Connectors API connector ID this agent reads credentials from.
	 *
	 * @var string
	 */
	private $connector_id = '';

	/**
	 * A method for calling the API of the AI agent.
	 *
	 * @param array    $prompts        The prompts to pass to the agent.
	 * @param int      $current_step   The current step in the process.
	 * @param string[] $previous_steps The previous steps in the process.
	 * @param bool     $additional_questions Whether to ask additional questions.
	 *
	 * @return string The response from the API.
	 */
	public function send_prompts( array $prompts, int $current_step, array $previous_steps, bool $additional_questions ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '';
	}

	/**
	 * A method for calling the API of the AI agent.
	 *
	 * @param string   $prompt         The prompt to pass to the agent.
	 * @param int      $current_step   The current step in the process.
	 * @param string[] $previous_steps The previous steps in the process.
	 * @param bool     $additional_questions Whether to ask additional questions.
	 *
	 * @return string The response from the API.
	 */
	public function send_prompt( string $prompt, int $current_step, array $previous_steps, bool $additional_questions ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '';
	}

	/**
	 * Get the systemInstructions for the AI agent.
	 *
	 * @return string The systemInstructions for the AI agent.
	 */
	public function get_system_instructions(): string {
		return $this->system_instructions;
	}

	/**
	 * Set the systemInstructions for the AI agent.
	 *
	 * @param string $system_instructions The system_instructions for the AI agent.
	 */
	public function set_system_instructions( string $system_instructions ): void {
		$this->system_instructions = $system_instructions;
	}

	/**
	 * Get the name of the AI agent.
	 *
	 * @return string The name of the AI agent.
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Set the name of the AI agent.
	 *
	 * @param string $name The name of the AI agent.
	 */
	public function set_name( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Get the description of the AI agent.
	 *
	 * @return string The description of the AI agent.
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Set the description of the AI agent.
	 *
	 * @param string $description The description of the AI agent.
	 */
	public function set_description( string $description ): void {
		$this->description = $description;
	}

	/**
	 * Get the wizard.
	 *
	 * @return WP_Performance_Wizard The performance wizard.
	 */
	public function get_wizard(): WP_Performance_Wizard {
		return $this->wizard;
	}

	/**
	 * Set the wizard.
	 *
	 * @param WP_Performance_Wizard $wizard The performance wizard.
	 */
	public function set_wizard( WP_Performance_Wizard $wizard ): void {
		$this->wizard = $wizard;
	}

	/**
	 * Get the Connectors API connector ID this agent uses.
	 *
	 * @return string The connector ID (e.g. 'anthropic', 'openai', 'gemini').
	 */
	public function get_connector_id(): string {
		return $this->connector_id;
	}

	/**
	 * Set the Connectors API connector ID this agent uses.
	 *
	 * @param string $connector_id The connector ID.
	 */
	public function set_connector_id( string $connector_id ): void {
		$this->connector_id = $connector_id;
	}

	/**
	 * Get the api key for this agent, resolved through the Connectors API.
	 *
	 * @return string The api key, or an empty string if not configured.
	 */
	public function get_api_key(): string {
		if ( null === $this->api_key ) {
			$this->api_key = $this->load_api_key();
		}
		return $this->api_key;
	}

	/**
	 * Request additional questions from the AI agent.
	 *
	 * @return string The additional questions prompt.
	 */
	protected function get_additional_questions_prompt(): string {
		return 'Finally, based on the data collected and recommendations so far, provide two suggestions for follow up questions that the user could ask to get more information or further recommendations. For these questions, provide them as HTML buttons that the user can click to ask the question. Keep the questions succinct, a maximum of 16 words. For example: "<button class=\"wp-wizard-follow-up-question\">What is the best way to optimize my LCP image?</button>"';
	}

	/**
	 * Load the API key for this agent from the Connectors API.
	 *
	 * Resolution order matches the Connectors API contract:
	 *   1. Environment variable `{CONNECTOR_ID}_API_KEY`.
	 *   2. PHP constant `{CONNECTOR_ID}_API_KEY`.
	 *   3. Database option `connectors_ai_{connector_id}_api_key`.
	 *
	 * @return string The API key, or an empty string if none is configured.
	 */
	public function load_api_key(): string {
		$connector_id = $this->get_connector_id();
		if ( '' === $connector_id ) {
			return '';
		}

		$env_name = strtoupper( $connector_id ) . '_API_KEY';

		$env_value = getenv( $env_name );
		if ( is_string( $env_value ) && '' !== $env_value ) {
			return $env_value;
		}

		if ( defined( $env_name ) ) {
			$constant_value = constant( $env_name );
			if ( is_string( $constant_value ) && '' !== $constant_value ) {
				return $constant_value;
			}
		}

		$option_value = get_option( 'connectors_ai_' . $connector_id . '_api_key', '' );
		return is_string( $option_value ) ? $option_value : '';
	}
}
