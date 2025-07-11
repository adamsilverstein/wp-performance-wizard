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
			$prompt .= PHP_EOL . $this->get_additional_questions_prompt();
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
		$api_base     = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent';
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
	 * Construct the agent.
	 *
	 * @param WP_Performance_Wizard $wizard The performance wizard.
	 */
	public function __construct( WP_Performance_Wizard $wizard ) {
		// Set the name.
		$this->set_name( 'Gemini' );
		$this->set_wizard( $wizard );
		$this->set_description( 'Gemini is a is a generative artificial intelligence chatbot developed by Google.' );

		// Add the handle_api_key_submission callback to the admin_post action.
		add_action( 'admin_post_handle_gemini_api_key_submission', array( $this, 'handle_api_key_submission' ), 10, 0 );
	}

	/**
	 * Add a submenu page for Gemini Admin, including a field to enter the API key.
	 */
	public function add_submenu_page(): void {
		// Add a submenu page for Gemini Admin, including a field to enter the API key. Goes as a sub menu under 'Performance Wizard'.
		add_submenu_page(
			'wp-performance-wizard',
			__( 'Gemini', 'wp-performance-wizard' ),
			__( 'Gemini', 'wp-performance-wizard' ),
			'manage_options',
			'wp-performance-wizard-gemini',
			array( $this, 'render_admin_page' ),
			1
		);
	}

	/**
	 * Render the Gemini Admin page.
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

		// Explain the Gemini API and where to get an API key.
		echo '<p>Gemini is a generative artificial intelligence tool developed by Google. You can get an API key by visiting <a href="https://aistudio.google.com/app/apikey" target="_blank">https://aistudio.google.com/app/apikey</a>.</p>';

		// Add a form element, with a nonce field for security. Add as a WordPress action.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'save_gemini_api_key', 'gemini_api_key_nonce' );

		echo '<input type="hidden" name="action" value="handle_gemini_api_key_submission">';

		// Add a field for the API key.
		echo '<label for="gemini-api-key">API Key</label> ';
		echo '<input type="password" id="gemini-api-key" name="gemini-api-key" value="' . esc_attr( $default_api_key ) . '">';

		// Add a hidden field with the default so we can skip if password is unchanged.
		echo '<input type="hidden" name="default-gemini-api-key" value="' . esc_attr( $default_api_key ) . '">';

		// Add a submit button.
		echo '<input type="submit" class="button button-primary" value="Save">';
		echo '</form>';
		if ( '' !== $api_key ) {
			$default_api_key = str_repeat( '*', strlen( $api_key ) );
		}
	}

	/**
	 * Handle the API key submission.
	 */
	public function handle_api_key_submission(): void {

		// Validate the nonce.
		if ( ! wp_verify_nonce( $_POST['gemini_api_key_nonce'], 'save_gemini_api_key' ) ) {
			$url = $_POST['_wp_http_referer'];
			wp_safe_redirect(
				add_query_arg(
					array(
						'info' => 'nonce_error',
					),
					$url
				)
			);
			exit;
		}

		$api_key = sanitize_text_field( $_POST['gemini-api-key'] );
		$url     = esc_url_raw( $_POST['_wp_http_referer'] );

		// Double check the user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'info' => 'permission_error',
					),
					$url
				)
			);
			exit;
		}

		// Validate API key format.
		if ( '' === $api_key || strlen( $api_key ) < 10 ) {
			wp_safe_redirect( add_query_arg( array( 'info' => 'invalid_key' ), $url ) );
			exit;
		}

		$default_api_key = sanitize_text_field( $_POST['default-gemini-api-key'] );
		if ( $default_api_key === $api_key ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'info' => 'no_change',
					),
					$url
				)
			);
			exit;
		}

		// Save the API key. Save in the options table and use if key file is not available.
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
}
