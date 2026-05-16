<?php
/**
 * A class describing the Site Metrics data source.
 *
 * Collects backend site health metrics that influence performance: scheduled
 * cron jobs, the total size of autoloaded options, and database table sizes.
 *
 * @package wp-performance-wizard
 */

/**
 * Define the site metrics data source class.
 */
class Performance_Wizard_Data_Source_Site_Metrics extends Performance_Wizard_Data_Source_Base {

	/**
	 * Maximum number of largest autoloaded options to report.
	 */
	const MAX_AUTOLOAD_OPTIONS = 15;

	/**
	 * Construct the class, setting key variables.
	 */
	public function __construct() {
		parent::__construct();
		$this->set_name( 'Site Metrics' );
		$this->set_prompt( 'Collecting backend site metrics: scheduled cron jobs, autoloaded options size, and database table sizes...' );
		$this->set_description( 'The Site Metrics data source provides backend health metrics that commonly affect WordPress performance: the list of scheduled WP-Cron events, the total count and size of autoloaded options, and the sizes of the database tables used by this site.' );
		$this->set_analysis_strategy(
			'The Site Metrics data source can be analyzed by looking for backend bottlenecks that slow down page generation.
			Review the autoload total size: anything well over ~800KB-1MB is excessive and loaded on every request, so call out the largest autoloaded options and the plugins that likely created them.
			Review the cron jobs for events that run too frequently (for example every minute) or duplicate/orphaned hooks, since real WP-Cron runs on page loads and can add latency.
			Review the database table sizes for unusually large tables (often wp_options, wp_postmeta, wp_term_relationships, or plugin tables) that may need cleanup, indexing, or archiving. Correlate findings with the installed plugins and Lighthouse/HTML data where possible.'
		);
		$this->set_data_shape( "The returned data is a JSON object with three keys. 'cron_jobs' is an array of scheduled events, each with 'hook' (the action hook name), 'schedule' (the recurrence slug, or 'single' for one-off events), 'recurrence_seconds' (interval in seconds, or null), 'next_run_gmt' (human-readable next run time in UTC), and 'next_run_in' (human-readable time until next run); raw callback arguments are intentionally excluded. 'autoload' is an object with 'total_count' (number of autoloaded options), 'total_size_bytes' (combined size of their values), and 'largest_options' (an array of up to 15 entries, each with 'option_name' and 'size_bytes', ordered largest first). 'database_tables' is an array of tables that use this site's table prefix, each with 'table_name', 'row_estimate' (approximate row count from information_schema), and 'size_mb' (data plus index size in megabytes), ordered largest first. Any sub-section that fails to query returns an empty array or zeroed values rather than breaking the JSON." );
	}

	/**
	 * Get the site metrics data and return it as a structured JSON object.
	 *
	 * Each sub-section is collected independently and guarded so a single
	 * failing query still yields valid JSON for the remaining sections.
	 *
	 * @return string JSON encoded string of the site metrics data.
	 */
	public function get_data(): string {
		$to_return = array(
			'cron_jobs'       => $this->get_cron_jobs(),
			'autoload'        => $this->get_autoload_metrics(),
			'database_tables' => $this->get_database_tables(),
		);

		$encoded = wp_json_encode( $to_return );

		return false === $encoded ? '{}' : $encoded;
	}

	/**
	 * Get a summarized list of scheduled WP-Cron events.
	 *
	 * Reads the cron array directly via _get_cron_array() and summarizes each
	 * event without exposing raw callback arguments.
	 *
	 * @return array<int,array<string,mixed>> The list of scheduled events.
	 */
	private function get_cron_jobs(): array {
		$jobs = array();

		if ( ! function_exists( '_get_cron_array' ) ) {
			return $jobs;
		}

		// _get_cron_array() returns the cron option array, which is empty before
		// the cron option is initialized; iterating an empty array is harmless.
		$cron = _get_cron_array();
		$now  = time();

		foreach ( $cron as $timestamp => $hooks ) {
			$timestamp = (int) $timestamp;

			foreach ( $hooks as $hook => $events ) {
				if ( ! is_array( $events ) ) {
					continue;
				}

				foreach ( $events as $event ) {
					$schedule = isset( $event['schedule'] ) && false !== $event['schedule']
						? (string) $event['schedule']
						: 'single';

					$recurrence_seconds = isset( $event['interval'] ) ? (int) $event['interval'] : null;

					$jobs[] = array(
						'hook'               => (string) $hook,
						'schedule'           => $schedule,
						'recurrence_seconds' => $recurrence_seconds,
						'next_run_gmt'       => gmdate( 'Y-m-d H:i:s', $timestamp ) . ' UTC',
						'next_run_in'        => $timestamp > $now
							? human_time_diff( $now, $timestamp )
							: 'overdue',
					);
				}
			}
		}

		return $jobs;
	}

