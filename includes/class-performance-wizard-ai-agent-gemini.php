<?php
/**
 * A class that enables connections to Gemini AI.
 */

 class Performance_Wizard_AI_Agent_Gemini extends Performance_Wizard_AI_Agent_Base {
	 // Properties

	 /**
	  * The private API key.
	  */
	 private $api_key;

	 /**
	  * The description of the AI agent.
	  *
	  * @var string
	  */
	 private $description = "Gemini is a is a generative artificial intelligence chatbot developed by Google.";

	 /**
	  * The prompt to use when passing the data to the AI agent.
	  *
	  * @var string
	  */
	 private $prompt;

	 // Constructor

	 // Methods

	 /**
	  * Call the API of the AI agent.
	  *
	  * @return string The response from the API.
	  */
	 public function send_prompt() {

	}

	/**
	 * Set the API key.
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Get the API key.
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * Construct the agent.
	 */
	function __construct() {
		// Set the name.
		$this->name = 'Gemini';
	}

 }