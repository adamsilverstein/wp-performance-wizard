<?php
/**
 * Expert skills provider for the Performance Wizard.
 *
 * @package wp-performance-wizard
 */

/**
 * Loads vendored "agent skill" Markdown files (see includes/skills/NOTICE.md)
 * and maps analysis steps to the skills whose guidance should be included as
 * reference context in the prompt sent to the AI agent.
 *
 * This grounds recommendations in well-known web performance and WordPress
 * playbooks (web.dev Core Web Vitals, 10up best practices, WP-CLI diagnostics,
 * etc.) rather than relying solely on the model's prior knowledge.
 */
class Performance_Wizard_Skills {

	/**
	 * Registry of available skills keyed by slug.
	 *
	 * Each entry: label, source repo, license, path relative to the skills directory.
	 *
	 * @var array<string, array<string, string>>
	 */
	private $skills = array(
		'performance'           => array(
			'label'   => 'Web performance (Lighthouse & Core Web Vitals)',
			'source'  => 'addyosmani/web-quality-skills',
			'license' => 'MIT',
			'path'    => 'web-quality/performance.md',
		),
		'core-web-vitals'       => array(
			'label'   => 'Core Web Vitals (LCP, INP, CLS)',
			'source'  => 'addyosmani/web-quality-skills',
			'license' => 'MIT',
			'path'    => 'web-quality/core-web-vitals.md',
		),
		'best-practices'        => array(
			'label'   => 'Modern web best practices',
			'source'  => 'addyosmani/web-quality-skills',
			'license' => 'MIT',
			'path'    => 'web-quality/best-practices.md',
		),
		'wp-performance'        => array(
			'label'   => 'WordPress performance (backend)',
			'source'  => 'WordPress/agent-skills',
			'license' => 'GPL-2.0-or-later',
			'path'    => 'wordpress/wp-performance.md',
		),
		'wp-plugin-development' => array(
			'label'   => 'WordPress plugin development',
			'source'  => 'WordPress/agent-skills',
			'license' => 'GPL-2.0-or-later',
			'path'    => 'wordpress/wp-plugin-development.md',
		),
	);

	/**
	 * Map analysis step titles (or data source names) to the slugs of skills
	 * whose content should be injected as reference context.
	 *
	 * Keys are matched against either the step title or the data source name.
	 *
	 * @var array<string, string[]>
	 */
	private $step_skill_map = array(
		'Lighthouse'         => array( 'performance', 'core-web-vitals' ),
		'HTML'               => array( 'best-practices', 'performance' ),
		'Script Attribution' => array( 'performance', 'best-practices' ),
		'Themes and Plugins' => array( 'wp-performance', 'wp-plugin-development' ),
		'Summarize Results'  => array( 'core-web-vitals', 'wp-performance' ),
	);

	/**
	 * Absolute path to the skills directory.
	 *
	 * @var string
	 */
	private $base_path;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base_path = plugin_dir_path( __FILE__ ) . 'skills/';
	}

	/**
	 * Whether skill-based context injection is enabled.
	 *
	 * Filterable via `wp_performance_wizard_use_expert_skills`. Defaults to
	 * the setting stored on the settings page (default: enabled).
	 */
	public static function is_enabled(): bool {
		$options = Performance_Wizard_Settings_Page::get_options();
		$enabled = array_key_exists( 'use_expert_skills', $options ) ? (bool) $options['use_expert_skills'] : true;
		/**
		 * Filters whether bundled expert skill guidance is added to analysis prompts.
		 *
		 * @param bool $enabled Whether expert skill context is enabled.
		 */
		return apply_filters( 'wp_performance_wizard_use_expert_skills', $enabled );
	}

	/**
	 * Get the skill registry.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_skills(): array {
		return $this->skills;
	}

	/**
	 * Return the slugs of the skills whose content should be included for a given step.
	 *
	 * @param string $step_key The step title or data source name.
	 *
	 * @return string[] Slugs of matching skills. Empty if no mapping exists.
	 */
	public function get_skill_slugs_for_step( string $step_key ): array {
		$slugs = array();
		if ( isset( $this->step_skill_map[ $step_key ] ) ) {
			$slugs = $this->step_skill_map[ $step_key ];
		}
		/**
		 * Filters the skill slugs selected for a given analysis step.
		 *
		 * @param string[] $slugs    The default skill slugs for this step.
		 * @param string   $step_key The step title or data source name.
		 */
		$filtered = apply_filters( 'wp_performance_wizard_skill_slugs_for_step', $slugs, $step_key );
		// Drop unknown slugs so callers can trust the list.
		return array_values( array_intersect( array_keys( $this->skills ), $filtered ) );
	}

	/**
	 * Load the Markdown content of a skill.
	 *
	 * @param string $slug Skill slug from the registry.
	 *
	 * @return string Skill content, or empty string if the skill or its file is missing.
	 */
	public function get_skill_content( string $slug ): string {
		if ( ! isset( $this->skills[ $slug ] ) ) {
			return '';
		}
		$path = $this->base_path . $this->skills[ $slug ]['path'];
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a bundled read-only file.
		return false === $contents ? '' : $contents;
	}

	/**
	 * Build the combined reference-context prompt for a step.
	 *
	 * Returns an empty string if skills are disabled, no skills map to the
	 * step, or none of the mapped skills have readable content.
	 *
	 * @param string $step_key The step title or data source name.
	 *
	 * @return string A single prompt string prefixed with a short framing note,
	 *                ready to be appended to the step's prompt array.
	 */
	public function build_reference_prompt( string $step_key ): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		$slugs = $this->get_skill_slugs_for_step( $step_key );
		if ( 0 === count( $slugs ) ) {
			return '';
		}

		$sections = array();
		foreach ( $slugs as $slug ) {
			$content = trim( $this->get_skill_content( $slug ) );
			if ( '' === $content ) {
				continue;
			}
			$label      = $this->skills[ $slug ]['label'];
			$source     = $this->skills[ $slug ]['source'];
			$sections[] = "### Reference skill: {$label} (source: {$source})\n\n{$content}";
		}

		if ( 0 === count( $sections ) ) {
			return '';
		}

		$header = 'The following expert reference material is provided to ground your analysis in well-known web performance and WordPress best practices. Use it to inform your recommendations, cite specific patterns when relevant, and avoid generic advice. Do not echo this material back to the user.';

		return $header . "\n\n" . implode( "\n\n---\n\n", $sections );
	}
}
