<?php
/**
 * A base class for performsnce wizard data sources.
 * 
 * Each data source has attributes that describe the source, a method to get the data
 * and a prompt to use when passing the data to the AI agent.
 */

class Performance_Wizard_Data_Source_Base {
	// Properties

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
	 * Get the name of the data source.
	 * 
	 * @return string The name of the data source.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the description of the data source.
	 * 
	 * @return string The description of the data source.
	 */
	public function get_description() {
		return $this->description;
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
	 * Get the shape of the data returned from the data source.
	 */
	public function get_data_shape() {
		return $this->data_shape;
	}
}