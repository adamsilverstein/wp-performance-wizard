<?php
/**
 * A class that enables connections to OpenAI's ChatGPT.
 *
 * You can get a key for OpenAI API by visiting https://platform.openai.com/api-keys
 *
 * Copy the key into a file named chat-gpt-key.json and place it in the .keys folder in the plugin directory.
 *
 * @package wp-performance-wizard
 */

/**
 * The ChatGPT class.
 */
class Performance_Wizard_AI_Agent_Chat_GPT extends Performance_Wizard_AI_Agent_Base {
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
		// Send a REST API request to the OpenAI API.
		$api_base = 'https://api.openai.com/v1/chat/completions';

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $this->get_system_instructions(),
			),
		);

		// Add previous conversation context.
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

		// Add current prompt.
		$messages[] = array(
			'role'    => 'user',
			'content' => implode( PHP_EOL, $prompts ),
		);

		$data = array(
			'model'       => 'gpt-4-turbo-preview',
			'messages'    => $messages,
			'temperature' => 0.7,
		);

		$response = wp_remote_post(
			$api_base,
			array(
				'body'    => wp_json_encode( $data ),
				'method'  => 'POST',
				'timeout' => 180, // Allow up to 3 minutes.
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->get_api_key(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		// Check for errors.
		if ( 200 !== $response['response']['code'] ) {
			return $response['response']['message'];
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		return $response_data['choices'][0]['message']['content'];
	}

	/**
	 * Request additional questions from the AI agent.
	 */
	public function getAdditionalQuestionsPrompt(): string {
		return 'Finally, based on the data collected and recommendations so far, provide two suggestions for follow up questions that the user could ask to get more information or further recommendations. For these questions, provide them as HTML buttons that the user can click to ask the question. Keep the questions succinct, a maximum of 16 words. For example: "<button class="wp-wizard-follow-up-question">What is the best way to optimize my LCP image?</button>"';
	}

	/**
	 * Construct the agent.
	 *
	 * @param WP_Performance_Wizard $wizard The performance wizard.
	 */
	public function __construct( WP_Performance_Wizard $wizard ) {
		$this->set_name( 'ChatGPT' );
		$this->set_wizard( $wizard );
		$this->set_description( 'ChatGPT is a large language model developed by OpenAI, capable of understanding and generating human-like text.' );

		add_action( 'admin_post_handle_chat_gpt_api_key_submission', array( $this, 'handle_api_key_submission' ), 10, 0 );
	}

	/**
	 * Add a submenu page for ChatGPT Admin.
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			'wp-performance-wizard',
			__( 'ChatGPT', 'wp-performance-wizard' ),
			__( 'ChatGPT', 'wp-performance-wizard' ),
			'manage_options',
			'wp-performance-wizard-chat-gpt',
			array( $this, 'render_admin_page' ),
			2
		);
	}

	/**
	 * Render the ChatGPT Admin page.
	 */
	public function render_admin_page(): void {
		echo '<div class="wrap">';
		echo '<h2>' . esc_attr( $this->get_name() ) . ' Admin</h2>';

		$default_api_key = '';
		$api_key         = $this->get_key();
		if ( '' !== $api_key ) {
			$default_api_key = str_repeat( '*', strlen( $api_key ) );
		}

		echo '<p>ChatGPT is an AI language model developed by OpenAI. You can get an API key by visiting <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a>.</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'save_chat_gpt_api_key', 'chat_gpt_api_key_nonce' );

		echo '<input type="hidden" name="action" value="handle_chat_gpt_api_key_submission">';

		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row"><label for="chat-gpt-api-key">API Key</label></th>';
		echo '<td><input type="password" id="chat-gpt-api-key" name="chat-gpt-api-key" value="' . esc_attr( $default_api_key ) . '" class="regular-text"></td>';
		echo '</tr>';
		echo '</table>';

		echo '<input type="hidden" name="default-chat-gpt-api-key" value="' . esc_attr( $default_api_key ) . '">';

		submit_button( 'Save Changes' );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle the API key submission.
	 */
	public function handle_api_key_submission(): void {
		// Validate the nonce.
		if ( ! wp_verify_nonce( $_POST['chat_gpt_api_key_nonce'], 'save_chat_gpt_api_key' ) ) {
			$url = $_POST['_wp_http_referer'];
			wp_safe_redirect(
				add_query_arg(
					array(
						'info' => 'error',
					),
					$url
				)
			);
			exit;
		}
		$url             = $_POST['_wp_http_referer'];
		$api_key         = sanitize_text_field( $_POST['chat-gpt-api-key'] );
		$default_api_key = sanitize_text_field( $_POST['default-chat-gpt-api-key'] );

		// Double check the user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'info' => 'error',
					),
					$url
				)
			);
			exit;
		}

		if ( $default_api_key === $api_key ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'info' => 'error',
					),
					$url
				)
			);
			exit;
		}

		// Save the API key. Save in the options table and use if key file is not available.
		$this->save_key( $api_key );

		// Redirect back to the form page.
		wp_safe_redirect(
			add_query_arg(
				array(
					'info' => 'saved',
				),
				$url
			)
		);
		exit;
	}

	/**
	 * Encrypt and save the key.
	 *
	 * @param string $key The key to save.
	 *
	 * @return bool Whether the key was saved successfully.
	 */
	public function save_key( string $key ): bool {
		$encrypted_key = $this->encrypt_key( $key );
		return update_option( 'wp_performance_wizard_chat_gpt_api_key', $encrypted_key );
	}

	/**
	 * Decrypt and retrieve the key.
	 *
	 * @return string The decrypted key.
	 */
	public function get_key(): string {
		$encrypted_key = get_option( 'wp_performance_wizard_chat_gpt_api_key' );
		return $this->decrypt_key( $encrypted_key );
	}

	/**
	 * Load the API key from the database if available. Otherwise fall back to file loading
	 *
	 * @return string The API key.
	 */
	public function load_api_key(): string {
		$stored_key = $this->get_key();
		if ( '' !== $stored_key ) {
			return $stored_key;
		} else {
			return parent::load_api_key();
		}
	}
}