	/**
	 * Get autoloaded options metrics: total count, total size, and largest options.
	 *
	 * @return array<string,mixed> The autoload metrics.
	 */
	private function get_autoload_metrics(): array {
		global $wpdb;

		$metrics = array(
			'total_count'      => 0,
			'total_size_bytes' => 0,
			'largest_options'  => array(),
		);

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return $metrics;
		}

		// The autoload column value differs by WordPress version (yes/on/auto/auto-on),
		// so match all "autoloaded" values to remain robust across versions.
		$autoload_values = array( 'yes', 'on', 'auto', 'auto-on' );
		$placeholders    = implode( ', ', array_fill( 0, count( $autoload_values ), '%s' ) );

		// The table name and the $placeholders list are safe: $wpdb->options is a
		// trusted core property and $placeholders only ever contains "%s" tokens.
		// All user-supplied values are still bound through $wpdb->prepare().
		$totals_sql = "SELECT COUNT(*) AS total_count, SUM(LENGTH(option_value)) AS total_size_bytes FROM {$wpdb->options} WHERE autoload IN ( {$placeholders} )"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$totals = $wpdb->get_row( $wpdb->prepare( $totals_sql, $autoload_values ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared

		if ( is_object( $totals ) ) {
			$metrics['total_count']      = (int) $totals->total_count;
			$metrics['total_size_bytes'] = (int) $totals->total_size_bytes;
		}

		$largest_sql = "SELECT option_name, LENGTH(option_value) AS size_bytes FROM {$wpdb->options} WHERE autoload IN ( {$placeholders} ) ORDER BY size_bytes DESC LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$largest = $wpdb->get_results( $wpdb->prepare( $largest_sql, array_merge( $autoload_values, array( self::MAX_AUTOLOAD_OPTIONS ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared

		if ( is_array( $largest ) ) {
			foreach ( $largest as $option ) {
				$metrics['largest_options'][] = array(
					'option_name' => (string) $option->option_name,
					'size_bytes'  => (int) $option->size_bytes,
				);
			}
		}

		return $metrics;
	}

	/**
	 * Get the sizes of database tables that use this site's table prefix.
	 *
	 * @return array<int,array<string,mixed>> The list of tables with sizes.
	 */
	private function get_database_tables(): array {
		global $wpdb;

		$tables = array();

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! defined( 'DB_NAME' ) ) {
			return $tables;
		}

		// Match this site's tables by prefix; escape the LIKE wildcards in the prefix.
		$prefix_like = $wpdb->esc_like( $wpdb->prefix ) . '%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying information_schema for table sizes is a one-off diagnostic with no caching API equivalent; all values are bound via prepare().
				'SELECT TABLE_NAME AS table_name, TABLE_ROWS AS row_estimate, ROUND( ( DATA_LENGTH + INDEX_LENGTH ) / 1048576, 2 ) AS size_mb FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s ORDER BY ( DATA_LENGTH + INDEX_LENGTH ) DESC',
				DB_NAME,
				$prefix_like
			)
		);

		if ( is_array( $results ) ) {
			foreach ( $results as $table ) {
				$tables[] = array(
					'table_name'   => (string) $table->table_name,
					'row_estimate' => (int) $table->row_estimate,
					'size_mb'      => (float) $table->size_mb,
				);
			}
		}

		return $tables;
	}
}
