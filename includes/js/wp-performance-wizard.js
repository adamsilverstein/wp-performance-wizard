jQuery( function( $ ) {
	const terminal = $( '#performance-wizard-terminal' ).terminal( function( command ) {}, {
		greetings: 'WP Performance Wizard',
		name: 'wppw',
		height: '100%',
		width: '100%',
		prompt: '',
		onCommandNotFound: function( term, command ) {
			echo( 'err', term, command );
			return false;
		}
	} );

	// @todo strings should be localized.
	terminal.echo( "[[b;green;]Let's get started...]" );

	// Run the analysis....
	runAnalysis( terminal );


	/**
	 * Run the analysis, interacting with the passed terminal.
	 */
	async function runAnalysis() {
		// @todo strings should be localized.
		terminal.echo( '[[b;green;]Running analysis...]' );

		// Get a description of the next step. Continue until the final step.
		const complete = false;
		const maxSteps = 25;
		let step = 0;
		while ( ! complete && step <= maxSteps ) {
			const nextStep = await getPerfomanceWizardNextStep( step );
			echoStep( nextStep.user_prompt );
			switch ( nextStep.action ) {
				case 'complete':
					complete = true;
					break;
				case 'run_action':
					results = await runPerfomanceWizardNextStep( step );
					console.log( results );
					terminal.echo( '[[b;white;]Analysis...]' );

					step++;
					break;
				case 'continue':
					step++;
					break;
			}
		}
		terminal.echo( '[[b;green;]Analysis complete...]' );
	}

	/**
	 * Echo out the next step, or an error if there is one.
	 */
	function echoStep( nextStep ) {
		if ( 'error' === nextStep || 'undefined' === nextStep ) {
			terminal.echo( '[[b;red;]Error: Unable to get the next step. Please try again.]' );
		} else {
			terminal.echo( '[[b;yellow;]Next step: ' + nextStep + ']' );
		}

	}


	/**
	 * Get the next step in the performance wizard.
	 * The endpoint is set up with
	 * register_rest_route( 'performance-wizard/v1', '/command/'...
	 *
	 * @param {int} step The current step in the wizard.
	 */
	function getPerfomanceWizardNextStep( step ) {
		// User= the REST API to get the next step.
		const params = {
			'command': '_get_next_action_',
			'step'   : step
		};
		return wp.apiFetch( {
			path  : '/performance-wizard/v1/command/',
			method: 'POST',
			data  : params
		} );
	}

	/**
	 * Run the next step in the performance wizard.
	 * The endpoint is set up with
	 * register_rest_route( 'performance-wizard/v1', '/command/'...
	 *
	 * @param {int} step The current step in the wizard.
	 */
	function runPerfomanceWizardNextStep( step ) {
		// User= the REST API to run the next step.
		const params = {
			'command': '_run_action_',
			'step'   : step
		};
		return wp.apiFetch( {
			path  : '/performance-wizard/v1/command/',
			method: 'POST',
			data  : params
		} );
	}
} );
