<?php
/**
 * A base class for performsnce wizard data sources.
 *
 * Each data source has attributes that describe the source, a method to get the data
 * and a prompt to use when passing the data to the AI agent.
 */

class Performance_Wizard_Data_Source_Base {

	/**
	 * Construct.
	 */
	function __constructor() {
	}

	/**
	 * The performance wizard object
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
	 */
	private $data_shape;

	/**
	 * The cache for data retrieved from the data source.
	 */
	private $cache;

	/**
	 * The cache length for this data source.
	 */
	private $cache_length;

	/**
	 * The description of a strategy that can be used to analyze this data source.
	 */
	private $analysis_strategy;

	// Constructor

	// Methods

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
	public function get_prompt() {
		return $this->prompt;
	}

	/**
	 * Set the prompt to use when passing the data to the AI agent.
	 */
	public function set_prompt( $prompt ) {
		$this->prompt = $prompt;
	}

	/**
	 * Get the prompt for the user. Users user_prompt, falling back to prompt if empty.
	 */
	public function get_user_prompt() {
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
	public function get_name() {
		return $this->name;
	}

	/**
	 * Set the name of the data source.
	 */
	public function set_name( $name ) {
		$this->name = $name;
	}

	/**
	 * Get the shape of the data returned from the data source.
	 */
	public function get_data_shape() {
		return $this->data_shape;
	}

	/**
	 * Get the description of the data source.
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get the cache for data retrieved from the data source.
	 */
	public function get_cache() {
		return $this->cache;
	}

	/**
	 * Get the cache length for this data source.
	 */
	public function get_cache_length() {
		return $this->cache_length;
	}

	/**
	 * Get the description of a strategy that can be used to analyze this data source.
	 */
	public function get_analysis_strategy() {
		return $this->analysis_strategy;
	}

	/**
	 * Set the analysis strategy.
	 */
	public function set_analysiss_strategy( $analysis_strategy ) {
		$this->analysis_strategy = $analysis_strategy;
	}

	/**
	 * Set the analysis strategy.
	 */
	public function set_analysis_strategy( $analysis_strategy ) {
		$this->analysis_strategy = $analysis_strategy;
	}

	/**
	 * Set the description for the data source.
	 */
	public function set_description( $description ) {
		$this->description = $description;
	}

	/**
	 * Get the wizard object.
	 */
	public function get_wizard() {
		return $this->wizard;
	}

	/**
	 * Basic constructor for the data source.
	 *
	 * @param Performance_Wizard $wizard The performance wizard object.
	 */
	public function __construct( $wizard) {
		$this->wizard = $wizard;
	}

}