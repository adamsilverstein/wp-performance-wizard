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
		 *
		 * @param array $prompts The prompts to pass to the agent.
		 *
		 * @return string The response from the API.
		 */
		public function send_prompts( $prompts ) {
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



	}