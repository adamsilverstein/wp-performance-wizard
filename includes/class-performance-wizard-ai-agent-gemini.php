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

	/**
	 * The name of the agent.
	*/
	private $name;

	/**
	 * A method for calling the API of the AI agent.
	 *
	 * @param string $prompt The prompt to pass to the agent.
	 *
	 * @return string The response from the API.
	 */
	public function send_prompt( $prompt ) {

		// Send a REST API request to the Gemini API, as documented here: https://ai.google.dev/gemini-api/docs/get-started/tutorial?lang=rest
		$api_base = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5:generateContent';
		$query_params = array(
			'key' => $this->api_key,
		);

		$data = array(
			'contents' => array(
				'parts' => array(
					array(
						'text' => $prompt,
					),
				),
			),
		);

		$response = wp_remote_post(
			add_query_arg( $query_params, $api_base ),
			array(
				'body'    => wp_json_encode( $data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		return $response;


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