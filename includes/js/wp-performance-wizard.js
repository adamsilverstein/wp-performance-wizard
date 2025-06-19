
( function() {

	// The performance-wizard-terminal div will be used to display all communications with the agent.
	const terminal = document.getElementById( 'performance-wizard-terminal' );

	console.log( 'Ready' );

	// Wait until the performance-wizard-start button has been click, then proceed with analysis.
	document.getElementById( 'performance-wizard-start' ).addEventListener( 'click', function() {
		// @todo strings should be localized.
		// Collect the checkbox data from performance-wizard-data-source class checkboxes.
		const dataSources = document.querySelectorAll( '.performance-wizard-data-source' );

		// Extract the values of the checkboxes that are checked.
		const checkedDataSources = [];
		dataSources.forEach( dataSource => {
			if ( dataSource.checked ) {
				checkedDataSources.push( dataSource.value );
			}
		} );

		// Run the analysis....
		runAnalysis( checkedDataSources );
	} );

	/**
	 * Get the selected AI model from the form.
	 */
	function getSelectedModel() {
		const modelElement = document.getElementById( 'performance-wizard-model' );
		return modelElement ? modelElement.value : '';
	}

	/**
	 * Run the analysis, interacting with the passed terminal.
	 */
	async function runAnalysis( dataSources ) {
		// @todo strings should be localized.
		const selectedModel = getSelectedModel();
		if ( ! selectedModel ) {
			echoToTerminal( '<r>Error: No AI model selected. Please configure an AI model first.</r>' );
			return;
		}

		echoToTerminal( '## <g>Running analysis with ' + selectedModel + '...</g>' );
		// Get a description of the next step. Continue until the final step.
		let complete = false;
		const maxSteps = 25;
		let step = 0;
		// Listen for clicks on the wp-wizard-follow-up-question buttons using event delegation.
		document.getElementById( 'performance-wizard-terminal' ).addEventListener( 'click', async function( event ) {
			if ( 'wp-wizard-follow-up-question' === event.target.className ) {
				// Extract the question from the content of the button, add it to the input field, and click the ask button.
				const question = event.target.innerHTML;
				document.getElementById( 'performance-wizard-question' ).value = question;
				document.getElementById( 'performance-wizard-ask' ).click();
			}
			if ( 'performance-wizard-ask' === event.target.id ) {
				const question = document.getElementById( 'performance-wizard-question' ).value;
				echoToTerminal( '<div class="info-chip user-chip">USER</div><br>' );
				echoToTerminal( '<div class="k"><br>' + question + '</div>' );
				// Remove the question field and buttons.
				document.getElementById( 'wp-performance-wizard-follow-up' ).remove();

				// Remove all the buttons with the class "wp-wizard-follow-up-question"
				const buttonsToRemove = document.querySelectorAll( '.wp-wizard-follow-up-question' );
				buttonsToRemove.forEach( button => {
					button.remove();
				} );

				// Ask the model for additional questions.
				// Send the question to the server.
				const selectedModel = getSelectedModel();
				results = await runPerfomanceWizardPrompt( question, step, true, selectedModel );
				echoToTerminal( '<br><div class="info-chip agent-chip">AGENT</div><br>')
				echoToTerminal( '<br><div class="dc">' + results + ' </div>' );
				addFollowUpQuestion();
			}
		} );

		function addFollowUpQuestion() {
			echoToTerminal( '<div id="wp-performance-wizard-follow-up"><div class="info-chip user-chip">USER</div><br><input type="text" id="performance-wizard-question" placeholder="Ask a question..."><button id="performance-wizard-ask">Ask</button></div>' );
		}

		function outputFormattedResults( result ) {
			// If the results start with "Q: ", then it is a question. Remove that part and format as a question.
			if ( result.startsWith( '>Q: ' ) ) {
				echoToTerminal( '<div class="info-chip user-chip">USER</div><br><div class="k"><br>' + result.replace( '>Q: ', '' ) + '</div>' );
			}
			// Similarly, results starting with ">A: " are answers. Remove that part format as an answer.
			else if ( result.startsWith( '>A: ' ) ) {
				echoToTerminal( '<div class="info-chip agent-chip">AGENT</div><br><div class="dc"><br>' + result.replace( '>A: ', '' ) + '</div>' );
			}
		}

		while ( ! complete && step <= maxSteps ) {
			const nextStep = await getPerfomanceWizardNextStep( step, selectedModel );
			console.log( nextStep );
			console.log( dataSources );
			// If the next step isn't in the checked data sources, skip it.
			if ( ! dataSources.includes( nextStep.title ) ) {
				step++;
				continue;
			}
			const promptForDisplay = nextStep.display_prompt ? nextStep.display_prompt : nextStep.user_prompt;
			switch ( nextStep.action ) {
				case 'complete':
					// Output the input field so users can ask follow up questions.
					addFollowUpQuestion();

					complete = true;
					break;
				case 'run_action':
					echoToTerminal( '<br><div class="info-chip step-chip">Collecting Data</div><br>' );
					echoStep( promptForDisplay, nextStep.title );
					results = await runPerformanceWizardNextStep( step, selectedModel );
					echoToTerminal( '### <div class="dc">Analysis...</div>' );
					// Iterate thru all of the results returned in the response
					for( const resultIndex in results ) {
						const result = results[ resultIndex ];
						echoToTerminal();
						outputFormattedResults( result );
					};

					step++;
					break;
				case 'prompt':
					step++
					echoToTerminal( '<br><div class="info-chip user-chip">USER</div><br>' );
					echoToTerminal( '<div class="k"><br>' + promptForDisplay  + '</div>' );
					result = await runPerfomanceWizardPrompt( nextStep.user_prompt, step, false, selectedModel );
					echoToTerminal();
					echoToTerminal( result );

					break;
				case 'continue':
					step++;
					break;
			}
		}
	}

	/**
	 * Echo out the next step, or an error if there is one.
	 */
	function echoStep( nextStep, title = 'Next step') {
		if ( 'error' === nextStep || 'undefined' === nextStep ) {
			echoToTerminal( '<r>Error: Unable to get the next step. Please try again.</r>' );
		} else {
			echoToTerminal( '<div class="k"><h3>' + title + '</h3> ' + nextStep + '</div>' );
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
	 * @param {int}    step  The current step in the wizard.
	 * @param {string} model The selected AI model.
	 */
	function getPerfomanceWizardNextStep( step, model = '' ) {
		// User= the REST API to get the next step.
		const params = {
			'command': '_get_next_action_',
			'step'   : step,
			'model'  : model
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
	 * @param {int}    step  The current step in the wizard.
	 * @param {string} model The selected AI model.
	 */
	function runPerformanceWizardNextStep( step, model = '' ) {
		// User= the REST API to run the next step.
		const params = {
			'command': '_run_action_',
			'step'   : step,
			'model'  : model
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
	 * @param {string} prompt               The prompt to send.
	 * @param {int}	   step                 The current step in the wizard.
	 * @param {bool}   additional_questions Whether or not to ask additional questions.
	 * @param {string} model                The selected AI model.
	 */
	function runPerfomanceWizardPrompt( prompt, step, additional_questions = false, model = '' ) {
		// User= the REST API to send the prompt.
		const params = {
			'command'             : '_prompt_',
			'prompt'              : prompt,
			'step'                : step,
			'additional_questions': additional_questions,
			'model'               : model
		};
		return wp.apiFetch( {
			path  : '/performance-wizard/v1/command/',
			method: 'POST',
			data  : params
		} );
	}
} )();
