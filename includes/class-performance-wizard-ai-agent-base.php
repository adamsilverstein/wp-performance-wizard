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
	 * Function to load the API key for an agent.
	 *
	 * The key can either be stored as an option under 'performance-wizard-api-key-[api slug]'or as a JSON file with the key "apikey"
	 *
	 * @return string The API key.
	 */
	public function load_api_key(): string {
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
		return $keydata->apikey;
	}

	/**
	 * Number of iterations for key derivation
	 */
	private const PBKDF2_ITERATIONS = 1000;

	/**
	 * Length of derived key in bytes
	 */
	private const KEY_LENGTH = 32;

	/**
	 * Encrypt the key.
	 *
	 * @param string $key The key to encrypt.
	 *
	 * @return string The encrypted key.
	 */
	public function encrypt_key( string $key ): string {
		$cipher = 'aes-256-cbc';
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
		if ( '' === $encrypted_key ) {
			return '';
		}

		$cipher = 'aes-256-cbc';
		$ivlen  = openssl_cipher_iv_length( $cipher );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$combined = base64_decode( $encrypted_key, true );
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

		return false === $decrypted ? '' : $decrypted;
	}
}
