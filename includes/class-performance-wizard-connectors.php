<?php
/**
 * Connectors API bootstrap for the Performance Wizard.
 *
 * Registers the AI provider connectors the plugin relies on if they have not
 * already been registered by core or another plugin. Users configure the
 * resulting credentials via the core Connectors admin screen.
 *
 * @package wp-performance-wizard
 */

/**
 * Registers AI connectors used by the Performance Wizard.
 */
class Performance_Wizard_Connectors {

	/**
	 * Wire up the connectors bootstrap.
	 */
	public function __construct() {
		add_action( 'wp_connectors_init', array( $this, 'register_connectors' ) );
	}

	/**
	 * Default connector definitions the plugin will register if missing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_default_connectors(): array {
		return array(
			'anthropic' => array(
				'name'           => __( 'Anthropic', 'wp-performance-wizard' ),
				'description'    => __( 'Text generation with Claude.', 'wp-performance-wizard' ),
				'type'           => 'ai_provider',
				'authentication' => array(
					'method'          => 'api_key',
					'credentials_url' => 'https://console.anthropic.com/settings/keys',
					'setting_name'    => 'connectors_ai_anthropic_api_key',
				),
			),
			'openai'    => array(
				'name'           => __( 'OpenAI', 'wp-performance-wizard' ),
				'description'    => __( 'Text generation with ChatGPT models.', 'wp-performance-wizard' ),
				'type'           => 'ai_provider',
				'authentication' => array(
					'method'          => 'api_key',
					'credentials_url' => 'https://platform.openai.com/api-keys',
					'setting_name'    => 'connectors_ai_openai_api_key',
				),
			),
			'gemini'    => array(
				'name'           => __( 'Google Gemini', 'wp-performance-wizard' ),
				'description'    => __( 'Text generation with Gemini models.', 'wp-performance-wizard' ),
				'type'           => 'ai_provider',
				'authentication' => array(
					'method'          => 'api_key',
					'credentials_url' => 'https://aistudio.google.com/app/apikey',
					'setting_name'    => 'connectors_ai_gemini_api_key',
				),
			),
		);
	}

	/**
	 * Register any AI connectors that are not already present in the registry.
	 *
	 * @param object $registry The WP_Connector_Registry passed by wp_connectors_init.
	 */
	public function register_connectors( object $registry ): void {
		if ( ! method_exists( $registry, 'register' ) || ! method_exists( $registry, 'is_registered' ) ) {
			return;
		}

		foreach ( self::get_default_connectors() as $id => $args ) {
			if ( $registry->is_registered( $id ) ) {
				continue;
			}
			$registry->register( $id, $args );
		}
	}
}
