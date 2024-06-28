<?php
/**
 * A class that encapulates the strategy to be used by the AI agent when 
 * analyzing the performance of a WordPress site. Describes the steps the agernt will use
 * and the prompts it will use to gather data.
 */

class Performance_Wizard_Analysis_Plan {
	// Properties

	/**
	 * The name of the analysis plan.
	 * 
	 * @var string
	 */
	private $name;

	/**
	 * The description of the analysis plan.
	 * 
	 * @var string
	 */
	private $description;

	/**
	 * The steps to be used by the AI agent when analyzing the performance of a WordPress site.
	 * 
	 * @var array
	 */
	private $steps;

	// Constructor

	/**
	 * Create a new instance of the Performance_Wizard_Analysis_Plan class.
	 * 
	 * @param string $name The name of the analysis plan.
	 * @param string $description The description of the analysis plan.
	 * @param array $steps The steps to be used by the AI agent when analyzing the performance of a WordPress site.
	 */
	public function __construct( $name, $description, $steps ) {
		$this->name = $name;
		$this->description = $description;
		$this->steps = $steps;
	}

	// Methods

	/**
	 * Get the name of the analysis plan.
	 * 
	 * @return string The name of the analysis plan.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the description of the analysis plan.
	 * 
	 * @return string The description of the analysis plan.
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get the steps to be used by the AI agent when analyzing the performance of a WordPress site.
	 * 
	 * @return array The steps to be used by the AI agent when analyzing the performance of a WordPress site.
	 */
	public function get_steps() {
		return $this->steps;
	}
}