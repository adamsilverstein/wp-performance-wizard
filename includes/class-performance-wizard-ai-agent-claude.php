<?php
/**
 * Performance Wizard AI Agent for Claude.
 *
 * This file contains the Claude AI agent implementation for the WordPress Performance Wizard.
 * It handles API connections to Anthropic's Claude AI service.
 *
 * You can get a key for Claude API by visiting https://console.anthropic.com/settings/keys
 *
 * Copy the key into a file named claude-key.json and place it in the .keys folder in the plugin directory.
 *
 * @package wp-performance-wizard
 */

/**
 * A class that enables connections to Anthropic Claude AI.
 *
 * You can get a key for Claude API by visiting https://console.anthropic.com/settings/keys
 *
 * Copy the key into a file named claude-key.json and place it in the .keys folder in the plugin directory.
 *
 * @package wp-performance-wizard
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
		add_action( 'admin_post_handle_claude_api_key_submission', array( $this, 'handle_api_key_submission' ), 10, 0 );
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
			$prompt .= PHP_EOL . $this->getAdditionalQuestionsPrompt();
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
			'model'      => 'claude-3-sonnet-20240229', // Claude Sonnet 4.0 model.
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

	/**
	 * Add a submenu page for Claude Admin, including a field to enter the API key.
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			'wp-performance-wizard',
			__( 'Claude', 'wp-performance-wizard' ),
			__( 'Claude', 'wp-performance-wizard' ),
			'manage_options',
			'wp-performance-wizard-claude',
			array( $this, 'render_admin_page' ),
			2
		);
	}

	/**
	 * Render the Claude Admin page.
	 */
	public function render_admin_page(): void {
		echo '<h2>' . esc_attr( $this->get_name() ) . ' Admin</h2>';
		$default_api_key = '';
		$api_key         = $this->get_api_key();
		if ( '' !== $api_key ) {
			$default_api_key = str_repeat( '*', strlen( $api_key ) );
		}
		echo '<p>Claude is a generative AI tool developed by Anthropic. You can get an API key by visiting <a href="https://console.anthropic.com/settings/keys" target="_blank">https://console.anthropic.com/settings/keys</a>.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'save_claude_api_key', 'claude_api_key_nonce' );
		echo '<input type="hidden" name="action" value="handle_claude_api_key_submission">';
		echo '<label for="claude-api-key">API Key</label> ';
		echo '<input type="password" id="claude-api-key" name="claude-api-key" value="' . esc_attr( $default_api_key ) . '">';
		echo '<input type="hidden" name="default-claude-api-key" value="' . esc_attr( $default_api_key ) . '">';
		echo '<input type="submit" class="button button-primary" value="Save">';
		echo '</form>';
	}

	/**
	 * Handle the API key submission.
	 */
	public function handle_api_key_submission(): void {
		// Validate nonce
		if ( ! isset( $_POST['claude_api_key_nonce'] ) || ! wp_verify_nonce( $_POST['claude_api_key_nonce'], 'save_claude_api_key' ) ) {
			$url = isset( $_POST['_wp_http_referer'] ) ? $_POST['_wp_http_referer'] : admin_url( 'admin.php?page=wp-performance-wizard-claude' );
			wp_safe_redirect( add_query_arg( array( 'info' => 'nonce_error' ), $url ) );
			exit;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			$url = isset( $_POST['_wp_http_referer'] ) ? $_POST['_wp_http_referer'] : admin_url( 'admin.php?page=wp-performance-wizard-claude' );
			wp_safe_redirect( add_query_arg( array( 'info' => 'permission_error' ), $url ) );
			exit;
		}

		// Get and validate form data
		$api_key = isset( $_POST['claude-api-key'] ) ? sanitize_text_field( $_POST['claude-api-key'] ) : '';
		$url     = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( $_POST['_wp_http_referer'] ) : admin_url( 'admin.php?page=wp-performance-wizard-claude' );
		$default_api_key = isset( $_POST['default-claude-api-key'] ) ? sanitize_text_field( $_POST['default-claude-api-key'] ) : '';

		// Check if the key was actually changed
		if ( $default_api_key === $api_key ) {
			wp_safe_redirect( add_query_arg( array( 'info' => 'no_change' ), $url ) );
			exit;
		}

		// Validate API key format.
		if ( empty( $api_key ) || strlen( $api_key ) < 10 ) {
			wp_safe_redirect( add_query_arg( array( 'info' => 'invalid_key' ), $url ) );
			exit;
		}

		// Try to save the key
		try {
			$saved = $this->save_key( $api_key );
			if ( $saved ) {
				wp_safe_redirect( add_query_arg( array( 'info' => 'saved' ), $url ) );
			} else {
				wp_safe_redirect( add_query_arg( array( 'info' => 'save_failed' ), $url ) );
			}
		} catch ( Exception $e ) {
			error_log( 'Claude API key save error: ' . $e->getMessage() );
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
