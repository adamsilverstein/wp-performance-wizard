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
	  * The name of the AI agent.
	  * 
	  * @var string
	  */
	 private $name = "Gemini";

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
	  */
	 public function call_api() {

	}

 }