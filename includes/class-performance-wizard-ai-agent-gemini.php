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
	 * Number of iterations for key derivation
	 */
	private const PBKDF2_ITERATIONS = 1000;

	/**
	 * Length of derived key in bytes
	 */
	private const KEY_LENGTH = 32;

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
	    public function send_prompts( array $prompts, int $current_step, array $previous_steps, bool $additional_questions, array $file_ids = array() ): string {

        // Send a REST API request to the Gemini API, as documented here: https://ai.google.dev/gemini-api/docs/get-started/tutorial?lang=rest.
		$api_base     = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent';
		$query_params = array(
			'key' => $this->get_api_key(),
		);


        $parts = array();
        foreach ($prompts as $prompt) {
            $parts[] = array('text' => $prompt);
        }


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

        if (!empty($file_ids)) {
            $data['context'] = array(
                'fileReferences' => array_map(function ($id) { return array('fileId' => $id); }, $file_ids)
            );
        }


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


    public function send_prompt( string $prompt, int $current_step, array $previous_steps, bool $additional_questions ): string {

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
	 * Request additional questions from the AI agent.
	 */
	public function getAdditionalQuestionsPrompt(): string {
		return 'Finally,  based on the data collected and recommendations so far, provide two suggestions for follow up questions that the user could ask to get more information or further recommendations. For these questions, provide them as HTML buttons that the user can click to ask the question. Keep the questions succinct, a maximum of 16 words. For example: "<button class="wp-wizard-follow-up-question">What is the best way to optimize my LCP image?</button>"';
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

		$default_api_key = '';
		$api_key         = $this->get_key();
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
						'info' => 'error',
					),
					$url
				)
			);
			exit;
		}

		$api_key = sanitize_text_field( $_POST['gemini-api-key'] );
		$url     = sanitize_text_field( $_POST['_wp_http_referer'] );

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

		$default_api_key = sanitize_text_field( $_POST['default-gemini-api-key'] );
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
		return update_option( 'wp_performance_wizard_gemini_api_key', $encrypted_key );
	}

	/**
	 * Decrypt and retrieve the key.
	 *
	 * @return string The decrypted key.
	 */
	public function get_key(): string {
		$encrypted_key = get_option( 'wp_performance_wizard_gemini_api_key' );
		return $this->decrypt_key( $encrypted_key );
	}

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
		$cipher = 'aes-256-cbc';
		$ivlen  = openssl_cipher_iv_length( $cipher );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$combined  = base64_decode( $encrypted_key, true );
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

		return openssl_decrypt(
			$encrypted,
			$cipher,
			$encryption_key,
			OPENSSL_RAW_DATA,
			$iv
		);
	}

	/**
	 * Load the API key from the database if available. Otherwise fall back to file loading
	 *
	 * @return string The API key.
	 */
	    /**
     * Uploads a file to the Gemini API.
     *
     * @param string $file_path The path to the file to upload.
     * @return string|WP_Error The file ID on success, or a WP_Error on failure.
     */
        /**
     * Gets the cached file ID for a file, or uploads and caches it if not found.
     *
     * @param string $file_path The path to the file.
     * @return string|WP_Error The file ID on success, or a WP_Error on failure.
     */
    public function get_cached_file_id(string $file_path): string|WP_Error {
        $transient_key = 'gemini_file_id_' . md5($file_path);
        $cached_id = get_transient($transient_key);

        if ($cached_id) {
            return $cached_id;
        }

        $uploaded_id = $this->upload_file($file_path);

        if (is_string($uploaded_id)) {
            set_transient($transient_key, $uploaded_id, DAY_IN_SECONDS);
            return $uploaded_id;
        } else {
            return $uploaded_id;
        }
    }


    /**
     * Uploads a file to the Gemini API.
     *
     * @param string $file_path The path to the file to upload.
     * @return string|WP_Error The file ID on success, or a WP_Error on failure.
     */
    public function upload_file(string $file_path): string|WP_Error {
        $api_base = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:uploadContent';
        $query_params = array(
            'key' => $this->get_api_key(),
        );

        global $wp_filesystem;
        WP_Filesystem();

        if ( ! $wp_filesystem->exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', 'File not found.' );
        }

        $file_contents = $wp_filesystem->get_contents( $file_path );

        $response = wp_remote_post(
            add_query_arg( $query_params, $api_base ),
            array(
                'body' => $file_contents,
                'method' => 'POST',
                'timeout' => 180,
                'headers' => array(
                    'Content-Type' => 'application/octet-stream',
                ),
            )
        );


        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( 200 !== $response['response']['code'] ) {
            return new WP_Error( 'api_error', $response['response']['message'] );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        if ( isset( $response_data['uploadId'] ) ) {
            return $response_data['uploadId'];
        } else {
            return new WP_Error( 'invalid_response', 'Invalid response from API.' );
        }
    }

public function load_api_key(): string {
		$stored_key = $this->get_key();
		if ( '' !== $stored_key ) {
			return $stored_key;
		} else {
			return parent::load_api_key();
		}
	}
}
