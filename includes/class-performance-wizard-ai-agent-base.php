<?php
/**
 * A base class for AI agents, eg. Gemini, ChatGPT, etc.
 *
 * Includes the name of the agent, a way to store the API key and a method to invoke the API.
 *
 * @package wp-performance-wizard
 */

/**
 * The agent base class.
 */
class Performance_Wizard_AI_Agent_Base {
	/**
	 * Encryption cipher method
	 */
	protected const ENCRYPTION_CIPHER = 'aes-256-cbc';

	/**
	 * Number of iterations for key derivation
	 */
	protected const PBKDF2_ITERATIONS = 10000;

	/**
	 * Length of derived key in bytes
	 */
	protected const KEY_LENGTH = 32;

	/**
	 * The private API key.
	 *
	 * @var string
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
	 * Get the api key.
	 *
	 * @return string The api key.
	 */
	public function get_api_key(): string {
		if ( null === $this->api_key ) {
			$this->api_key = $this->load_api_key();
		}
		return $this->api_key;
	}

	/**
	 * Set the api key.
	 *
	 * @param string $api_key The api key.
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
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
		$option_name = $this->get_api_key_option_name();
		return update_option( $option_name, $encrypted_key );
	}

	/**
	 * Get the standardized option name for storing the API key.
	 *
	 * @return string The option name.
	 */
	protected function get_api_key_option_name(): string {
		$agent_name = $this->get_name();
		if ( '' === $agent_name ) {
			return '';
		}
		$agent_slug = str_replace( ' ', '_', strtolower( $agent_name ) );
		return 'wp_performance_wizard_' . $agent_slug . '_api_key';
	}

	/**
	 * Encrypt the key.
	 *
	 * @param string $key The key to encrypt.
	 *
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

		$encryption_key = hash_pbkdf2(
			'sha256',
			SECURE_AUTH_KEY . SECURE_AUTH_SALT,
			$salt,
			self::PBKDF2_ITERATIONS,
			self::KEY_LENGTH,
			true
		);

		$encrypted = openssl_encrypt(
			$key,
			$cipher,
			$encryption_key,
			OPENSSL_RAW_DATA,
			$iv
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $iv . $salt . $encrypted );
	}

	/**
	 * Decrypt the key.
	 *
	 * @param string $encrypted_key The encrypted key to decrypt.
	 *
	 * @return string The decrypted key.
	 */
	public function decrypt_key( string $encrypted_key ): string {
		if ( empty( $encrypted_key ) ) {
			return '';
		}

		$cipher = self::ENCRYPTION_CIPHER;
		$ivlen  = openssl_cipher_iv_length( $cipher );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$combined  = base64_decode( $encrypted_key, true );
		if ( false === $combined ) {
			return '';
		}

		$iv        = substr( $combined, 0, $ivlen );
		$salt      = substr( $combined, $ivlen, 32 );
		$encrypted = substr( $combined, $ivlen + 32 );

		if ( ! defined( 'SECURE_AUTH_KEY' ) || ! defined( 'SECURE_AUTH_SALT' ) ) {
			return '';
		}

		$encryption_key = hash_pbkdf2(
			'sha256',
			SECURE_AUTH_KEY . SECURE_AUTH_SALT,
			$salt,
			self::PBKDF2_ITERATIONS,
			self::KEY_LENGTH,
			true
		);

		$decrypted = openssl_decrypt(
			$encrypted,
			$cipher,
			$encryption_key,
			OPENSSL_RAW_DATA,
			$iv
		);

		return ( false === $decrypted ) ? '' : $decrypted;
	}

	/**
	 * Render status messages based on the 'info' GET parameter.
	 *
	 * This method handles common status messages for API key operations
	 * and can be called by child classes in their render_admin_page methods.
	 */
	protected function render_status_messages(): void {
		if ( ! isset( $_GET['info'] ) ) {
			return;
		}

		$info = sanitize_text_field( $_GET['info'] );
		$agent_name = $this->get_name();

		switch ( $info ) {
			case 'saved':
				echo '<div class="notice notice-success"><p>API key saved successfully!</p></div>';
				break;
			case 'nonce_error':
				echo '<div class="notice notice-error"><p>Security check failed. Please try again.</p></div>';
				break;
			case 'permission_error':
				echo '<div class="notice notice-error"><p>You do not have permission to perform this action.</p></div>';
				break;
			case 'no_change':
				echo '<div class="notice notice-warning"><p>No changes were made to the API key.</p></div>';
				break;
			case 'invalid_key':
				echo '<div class="notice notice-error"><p>Invalid API key format. Please enter a valid ' . esc_html( $agent_name ) . ' API key.</p></div>';
				break;
			case 'save_failed':
				echo '<div class="notice notice-error"><p>Failed to save API key. Please try again.</p></div>';
				break;
			case 'exception':
				echo '<div class="notice notice-error"><p>An error occurred while saving the API key. Please check the error logs.</p></div>';
				break;
		}
	}

	/**
	 * Function to load the API key for an agent.
	 *
	 * First tries to load from the encrypted option, then falls back to
	 * the legacy option or JSON file.
	 *
	 * @return string The API key.
	 */
	public function load_api_key(): string {
		// First try to get the encrypted key from options
		$option_name = $this->get_api_key_option_name();
		$encrypted_key = get_option( $option_name );
		$decrypted_key = $this->decrypt_key( $encrypted_key );

		if ( '' !== $decrypted_key ) {
			return $decrypted_key;
		}

		// Fall back to legacy methods
		global $wp_filesystem;

		$agent_name = $this->get_name();

		if ( '' === $agent_name ) {
			return '';
		}

		// Construct the slug from the name.
		$agent_slug = str_replace( ' ', '-', strtolower( $agent_name ) );

		// First check the options table.
		$api_key = get_option( 'performance-wizard-api-key-' . $agent_slug );
		if ( '' !== $api_key && false !== $api_key ) {
			return $api_key;
		}

		// Next check the key file.
		$filename = plugin_dir_path( __FILE__ ) . '../.keys/' . $agent_slug . '-key.json';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$keydata = json_decode( $wp_filesystem->get_contents( $filename ) );
		return isset( $keydata->apikey ) ? $keydata->apikey : '';
	}
}
