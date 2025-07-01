<?php
/**
 * Performance Wizard AI Agent for ChatGPT.
 *
 * This file contains the ChatGPT AI agent implementation for the WordPress Performance Wizard.
 * It handles API connections to OpenAI's ChatGPT/GPT service.
 *
 * You can get a key for OpenAI API by visiting https://platform.openai.com/api-keys
 *
 * Copy the key into a file named chatgpt-key.json and place it in the .keys folder in the plugin directory.
 *
 * @package wp-performance-wizard
 */

/**
 * A class that enables connections to OpenAI ChatGPT.
 *
 * You can get a key for OpenAI API by visiting https://platform.openai.com/api-keys
 *
 * Copy the key into a file named chatgpt-key.json and place it in the .keys folder in the plugin directory.
 *
 * @package wp-performance-wizard
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
		add_action( 'admin_post_handle_chatgpt_api_key_submission', array( $this, 'handle_api_key_submission' ), 10, 0 );
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
			$prompt .= PHP_EOL . $this->getAdditionalQuestionsPrompt();
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

		// Add system instruction as the first message
		if ( '' !== $system_instruction ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_instruction,
			);
		}

		// Add previous conversation history
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

		// Add current prompts
		$messages[] = array(
			'role'    => 'user',
			'content' => implode( PHP_EOL, $prompts ),
		);

		$data = array(
			'model'       => 'gpt-4', // Using GPT-4 for better performance analysis capabilities
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

		// Check if we have a valid response structure
		if ( isset( $response_data['choices'][0]['message']['content'] ) ) {
			return $response_data['choices'][0]['message']['content'];
		}

		return 'No response from ChatGPT.';
	}

	/**
	 * Add a submenu page for ChatGPT Admin, including a field to enter the API key.
	 */
	public function add_submenu_page(): void {
		// Add a submenu page for ChatGPT Admin, including a field to enter the API key. Goes as a sub menu under 'Performance Wizard'.
		add_submenu_page(
			'wp-performance-wizard',
			__( 'ChatGPT', 'wp-performance-wizard' ),
			__( 'ChatGPT', 'wp-performance-wizard' ),
			'manage_options',
			'wp-performance-wizard-chatgpt',
			array( $this, 'render_admin_page' ),
			1
		);
	}

	/**
	 * Render the ChatGPT Admin page.
	 */
	public function render_admin_page(): void {
		echo '<h2>' . esc_attr( $this->get_name() ) . ' Admin</h2>';

		// Show status messages using base class helper.
		$this->render_status_messages();

		$default_api_key = '';
		$api_key         = $this->get_api_key();
		if ( '' !== $api_key ) {
			$default_api_key = str_repeat( '*', strlen( $api_key ) );
		}

		// Explain the ChatGPT API and where to get an API key.
		echo '<p>ChatGPT is a generative artificial intelligence tool developed by OpenAI. You can get an API key by visiting <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a>.</p>';

		// Add a form element, with a nonce field for security. Add as a WordPress action.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'save_chatgpt_api_key', 'chatgpt_api_key_nonce' );

		echo '<input type="hidden" name="action" value="handle_chatgpt_api_key_submission">';

		// Add a field for the API key.
		echo '<label for="chatgpt-api-key">API Key</label> ';
		echo '<input type="password" id="chatgpt-api-key" name="chatgpt-api-key" value="' . esc_attr( $default_api_key ) . '">';

		// Add a hidden field with the default so we can skip if password is unchanged.
		echo '<input type="hidden" name="default-chatgpt-api-key" value="' . esc_attr( $default_api_key ) . '">';

		// Add a submit button.
		echo '<input type="submit" class="button button-primary" value="Save">';
		echo '</form>';
	}

	/**
	 * Handle the API key submission.
	 */
	public function handle_api_key_submission(): void {
		// Validate nonce.
		if ( ! isset( $_POST['chatgpt_api_key_nonce'] ) || ! wp_verify_nonce( $_POST['chatgpt_api_key_nonce'], 'save_chatgpt_api_key' ) ) {
			$url = isset( $_POST['_wp_http_referer'] ) ? $_POST['_wp_http_referer'] : admin_url( 'admin.php?page=wp-performance-wizard-chatgpt' );
			wp_safe_redirect( add_query_arg( array( 'info' => 'nonce_error' ), $url ) );
			exit;
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			$url = isset( $_POST['_wp_http_referer'] ) ? $_POST['_wp_http_referer'] : admin_url( 'admin.php?page=wp-performance-wizard-chatgpt' );
			wp_safe_redirect( add_query_arg( array( 'info' => 'permission_error' ), $url ) );
			exit;
		}

		// Get and validate form data.
		$api_key         = isset( $_POST['chatgpt-api-key'] ) ? sanitize_text_field( $_POST['chatgpt-api-key'] ) : '';
		$url             = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( $_POST['_wp_http_referer'] ) : admin_url( 'admin.php?page=wp-performance-wizard-chatgpt' );
		$default_api_key = isset( $_POST['default-chatgpt-api-key'] ) ? sanitize_text_field( $_POST['default-chatgpt-api-key'] ) : '';

		// Check if the key was actually changed.
		if ( $default_api_key === $api_key ) {
			wp_safe_redirect( add_query_arg( array( 'info' => 'no_change' ), $url ) );
			exit;
		}

		// Validate API key format - OpenAI keys typically start with 'sk-'
		if ( '' === $api_key || strlen( $api_key ) < 10 ) {
			wp_safe_redirect( add_query_arg( array( 'info' => 'invalid_key' ), $url ) );
			exit;
		}

		// Try to save the key.
		try {
			$saved = $this->save_key( $api_key );
			if ( $saved ) {
				wp_safe_redirect( add_query_arg( array( 'info' => 'saved' ), $url ) );
			} else {
				wp_safe_redirect( add_query_arg( array( 'info' => 'save_failed' ), $url ) );
			}
		} catch ( Exception $e ) {
			wp_safe_redirect( add_query_arg( array( 'info' => 'exception' ), $url ) );
		}
		exit;
	}

	/**
	 * Request additional questions from the AI agent.
	 */
	public function getAdditionalQuestionsPrompt(): string {
		return 'Finally, based on the data collected and recommendations so far, provide two suggestions for follow up questions that the user could ask to get more information or further recommendations. For these questions, provide them as HTML buttons that the user can click to ask the question. Keep the questions succinct, a maximum of 16 words. For example: "<button class=\"wp-wizard-follow-up-question\">What is the best way to optimize my LCP image?</button>"';
	}
}