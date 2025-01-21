<?php
/**
 * A class to configure the Performance Wizard Rest API endpoint
 *
 * @package wp-performance-wizard
 */

/**
 * The REST API class.
 */
class Performance_Wizard_Rest_API {

	/**
	 * Keep a handle on the base wizard class.
	 *
	 * @var WP_Performance_Wizard
	 */
	private $wizard;

	/**
	 * Construct the class, passed a reference to the base class.
	 *
	 * @param WP_Performance_Wizard $wizard The wizard to use.
	 */
	public function __construct( WP_Performance_Wizard $wizard ) {
		$this->wizard = $wizard;
		$this->add_endpoint();
	}

	/**
	 * Add a command endpoint for the performance wizard.
	 * This endpoint will accept a command and return a response.
	 */
	public function add_endpoint(): void {
		add_action(
			'rest_api_init',
			function (): void {
				// Register the command route, requiring admin access to use.
				register_rest_route(
					'performance-wizard/v1',
					'/command/',
					array(
						'methods'             => array( 'POST' ),
						'callback'            => array( $this, 'handle_command' ),
						'permission_callback' => static function () {
							return current_user_can( 'manage_options' );
						},
					)
				);
			}
		);
	}

	/**
	 * Handle a command sent to the Rest API 'command' endpoint.
	 *
	 * Takes commands including 'get_next_action', 'start' and
	 * 'run_action' and returns a response.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public function handle_command( WP_REST_Request $request ): WP_REST_Response {
		$command              = $request->get_param( 'command' );
		$step                 = $request->get_param( 'step' );
		$step                 = $step ? intval( $step ) : 0;
		$additional_questions = $request->get_param( 'additional_questions' );
		$agent                = $request->get_param( 'agent' );
		$response             = '';

		// Set the agent on the wizard.
		if ( '' !== $agent ) {
			$all_agents = $this->wizard->get_supported_agents();
			if ( array_key_exists( $agent, $all_agents ) ) {
				$agent_class = new $all_agents[ $agent ]( $this->wizard );
				$this->wizard->set_ai_agent( $agent_class );
			}
		}

		// Log this event.
		error_log( 'Command: ' . $command . ' Step: ' . $step . ' Additional Questions: ' . $additional_questions . ' Agent: ' . $agent );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		switch ( $command ) {
			case '_get_next_action_':
				$response = $this->wizard->get_analysis_plan()->get_next_action( $step );
				break;
			case '_start_':
				$response = $this->wizard->get_analysis_plan()->start();
				break;
			case '_run_action_':
				$response = $this->wizard->get_analysis_plan()->run_action( $step );
				break;
			case '_prompt_':
				$prompt         = $request->get_param( 'prompt' );
				$previous_steps = get_option( $this->wizard->get_option_name(), array() );

				// Handle the special case of the prompt 'compare' which prompts the system to run a new Lighthouse report and compare the results to the previous one.
				if ( 'compare' === $prompt ) {
					$response = $this->wizard->get_analysis_plan()->compare();
				} else {
					$response = $this->wizard->get_ai_agent()->send_prompt( $prompt, $step, $previous_steps, $additional_questions );
				}
		}

		return new WP_REST_Response( $response, 200 );
	}
}
