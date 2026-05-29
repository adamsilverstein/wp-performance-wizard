<?php
/**
 * Minimal PHPStan stubs for the WordPress 7.0 AI Client API.
 *
 * Not loaded at runtime — this file exists only so static analysis can resolve
 * the AI Client symbols the plugin depends on. The real implementations ship
 * with WordPress core.
 *
 * @package wp-performance-wizard
 */

namespace WordPress\AiClient\Messages\DTO {

	/**
	 * Minimal Message DTO stub.
	 */
	class Message {
		/**
		 * Constructor.
		 *
		 * @param mixed              $role  Message role enum instance.
		 * @param array<int,mixed>   $parts Message parts.
		 */
		public function __construct( $role, array $parts ) {}
	}

	/**
	 * Minimal MessagePart DTO stub.
	 */
	class MessagePart {
		/**
		 * Constructor.
		 *
		 * @param mixed $content          Part content (string, File, FunctionCall, FunctionResponse).
		 * @param mixed $channel          Optional channel enum.
		 * @param mixed $thoughtSignature Optional thought signature.
		 */
		public function __construct( $content, $channel = null, $thoughtSignature = null ) {}
	}
}

namespace WordPress\AiClient\Providers\Http\DTO {

	/**
	 * Minimal RequestOptions DTO stub.
	 */
	class RequestOptions {
		public const KEY_TIMEOUT         = 'timeout';
		public const KEY_CONNECT_TIMEOUT = 'connectTimeout';
		public const KEY_MAX_REDIRECTS   = 'maxRedirects';

		/**
		 * Build from array.
		 *
		 * @param array<string,mixed> $array Options.
		 * @return self
		 */
		public static function fromArray( array $array ): self {
			return new self();
		}
	}
}

namespace WordPress\AiClient\Messages\Enums {

	/**
	 * Minimal MessageRoleEnum stub.
	 */
	class MessageRoleEnum {
		/**
		 * Returns a USER role instance.
		 *
		 * @return self
		 */
		public static function user(): self {
			return new self();
		}

		/**
		 * Returns a MODEL role instance.
		 *
		 * @return self
		 */
		public static function model(): self {
			return new self();
		}
	}
}

namespace {

	if ( ! class_exists( 'WP_AI_Client_Prompt_Builder' ) ) {
		/**
		 * Minimal WP_AI_Client_Prompt_Builder stub.
		 */
		class WP_AI_Client_Prompt_Builder {
			/**
			 * Provider.
			 *
			 * @param string $provider Provider id.
			 * @return self
			 */
			public function using_provider( string $provider ): self {
				return $this;
			}

			/**
			 * System instruction.
			 *
			 * @param string $instruction System instruction.
			 * @return self
			 */
			public function using_system_instruction( string $instruction ): self {
				return $this;
			}

			/**
			 * Max tokens.
			 *
			 * @param int $max_tokens Max tokens.
			 * @return self
			 */
			public function using_max_tokens( int $max_tokens ): self {
				return $this;
			}

			/**
			 * Model preference (ordered list of model IDs; falls back to a
			 * compatible model when none of them are available).
			 *
			 * @param string ...$model_ids Preferred model IDs.
			 * @return self
			 */
			public function using_model_preference( string ...$model_ids ): self {
				return $this;
			}

			/**
			 * Temperature.
			 *
			 * @param float $temperature Temperature.
			 * @return self
			 */
			public function using_temperature( float $temperature ): self {
				return $this;
			}

			/**
			 * History.
			 *
			 * @param mixed ...$messages Conversation history.
			 * @return self
			 */
			public function with_history( ...$messages ): self {
				return $this;
			}

			/**
			 * Request options (timeout, redirects, etc.).
			 *
			 * @param mixed $options RequestOptions instance.
			 * @return self
			 */
			public function using_request_options( $options ): self {
				return $this;
			}

			/**
			 * Generate text.
			 *
			 * @return string|\WP_Error
			 */
			public function generate_text() {
				return '';
			}
		}
	}

	if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
		/**
		 * Creates a new AI prompt builder.
		 *
		 * @param mixed $prompt Initial prompt content.
		 * @return WP_AI_Client_Prompt_Builder
		 */
		function wp_ai_client_prompt( $prompt = null ): WP_AI_Client_Prompt_Builder {
			return new WP_AI_Client_Prompt_Builder();
		}
	}
}
