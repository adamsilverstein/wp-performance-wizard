<?php
/**
 * Estimated token-usage tracking for the Performance Wizard.
 *
 * The WordPress AI Client returns generated text but does not expose a uniform
 * per-provider token count to this plugin, so usage is estimated from the byte
 * length of the prompt sent and the response received (~4 characters per token,
 * the common rule of thumb). Totals are accumulated per user for the current
 * analysis run so the UI can show how many tokens a run is consuming and an
 * approximate cost. Every figure is an estimate and is labeled as such.
 *
 * @package wp-performance-wizard
 */

/**
 * Records and reports estimated token usage for the current run.
 */
class Performance_Wizard_Usage {

	/**
	 * Transient key prefix; the current user ID is appended.
	 */
	const TRANSIENT_PREFIX = 'performance_wizard_usage_user_';

	/**
	 * Average characters per token used for the estimate.
	 */
	const CHARS_PER_TOKEN = 4;

	/**
	 * The per-user transient key for the current user.
	 *
	 * @return string The transient key.
	 */
	private static function key(): string {
		return self::TRANSIENT_PREFIX . get_current_user_id();
	}

	/**
	 * The empty/initial usage structure.
	 *
	 * @return array<string,mixed> The default usage data.
	 */
	private static function defaults(): array {
		return array(
			'total_input'  => 0,
			'total_output' => 0,
			'count'        => 0,
			'cost'         => 0.0,
			'last'         => null,
		);
	}

	/**
	 * Clear the accumulated usage for the current user.
	 */
	public static function reset(): void {
		delete_transient( self::key() );
	}

	/**
	 * Estimate the number of tokens represented by a character count.
	 *
	 * @param int $chars The number of characters.
	 *
	 * @return int The estimated token count.
	 */
	public static function estimate_tokens( int $chars ): int {
		if ( $chars <= 0 ) {
			return 0;
		}
		return (int) ceil( $chars / self::CHARS_PER_TOKEN );
	}

	/**
	 * Record one AI call's estimated usage and accumulate the run totals.
	 *
	 * @param string $connector_id The provider connector ID (for cost rates).
	 * @param int    $input_chars  Characters sent in the request (system + history + prompt).
	 * @param int    $output_chars Characters received in the response.
	 */
	public static function record( string $connector_id, int $input_chars, int $output_chars ): void {
		$input_tokens  = self::estimate_tokens( $input_chars );
		$output_tokens = self::estimate_tokens( $output_chars );

		$stored = get_transient( self::key() );
		$data   = is_array( $stored ) ? $stored : self::defaults();

		$total_input  = isset( $data['total_input'] ) ? (int) $data['total_input'] : 0;
		$total_output = isset( $data['total_output'] ) ? (int) $data['total_output'] : 0;
		$count        = isset( $data['count'] ) ? (int) $data['count'] : 0;
		$total_cost   = isset( $data['cost'] ) ? (float) $data['cost'] : 0.0;

		$rates     = self::rates( $connector_id );
		$call_cost = ( $input_tokens / 1000000 ) * $rates['input'] + ( $output_tokens / 1000000 ) * $rates['output'];

		$updated = array(
			'total_input'  => $total_input + $input_tokens,
			'total_output' => $total_output + $output_tokens,
			'count'        => $count + 1,
			'cost'         => $total_cost + $call_cost,
			'last'         => array(
				'input'  => $input_tokens,
				'output' => $output_tokens,
				'tokens' => $input_tokens + $output_tokens,
				'cost'   => $call_cost,
			),
		);

		set_transient( self::key(), $updated, DAY_IN_SECONDS );
	}

	/**
	 * Get the accumulated usage for the current user, with derived totals.
	 *
	 * @return array<string,mixed> The usage data for the REST response.
	 */
	public static function get(): array {
		$stored = get_transient( self::key() );
		$data   = is_array( $stored ) ? $stored : self::defaults();

		$total_input  = isset( $data['total_input'] ) ? (int) $data['total_input'] : 0;
		$total_output = isset( $data['total_output'] ) ? (int) $data['total_output'] : 0;
		$count        = isset( $data['count'] ) ? (int) $data['count'] : 0;
		$cost         = isset( $data['cost'] ) ? (float) $data['cost'] : 0.0;
		$last         = isset( $data['last'] ) && is_array( $data['last'] ) ? $data['last'] : null;

		return array(
			'total_input'    => $total_input,
			'total_output'   => $total_output,
			'total_tokens'   => $total_input + $total_output,
			'count'          => $count,
			'estimated_cost' => round( $cost, 4 ),
			'currency'       => 'USD',
			'estimated'      => true,
			'last'           => $last,
		);
	}

	/**
	 * Estimated token rates (USD per 1,000,000 tokens) for a connector.
	 *
	 * These are rough, mid-tier defaults used only to give an order-of-magnitude
	 * cost estimate; the selected model and current provider pricing will differ.
	 * Filter `wp_performance_wizard_token_rates` to adjust them.
	 *
	 * @param string $connector_id The provider connector ID.
	 *
	 * @return array{input:float,output:float} The input and output rates.
	 */
	private static function rates( string $connector_id ): array {
		$defaults = array(
			'anthropic' => array(
				'input'  => 3.0,
				'output' => 15.0,
			),
			'openai'    => array(
				'input'  => 2.5,
				'output' => 10.0,
			),
			'gemini'    => array(
				'input'  => 1.25,
				'output' => 5.0,
			),
		);
		$fallback = array(
			'input'  => 3.0,
			'output' => 15.0,
		);
		$rate     = isset( $defaults[ $connector_id ] ) ? $defaults[ $connector_id ] : $fallback;

		/**
		 * Filters the estimated token rates (USD per 1,000,000 tokens) for a connector.
		 *
		 * @param mixed  $rate         Array with 'input' and 'output' rates per 1,000,000 tokens.
		 * @param string $connector_id The provider connector ID.
		 */
		$rate = apply_filters( 'wp_performance_wizard_token_rates', $rate, $connector_id );

		if ( ! is_array( $rate ) ) {
			$rate = $fallback;
		}

		return array(
			'input'  => isset( $rate['input'] ) ? (float) $rate['input'] : $fallback['input'],
			'output' => isset( $rate['output'] ) ? (float) $rate['output'] : $fallback['output'],
		);
	}
}
