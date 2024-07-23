<?php
/**
 * A base class for AI agents, eg. Gemini, ChatGPT, etc.
 *
 * Includes the name of the agent, a way to store the API key and a method to invoke the API.
 *
 * @package wp-performance-wizard
 */

class Performance_Wizard_AI_Agent_Base {
		/**
		 * The private API key.
		 */
		private $api_key;

		/**
		 * The name of the AI agent.
		 *
		 * @var string
		 */
		private $name;

		/**
		 * The description of the AI agent.
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
		 * A method for calling the API of the AI agent.
		 */
		public function send_prompt() {
			// To be implemented by subclasses.
		}

		/**
		 * Get the name of the AI agent.
		 *
		 * @return string The name of the AI agent.
		 */
		public function get_name() {
			return $this->name;
		}

		/**
		 * Get the description of the AI agent.
		 *
		 * @return string The description of the AI agent.
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





	}