<?php
/**
 * Minimal PHPStan stubs for the WordPress 7.0 Connectors API.
 *
 * Not loaded at runtime — this file exists only so static analysis can resolve
 * the Connectors API symbols the plugin depends on. The real implementations
 * ship with WordPress core.
 *
 * @package wp-performance-wizard
 */

/**
 * Registry passed to wp_connectors_init callbacks.
 */
class WP_Connector_Registry {
	/**
	 * Register a connector.
	 *
	 * @param string              $id   Connector ID.
	 * @param array<string,mixed> $args Connector metadata.
	 * @return bool
	 */
	public function register( string $id, array $args ): bool {
		return true;
	}

	/**
	 * Unregister and return a connector's metadata.
	 *
	 * @param string $id Connector ID.
	 * @return array<string,mixed>|null
	 */
	public function unregister( string $id ): ?array {
		return null;
	}

	/**
	 * Whether a connector is registered.
	 *
	 * @param string $id Connector ID.
	 * @return bool
	 */
	public function is_registered( string $id ): bool {
		return false;
	}

	/**
	 * Retrieve a single connector's metadata.
	 *
	 * @param string $id Connector ID.
	 * @return array<string,mixed>|null
	 */
	public function get_registered( string $id ): ?array {
		return null;
	}

	/**
	 * Retrieve all registered connectors.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_all_registered(): array {
		return array();
	}
}
