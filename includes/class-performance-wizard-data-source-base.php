<?php
/**
 * A base class for performance wizard data sources.
 *
 * Each data source has attributes that describe the source, a method to get the data
 * and a prompt to use when passing the data to the AI agent.
 *
 * @package wp-performance-wizard
 */

/**
 * Yje base class.
 */
class Performance_Wizard_Data_Source_Base {

	/**
	 * The performance wizard object
	 *
	 * @var WP_Performance_Wizard
	 */
	protected $wizard;

	/**
	 * The name of the data source.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The description of the data source.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * The prompt to use when passing the data to the AI agent.
	 *
	 * @var string
	 */
	private $prompt;

	/**
	 * The prompt to the user explaining this data source. Falls back to prompt.
	 *
	 * @var string
	 */
	private $user_prompt;

	/**
	 * A plain text description of the shape of the data returned from the data source.
	 *
	 * @var string
	 */
	private $data_shape;

	/**
	 * The description of a strategy that can be used to analyze this data source.
	 *
	 * @var string
	 */
	private $analysis_strategy;

	/**
	 * Get the data from the data source.
	 *
	 * @return mixed The data from the data source.
	 */
	public function get_data() {
		// To be implemented by subclasses.
	}


	/**
	 * Get the prompt to use when passing the data to the AI agent.
	 *
	 * @return string The prompt to use when passing the data to the AI agent.
	 */
	public function get_prompt(): string {
		return $this->prompt;
	}

	/**
	 * Set the prompt to use when passing the data to the AI agent.
	 *
	 * @param string $prompt The prompt to use when passing the data to the AI agent.
	 */
	public function set_prompt( string $prompt ): void {
		$this->prompt = $prompt;
	}

	/**
	 * Get the prompt for the user. Users user_prompt, falling back to prompt if empty.
	 *
	 * @return string The prompt for the user.
	 */
	public function get_user_prompt(): string {
		$user_prompt = $this->user_prompt;
		if ( empty( $user_prompt ) ) {
			$user_prompt = $this->prompt;
		}
		return $user_prompt;
	}


	/**
	 * Get the name of the data source.
	 *
	 * @return string The name of the data source.
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Set the name of the data source.
	 *
	 * @param string $name The name of the data source.
	 */
	public function set_name( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Get the shape of the data returned from the data source.
	 *
	 * @return string The shape of the data returned from the data source.
	 */
	public function get_data_shape(): string {
		return $this->data_shape;
	}

	/**
	 * Get the description of the data source.
	 *
	 * @return string The description of the data source.
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Get the description of a strategy that can be used to analyze this data source.
	 *
	 * @return string The analysis strategy.
	 */
	public function get_analysis_strategy(): string {
		return $this->analysis_strategy;
	}

	/**
	 * Set the analysis strategy.
	 *
	 * @param string $analysis_strategy The analysis strategy.
	 */
	public function set_analysis_strategy( string $analysis_strategy ): void {
		$this->analysis_strategy = $analysis_strategy;
	}

	/**
	 * Set the description for the data source.
	 *
	 * @param string $description The description for the data source.
	 */
	public function set_description( string $description ): void {
		$this->description = $description;
	}

	/**
	 * Get the wizard object.
	 *
	 * @return WP_Performance_Wizard The performance wizard object.
	 */
	public function get_wizard(): WP_Performance_Wizard {
		return $this->wizard;
	}

	/**
	 * Basic constructor for the data source.
	 *
	 * @param WP_Performance_Wizard $wizard The performance wizard object.
	 */
	public function __construct( WP_Performance_Wizard $wizard ) {
		$this->wizard = $wizard;
	}
}
