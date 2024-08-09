
( function() {

	// The performance-wizard-terminal div will be used to display all communications with the agent.
	const terminal = document.getElementById( 'performance-wizard-terminal' );

	// Set up the terminal.
	echoToTerminal( '## Welcome to the Performance Wizard' );

	// Wait until the performance-wizard-start button has been click, then proceed with analysis.
	document.getElementById( 'performance-wizard-start' ).addEventListener( 'click', function() {
		// @todo strings should be localized.
		// Run the analysis....
		runAnalysis( terminal );
	} );

	/**
	 * Run the analysis, interacting with the passed terminal.
	 */
	async function runAnalysis() {
		// @todo strings should be localized.
		echoToTerminal( '### <g>Running analysis...</g>' );
		// Get a description of the next step. Continue until the final step.
		const complete = false;
		const maxSteps = 25;
		let step = 0;
		while ( ! complete && step <= maxSteps ) {
			const nextStep = await getPerfomanceWizardNextStep( step );
			echoStep( nextStep.user_prompt, nextStep.title );
			switch ( nextStep.action ) {
				case 'complete':
					complete = true;
					break;
				case 'run_action':
					results = await runPerfomanceWizardNextStep( step );
					console.log( results );
					echoToTerminal( '### <dg>Analysis...</dg>' );

					// Iterate thru all of the results returned in the response
					for( const resultIndex in results ) {
						const result = results[ resultIndex ];
						echoToTerminal();
						// If the results start with "Q: ", then it is a question. Remove that part and display the question in yellow.
						if ( result.startsWith( '>Q: ' ) ) {
							echoToTerminal( '<user-chip>USER</user-chip><br><y>' + result.replace( '>Q: ', '' ) + '</y>' );
						}
						// Similarly, results starting with ">A: " are answers. Remove that part and display the answer in white.
						else if ( result.startsWith( '>A: ' ) ) {
							echoToTerminal( '<agent-chip>AGENT</agent-chip><br><dg>' + result.replace( '>A: ', '' ) + '</dg>' );
						}
					};

					step++;
					break;
				case 'prompt':
					step++
					echoToTerminal( '<user-chip>USER</user-chip><br>' );
					echoToTerminal( '<y>' + nextStep.user_prompt  + '</y>' );
					results = await runPerfomanceWizardPrompt( nextStep.user_prompt, step );
					echoToTerminal( '<agent-chip>AGENT</agent-chip><br>')
					echoToTerminal( '<dg>' + results + '</dg>' );
					break;
				case 'continue':
					step++;
					break;
			}
		}
		echoToTerminal( '<g>Analysis complete...</g>' );
	}

	/**
	 * Echo out the next step, or an error if there is one.
	 */
	function echoStep( nextStep, title = 'Next step') {
		if ( 'error' === nextStep || 'undefined' === nextStep ) {
			echoToTerminal( '<r>Error: Unable to get the next step. Please try again.</r>' );
		} else {
			echoToTerminal( '<y>' + title + ': ' + nextStep + '</y>' );
		}

	}

	/**
	 * Echo out a message to the page, appending to the DOM.
	 *
	 * @param {string} message The message to display.
	 */
	function echoToTerminal( message = false ) {
		if ( false === message ) {
			const br = document.createElement( 'br' );
			terminal.appendChild( br );
			return;
		}
		const div = document.createElement( 'div' );
		console.log( message );
		div.innerHTML = marked.marked( message );
		terminal.appendChild( div );
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

	/**
	 * Function to send a prompt.
	 *
	 * @param {string} prompt The prompt to send.
	 * @param {int}	step   The current step in the wizard.
	 */
	function runPerfomanceWizardPrompt( prompt, step ) {
		// User= the REST API to send the prompt.
		const params = {
			'command': '_prompt_',
			'prompt' : prompt,
			'step'   : step
		};
		return wp.apiFetch( {
			path  : '/performance-wizard/v1/command/',
			method: 'POST',
			data  : params
		} );
	}
} )();



