<?php
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
	 * Encryption cipher method
	 */
	private const ENCRYPTION_CIPHER = 'aes-256-cbc';

	/**
	 * Number of iterations for key derivation
	 */
	private const PBKDF2_ITERATIONS = 10000;

	/**
	 * Length of derived key in bytes
	 */
	private const KEY_LENGTH = 32;

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
	 * @param int $current_step The current step in the process.
	 * @param array $previous_steps The previous steps in the process.
	 * @param bool $additional_questions Whether to ask additional questions.
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
	 * @param int $current_step The current step in the process.
	 * @param array $previous_steps The previous steps in the process.
	 * @param bool $additional_questions Whether to ask additional questions.
	 * @return string The response from the API.
	 */
	public function send_prompts( array $prompts, int $current_step, array $previous_steps, bool $additional_questions ): string {
		$api_url = 'https://api.anthropic.com/v1/messages';
		$api_key = $this->get_api_key();
		$system_instruction = $this->get_system_instructions();

		$messages = array();
		$max_steps = $current_step;
		for ( $i = 1; $i < $max_steps; $i++ ) {
			if ( ! isset( $previous_steps[ $i ] ) ) {
				continue;
			}
			$step = $previous_steps[ $i ];
			if ( isset( $step['prompts'] ) ) {
				$messages[] = array( 'role' => 'user', 'content' => $step['prompts'] );
			}
			if ( isset( $step['response'] ) ) {
				$messages[] = array( 'role' => 'assistant', 'content' => $step['response'] );
			}
		}
		$messages[] = array( 'role' => 'user', 'content' => implode( PHP_EOL, $prompts ) );

		$data = array(
			"model" => "claude-3-sonnet-20240229", // Claude Sonnet 4.0 model
			"system" => $system_instruction,
			"messages" => $messages,
			"max_tokens" => 2048,
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'body'    => wp_json_encode( $data ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-API-Key'     => $api_key,
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
		if ( ! wp_verify_nonce( $_POST['claude_api_key_nonce'], 'save_claude_api_key' ) ) {
			$url = $_POST['_wp_http_referer'];
			wp_safe_redirect( add_query_arg( array( 'info' => 'error', ), $url ) );
			exit;
		}
		$api_key = sanitize_text_field( $_POST['claude-api-key'] );
		$url     = esc_url_raw( $_POST['_wp_http_referer'] );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( add_query_arg( array( 'info' => 'error', ), $url ) );
			exit;
		}
		$default_api_key = sanitize_text_field( $_POST['default-claude-api-key'] );
		if ( $default_api_key === $api_key ) {
			wp_safe_redirect( add_query_arg( array( 'info' => 'error', ), $url ) );
			exit;
		}
		$this->save_key( $api_key );
		wp_safe_redirect( add_query_arg( array( 'info' => 'saved', ), $url ) );
		exit;
	}

	/**
	 * Encrypt and save the key.
	 *
	 * @param string $key The key to save.
	 * @return bool Whether the key was saved successfully.
	 */
	public function save_key( string $key ): bool {
		$encrypted_key = $this->encrypt_key( $key );
		return update_option( 'wp_performance_wizard_claude_api_key', $encrypted_key );
	}

	/**
	 * Decrypt and retrieve the key.
	 *
	 * @return string The decrypted key.
	 */
	public function get_api_key(): string {
		$encrypted_key = get_option( 'wp_performance_wizard_claude_api_key' );
		return $this->decrypt_key( $encrypted_key );
	}

	/**
	 * Encrypt the key.
	 *
	 * @param string $key The key to encrypt.
	 * @return string The encrypted key.
	 */
	public function encrypt_key( string $key ): string {
		$cipher = self::ENCRYPTION_CIPHER;
		$ivlen  = openssl_cipher_iv_length( $cipher );
		$iv     = openssl_random_pseudo_bytes( $ivlen );
		$salt   = openssl_random_pseudo_bytes( 32 );
		if ( ! defined( 'SECURE_AUTH_KEY' ) || ! defined( 'SECURE_AUTH_SALT' ) ) {
			return '';
		}
		$encryption_key = hash_pbkdf2( 'sha256', SECURE_AUTH_KEY . SECURE_AUTH_SALT, $salt, self::PBKDF2_ITERATIONS, self::KEY_LENGTH, true );
		$encrypted = openssl_encrypt( $key, $cipher, $encryption_key, OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $salt . $encrypted );
	}

	/**
	 * Decrypt the key.
	 *
	 * @param string $encrypted_key The encrypted key to decrypt.
	 * @return string The decrypted key.
	 */
	public function decrypt_key( string $encrypted_key ): string {
		$cipher = self::ENCRYPTION_CIPHER;
		$ivlen  = openssl_cipher_iv_length( $cipher );
		$combined  = base64_decode( $encrypted_key, true );
		$iv        = substr( $combined, 0, $ivlen );
		$salt      = substr( $combined, $ivlen, 32 );
		$encrypted = substr( $combined, $ivlen + 32 );
		if ( ! defined( 'SECURE_AUTH_KEY' ) || ! defined( 'SECURE_AUTH_SALT' ) ) {
			return '';
		}
		$encryption_key = hash_pbkdf2( 'sha256', SECURE_AUTH_KEY . SECURE_AUTH_SALT, $salt, self::PBKDF2_ITERATIONS, self::KEY_LENGTH, true );
		$decrypted      = openssl_decrypt( $encrypted, $cipher, $encryption_key, OPENSSL_RAW_DATA, $iv );
		return ( false === $decrypted ) ? '' : $decrypted;
	}

	/**
	 * Load the API key from the database if available. Otherwise fall back to file loading
	 *
	 * @return string The API key.
	 */
	public function load_api_key(): string {
		$stored_key = $this->get_api_key();
		if ( '' !== $stored_key ) {
			return $stored_key;
		} else {
			return parent::load_api_key();
		}
	}

	/**
	 * Request additional questions from the AI agent.
	 */
	public function getAdditionalQuestionsPrompt(): string {
		return 'Finally, based on the data collected and recommendations so far, provide two suggestions for follow up questions that the user could ask to get more information or further recommendations. For these questions, provide them as HTML buttons that the user can click to ask the question. Keep the questions succinct, a maximum of 16 words. For example: "<button class=\"wp-wizard-follow-up-question\">What is the best way to optimize my LCP image?</button>"';
	}
}
