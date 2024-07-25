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
	 * A method to send a single prompt to the agent.
	 */
	public function send_prompt( $prompt ) {
		return $this->send_prompts( array( $prompt ) );
	}

	/**
	 * A method for calling the API of the AI agent.
	 *
	 * @param array $prompts The prompts to pass to the agent.
	 *
	 * @return string The response from the API.
	 */
	public function send_prompts( $prompts ) {

		// Send a REST API request to the Gemini API, as documented here: https://ai.google.dev/gemini-api/docs/get-started/tutorial?lang=rest
		$api_base = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';
		$query_params = array(
			'key' => $this->api_key,
		);

		$parts = array(
			'text' => implode( "\n", $prompts ),
		);

		$data = array(
			'contents' => array(
				'parts' => $parts,
				'role'  => 'user',
			),
			'system_instructions' => array(
				'parts' => array(
					'text' => $this->get_wizard->system_prompt,
				),
			)
		);
		error_log( 'Data: ' . wp_json_encode( $data ) );
		$response = wp_remote_post(
			add_query_arg( $query_params, $api_base ),
			array(
				'body'    => wp_json_encode( $data ),
				'method'  => 'POST',
				'timeout' => 45,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		// Check for errors, then return the response parameters.
		if ( 200 !== $response['response']['code'] ) {
			return $response['response']['message'];
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		return $response_data['candidates'][0]['content']['parts'][0]['text'];
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
	 * Get the name of the agent.
	 */
	public function get_name() {
		return $this->name;
	}



	/**
	 * Construct the agent.
	 */
	function __construct() {
		// Set the name.
		$this->name = 'Gemini';
	}

}