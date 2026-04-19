<?php
/**
 * Unit tests for the AI agent base class key resolution via the Connectors API.
 *
 * @package wp-performance-wizard
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Covers Performance_Wizard_AI_Agent_Base::load_api_key().
 */
class WP_Performance_Wizard_Test extends TestCase {

	/**
	 * Reset option store and relevant env vars before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_performance_wizard_test_options'] = array();
		putenv( 'GEMINI_API_KEY' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
	}

	public function testReturnsEmptyStringWhenConnectorIdMissing(): void {
		$agent = new Performance_Wizard_AI_Agent_Base();
		$this->assertSame( '', $agent->load_api_key() );
	}

	public function testFallsBackToConnectorOption(): void {
		$GLOBALS['wp_performance_wizard_test_options']['connectors_ai_gemini_api_key'] = 'option-key';

		$agent = new Performance_Wizard_AI_Agent_Base();
		$agent->set_connector_id( 'gemini' );

		$this->assertSame( 'option-key', $agent->load_api_key() );
	}

	public function testEnvironmentVariableBeatsOption(): void {
		$GLOBALS['wp_performance_wizard_test_options']['connectors_ai_gemini_api_key'] = 'option-key';
		putenv( 'GEMINI_API_KEY=env-key' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv

		$agent = new Performance_Wizard_AI_Agent_Base();
		$agent->set_connector_id( 'gemini' );

		$this->assertSame( 'env-key', $agent->load_api_key() );
	}
}
