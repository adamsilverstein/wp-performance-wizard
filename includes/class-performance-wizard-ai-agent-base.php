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
	 * Resets the cached key so the next get_api_key() call re-resolves against
	 * the new connector's env var, constant, and option.
	 *
	 * @param string $connector_id The connector ID.
	 */
	public function set_connector_id( string $connector_id ): void {
		if ( $this->connector_id !== $connector_id ) {
			$this->connector_id = $connector_id;
			$this->api_key      = null;
		}
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
	 * Send prompts via the WordPress 7.0 AI Client API with retry-on-transient.
	 *
	 * Builds the prompt builder with the agent's connector ID as the provider,
	 * the cached system instructions, prior conversation history as Message
	 * DTOs, and an extended request timeout suitable for our large analysis
	 * prompts. Retries up to three times with exponential backoff when the
	 * underlying request fails with a transient error (timeouts, network
	 * blips, rate limits, or 5xx upstream errors).
	 *
	 * @param array<int, string>                $prompts        Current prompts to send.
	 * @param int                               $current_step   The current step index.
	 * @param array<int, array<string, string>> $previous_steps Prior steps to replay as history.
	 * @param array<string, mixed>              $options        Optional per-agent tuning:
	 *                                                          'max_tokens'  int   default 2048,
	 *                                                          'temperature' float omitted unless set,
	 *                                                          'timeout'     float seconds, default 180.
	 * @return string The model output, or a user-facing error string.
	 */
	protected function send_via_ai_client( array $prompts, int $current_step, array $previous_steps, array $options = array() ): string {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return __( 'WordPress AI Client API (wp_ai_client_prompt) is unavailable. WordPress 7.0+ is required.', 'wp-performance-wizard' );
		}

		// Track the request size as we build it so usage can be estimated for
		// the cost indicator (the AI Client does not surface a uniform token
		// count to the plugin).
		$history_input_chars = 0;

		$history = array();
		for ( $i = 1; $i < $current_step; $i++ ) {
			if ( ! isset( $previous_steps[ $i ] ) ) {
				continue;
			}
			$step = $previous_steps[ $i ];
			if ( isset( $step['prompts'] ) && '' !== $step['prompts'] ) {
				$history_input_chars += strlen( $step['prompts'] );
				$history[]            = new \WordPress\AiClient\Messages\DTO\Message(
					\WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
					array( new \WordPress\AiClient\Messages\DTO\MessagePart( $step['prompts'] ) )
				);
			}
			if ( isset( $step['response'] ) && '' !== $step['response'] ) {
				$history_input_chars += strlen( $step['response'] );
				$history[]            = new \WordPress\AiClient\Messages\DTO\Message(
					\WordPress\AiClient\Messages\Enums\MessageRoleEnum::model(),
					array( new \WordPress\AiClient\Messages\DTO\MessagePart( $step['response'] ) )
				);
			}
		}

		$current_prompt = implode( PHP_EOL, $prompts );
		$max_tokens     = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 2048;
		$timeout        = isset( $options['timeout'] ) ? (float) $options['timeout'] : 180.0;
		$max_attempts   = 3;

		// The full request is the system instruction plus the replayed history
		// plus the current prompt.
		$input_chars = $history_input_chars + strlen( $this->get_system_instructions() ) + strlen( $current_prompt );

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$builder = wp_ai_client_prompt( $current_prompt )
				->using_provider( $this->get_connector_id() )
				->using_system_instruction( $this->get_system_instructions() )
				->using_max_tokens( $max_tokens )
				->using_request_options(
					\WordPress\AiClient\Providers\Http\DTO\RequestOptions::fromArray(
						array( \WordPress\AiClient\Providers\Http\DTO\RequestOptions::KEY_TIMEOUT => $timeout )
					)
				);

			if ( isset( $options['temperature'] ) ) {
				$builder = $builder->using_temperature( (float) $options['temperature'] );
			}

			if ( count( $history ) > 0 ) {
				$builder = $builder->with_history( ...$history );
			}

			$result = $builder->generate_text();

			if ( ! is_wp_error( $result ) ) {
				// Record estimated usage for the successful generation so the UI
				// can show per-step and per-run token consumption and cost.
				Performance_Wizard_Usage::record( $this->get_connector_id(), $input_chars, strlen( $result ) );
				return $result;
			}

			$error_message = $result->get_error_message();

			if ( ! self::is_transient_error( $error_message ) || $attempt === $max_attempts ) {
				error_log( sprintf( '[WP Performance Wizard][%s] generate_text failed (attempt %d/%d): %s', $this->get_name(), $attempt, $max_attempts, $error_message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				/* translators: 1: AI agent name (e.g. Claude), 2: error message from the AI Client. */
				return sprintf( __( '%1$s API error: %2$s', 'wp-performance-wizard' ), $this->get_name(), $error_message );
			}

			$delay = min( 2 ** ( $attempt - 1 ), 8 );
			error_log( sprintf( '[WP Performance Wizard][%s] transient error on attempt %d/%d, retrying in %ds: %s', $this->get_name(), $attempt, $max_attempts, $delay, $error_message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sleep( $delay );
		}

		/* translators: %s: AI agent name (e.g. Claude). */
		return sprintf( __( '%s API error: maximum retries exceeded.', 'wp-performance-wizard' ), $this->get_name() );
	}

	/**
	 * Decide whether an AI Client error message represents a transient failure
	 * that is worth retrying.
	 *
	 * @param string $message The error message returned by the AI Client.
	 * @return bool Whether the request should be retried.
	 */
	private static function is_transient_error( string $message ): bool {
		$lower = strtolower( $message );

		$transient_markers = array(
			'timed out',
			'timeout',
			'curl error 28',
			'curl error 6',
			'curl error 7',
			'curl error 35',
			'curl error 52',
			'curl error 56',
			'could not resolve',
			'connection reset',
			'connection refused',
			'temporarily unavailable',
			'rate limit',
			'too many requests',
			'overloaded',
			' 429',
			' 500',
			' 502',
			' 503',
			' 504',
			'http/1.1 5',
			'http/2 5',
		);

		foreach ( $transient_markers as $marker ) {
			if ( false !== strpos( $lower, $marker ) ) {
				return true;
			}
		}

		return false;
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
