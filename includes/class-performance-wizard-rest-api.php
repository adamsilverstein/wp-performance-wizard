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

				// Register the save model preference route.
				register_rest_route(
					'performance-wizard/v1',
					'/save-model-preference/',
					array(
						'methods'             => array( 'POST' ),
						'callback'            => array( $this, 'save_model_preference' ),
						'permission_callback' => static function () {
							return current_user_can( 'manage_options' );
						},
					)
				);

				// Register the reset conversation route.
				register_rest_route(
					'performance-wizard/v1',
					'/reset-conversation/',
					array(
						'methods'             => array( 'POST' ),
						'callback'            => array( $this, 'reset_conversation' ),
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
		$model                = $request->get_param( 'model' );
		$response             = '';

		// Set the AI agent based on the selected model if provided.
		if ( is_string( $model ) && '' !== $model ) {
			$model_set = $this->wizard->set_ai_agent( $model );
			if ( ! $model_set ) {
				return new WP_REST_Response(
					array( 'error' => 'Invalid or unconfigured AI model: ' . $model ),
					400
				);
			}
		}

		// Commands that require a configured AI agent. Fail early with a clear
		// message instead of crashing downstream when no provider is connected.
		$ai_required = array( '_get_next_action_', '_start_', '_run_action_', '_prompt_' );
		if ( in_array( $command, $ai_required, true ) && null === $this->wizard->get_ai_agent() ) {
			$connectors_url = Performance_Wizard_Admin_Page::get_connectors_screen_url();
			return new WP_REST_Response(
				array(
					'error'          => __( 'No AI provider is connected. Configure an AI connector (Anthropic, Google Gemini, or OpenAI) to run an analysis.', 'wp-performance-wizard' ),
					'connectors_url' => $connectors_url,
				),
				503
			);
		}

		switch ( $command ) {
			case '_get_next_action_':
				// The first call of a run (step 0) marks a fresh start, so clear
				// the estimated usage accumulated by any previous run.
				if ( 0 === $step ) {
					Performance_Wizard_Usage::reset();
				}
				$response = $this->wizard->get_analysis_plan()->get_next_action( $step );
				break;
			case '_get_usage_':
				$response = Performance_Wizard_Usage::get();
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
				break;
			case '_archive_session_':
				$data_sources = $request->get_param( 'data_sources' );
				$transcript   = $request->get_param( 'transcript' );

				$data_sources = is_array( $data_sources ) ? $data_sources : array();
				$transcript   = is_array( $transcript ) ? $transcript : array();
				$model        = is_string( $model ) ? $model : '';

				$response = $this->wizard->get_history()->archive( $model, $data_sources, $transcript );
				break;
			case '_get_sessions_':
				$response = $this->wizard->get_history()->get_sessions();
				break;
			case '_get_session_':
				$session_id = $request->get_param( 'session_id' );
				$session_id = is_string( $session_id ) ? sanitize_text_field( $session_id ) : '';
				$session    = $this->wizard->get_history()->get_session( $session_id );

				if ( null === $session ) {
					return new WP_REST_Response(
						array( 'error' => 'Session not found.' ),
						404
					);
				}

				$response = $session;
				break;
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle saving the user's AI model preference.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public function save_model_preference( WP_REST_Request $request ): WP_REST_Response {
		// Verify nonce for security.
		$nonce = $request->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'save_model_preference' ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid nonce' ),
				403
			);
		}

		$model = $request->get_param( 'model' );
		if ( null === $model || '' === $model ) {
			return new WP_REST_Response(
				array( 'error' => 'Model parameter is required' ),
				400
			);
		}

		// Validate that the model is supported and available.
		$available_models = $this->wizard->get_available_models();
		if ( ! isset( $available_models[ $model ] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid or unavailable model: ' . $model ),
				400
			);
		}

		// Save the preference in a user-specific transient for 1 year.
		$transient_key = $this->wizard->get_model_preference_transient_key();
		$expiration    = YEAR_IN_SECONDS; // 1 year.

		$saved = set_transient( $transient_key, $model, $expiration );

		if ( $saved ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Model preference saved successfully',
				),
				200
			);
		} else {
			return new WP_REST_Response(
				array( 'error' => 'Failed to save model preference' ),
				500
			);
		}
	}

	/**
	 * Reset the current conversation.
	 *
	 * Deletes the stored analysis plan steps option so a fresh analysis
	 * starts with a clean slate.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public function reset_conversation( WP_REST_Request $request ): WP_REST_Response {
		// Verify nonce for security.
		$nonce = $request->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'reset_conversation' ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid nonce' ),
				403
			);
		}

		// Delete the stored conversation so the next analysis starts clean.
		delete_option( $this->wizard->get_option_name() );

		// Clear the estimated token usage for the run as well.
		Performance_Wizard_Usage::reset();

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Conversation reset successfully',
			),
			200
		);
	}
}
