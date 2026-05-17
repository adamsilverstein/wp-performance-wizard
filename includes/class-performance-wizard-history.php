<?php
/**
 * A class to persist completed analysis runs as revisitable sessions.
 *
 * @package wp-performance-wizard
 */

/**
 * Stores completed analysis runs in a single capped option so users can
 * revisit a past run's transcript read-only from the admin page.
 */
class Performance_Wizard_History {

	/**
	 * The name of the option used to store the analysis history.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'performance_wizard_analysis_history';

	/**
	 * Maximum number of sessions retained. The oldest is evicted once exceeded.
	 *
	 * @var int
	 */
	const MAX_SESSIONS = 20;

	/**
	 * The transcript entry types that may be archived.
	 *
	 * @var string[]
	 */
	const ALLOWED_TYPES = array( 'step', 'recommendations', 'qa' );

	/**
	 * Archive a completed analysis run as a non-destructive snapshot.
	 *
	 * The transcript is captured client-side because the final recommendations
	 * and follow-up Q&A are not persisted server-side. The plan steps option is
	 * intentionally left untouched so post-completion follow-up questions keep
	 * working.
	 *
	 * @param string           $model        The AI model used for the run.
	 * @param array<int,mixed> $data_sources The data source names selected for the run.
	 * @param array<int,mixed> $transcript   The captured transcript entries.
	 *
	 * @return array<string,mixed> The saved record, or an empty array when nothing was archived.
	 */
	public function archive( string $model, array $data_sources, array $transcript ): array {
		$clean_transcript = $this->sanitize_transcript( $transcript );

		// Nothing meaningful to store.
		if ( array() === $clean_transcript ) {
			return array();
		}

		$clean_model = sanitize_text_field( $model );

		$clean_sources = array();
		foreach ( $data_sources as $data_source ) {
			$clean_sources[] = sanitize_text_field( (string) $data_source );
		}

		$history = $this->get_history();

		// Dedupe: skip when the newest existing record has an identical transcript.
		if ( array() !== $history ) {
			$newest = $history[0];
			if ( isset( $newest['transcript'] ) && $newest['transcript'] === $clean_transcript ) {
				return $newest;
			}
		}

		$record = array(
			'id'           => uniqid( 'pw_', true ),
			'created'      => current_time( 'mysql' ),
			'timestamp'    => time(),
			'model'        => $clean_model,
			'data_sources' => $clean_sources,
			'transcript'   => $clean_transcript,
		);

		// Newest first, capped to MAX_SESSIONS (evict oldest).
		array_unshift( $history, $record );
		if ( count( $history ) > self::MAX_SESSIONS ) {
			$history = array_slice( $history, 0, self::MAX_SESSIONS );
		}

		update_option( self::OPTION_NAME, $history, false );

		return $record;
	}

	/**
	 * Get a lightweight listing of stored sessions.
	 *
	 * The transcript itself is omitted for an efficient listing; an entry count
	 * is included instead.
	 *
	 * @return array<int,array<string,mixed>> The sessions, newest first, without transcripts.
	 */
	public function get_sessions(): array {
		$sessions = array();

		foreach ( $this->get_history() as $record ) {
			$transcript = isset( $record['transcript'] ) && is_array( $record['transcript'] ) ? $record['transcript'] : array();

			$sessions[] = array(
				'id'           => isset( $record['id'] ) ? (string) $record['id'] : '',
				'created'      => isset( $record['created'] ) ? (string) $record['created'] : '',
				'timestamp'    => isset( $record['timestamp'] ) ? (int) $record['timestamp'] : 0,
				'model'        => isset( $record['model'] ) ? (string) $record['model'] : '',
				'data_sources' => isset( $record['data_sources'] ) && is_array( $record['data_sources'] ) ? $record['data_sources'] : array(),
				'entry_count'  => count( $transcript ),
			);
		}

		return $sessions;
	}

	/**
	 * Get the full stored record for a single session.
	 *
	 * @param string $id The session id.
	 *
	 * @return array<string,mixed>|null The full record, or null when not found.
	 */
	public function get_session( string $id ): ?array {
		foreach ( $this->get_history() as $record ) {
			if ( isset( $record['id'] ) && (string) $record['id'] === $id ) {
				return $record;
			}
		}

		return null;
	}

	/**
	 * Get the raw stored history list.
	 *
	 * @return array<int,array<string,mixed>> The stored records, newest first.
	 */
	private function get_history(): array {
		$history = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $history ) ) {
			return array();
		}

		return $history;
	}

	/**
	 * Validate and sanitize a client-supplied transcript.
	 *
	 * Each entry's type is whitelisted and its title is sanitized. The content
	 * is kept as a plain string (cast only) because it is Markdown that the
	 * client re-renders through marked(); it is intentionally not passed
	 * through wp_kses.
	 *
	 * @param array<int,mixed> $transcript The raw transcript entries.
	 *
	 * @return array<int,array<string,string>> The sanitized transcript entries.
	 */
	private function sanitize_transcript( array $transcript ): array {
		$clean = array();

		foreach ( $transcript as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$type = isset( $entry['type'] ) ? (string) $entry['type'] : '';
			if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
				continue;
			}

			$title   = isset( $entry['title'] ) ? sanitize_text_field( (string) $entry['title'] ) : '';
			$content = isset( $entry['content'] ) ? (string) $entry['content'] : '';

			$clean[] = array(
				'type'    => $type,
				'title'   => $title,
				'content' => $content,
			);
		}

		return $clean;
	}
}
