<?php
/**
 * A class to configure the Performance Wizard Rest API endpoint
 */
class Performance_Wizard_Rest_API {

	/**
	 * Keep a handle on the base wizard class.
	 */
	private $wizard;

	/**
	 * Construct the class, passed a reference to the base class.
	 */
	function __construct( $wizard ) {
		$this->wizard = $wizard;
		$this->add_endpoint();
	}

	/**
	 * Add a command endpoint for the performance wizard.
	 * This endpoint will accept a command and return a response.
	 *
	 * @return void
	 */
	public function add_endpoint() {
		add_action( 'rest_api_init', function () {
			// Register the command route, requiring admin access to use.
			register_rest_route( 'performance-wizard/v1', '/command/', array(
				'methods'             => array( 'POST' ),
				'callback'            => array( $this, 'handle_command' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			) );
		} );
	}

	/**
	 * Handle a command sent to the Rest API 'command' endpoint.
	 *
	 * Takes commands including 'get_next_action', 'start' and
	 * 'run_action' and returns a response.
	 */
	public function handle_command( $request ) {
		$command = $request->get_param( 'command' );
		$step = $request->get_param( 'step' );
		$step = $step ? intval( $step ) : 0;
		error_log( 'Command: ' . $command );
		error_log( 'Step: ' . $step );
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
			default:
				$response = $this->wizard->get_analysis_plan()->prompt( $command );
		}

		return new WP_REST_Response( $response, 200 );
	}

}