
( function() {

	// The performance-wizard-terminal div will be used to display all communications with the agent.
	const terminal = document.getElementById( 'performance-wizard-terminal' );

	// Module-scoped transcript of the current run. Each entry is
	// { type: 'step'|'recommendations'|'qa', title, content } where content is
	// the raw Markdown string (not HTML) so it can be re-rendered later.
	let currentTranscript = [];

	/**
	 * Record a transcript entry for the current run.
	 *
	 * @param {string} type    One of 'step', 'recommendations', 'qa'.
	 * @param {string} title   A short heading for the entry.
	 * @param {string} content The raw Markdown content.
	 */
	function recordTranscript( type, title, content ) {
		currentTranscript.push( {
			type   : type,
			title  : String( title || '' ),
			content: String( content || '' )
		} );
	}

	// Handle layout toggle
	document.addEventListener( 'click', function( event ) {
		if ( event.target.classList.contains( 'layout-btn' ) ) {
			const layout = event.target.dataset.layout;
			const container = document.getElementById( 'wp-performance-wizard-container' );
			
			// Update button states
			document.querySelectorAll( '.layout-btn' ).forEach( btn => btn.classList.remove( 'active' ) );
			event.target.classList.add( 'active' );
			
			if ( layout === 'sidebar' ) {
				container.classList.add( 'sidebar-layout' );
			} else {
				container.classList.remove( 'sidebar-layout' );
			}
		}
	} );

	// Add event listener for model selection changes to save preference.
	const modelSelect = document.getElementById( 'performance-wizard-model' );
	if ( modelSelect && modelSelect.tagName === 'SELECT' ) {
		modelSelect.addEventListener( 'change', function() {
			saveModelPreference( this.value );
		} );
	}

	// Wait until the performance-wizard-start button has been click, then proceed with analysis.
	document.getElementById( 'performance-wizard-start' ).addEventListener( 'click', function( event ) {
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

		// Disable so a second click cannot stack another analysis run (and
		// another terminal click listener inside runAnalysis) on top of the
		// first.
		event.target.disabled = true;

		// Start a fresh transcript for this run.
		currentTranscript = [];

		// Run the analysis....
		runAnalysis( checkedDataSources );
	} );

	// Wait until the performance-wizard-new-conversation button has been
	// clicked, then reset the stored conversation and clear the terminal.
	const newConversationButton = document.getElementById( 'performance-wizard-new-conversation' );
	if ( newConversationButton ) {
		newConversationButton.addEventListener( 'click', function() {
			resetConversation();
		} );
	}

	/**
	 * Reset the conversation by clearing the stored prompts/responses on the
	 * server, then clearing the terminal and re-enabling the start button so
	 * a fresh analysis can be run.
	 */
	function resetConversation() {
		if ( ! wpPerformanceWizard || ! wpPerformanceWizard.resetNonce ) {
			console.error( 'Missing nonce for resetting conversation' );
			return;
		}

		const params = {
			'nonce': wpPerformanceWizard.resetNonce
		};

		wp.apiFetch( {
			path  : '/performance-wizard/v1/reset-conversation/',
			method: 'POST',
			data  : params
		} ).then( function( response ) {
			if ( response.success ) {
				// Clear the terminal output.
				terminal.innerHTML = '';

				// Remove any lingering follow-up question input.
				const followUp = document.getElementById( 'wp-performance-wizard-follow-up' );
				if ( followUp ) {
					followUp.remove();
				}

				// Re-enable the start button so a fresh analysis can be run.
				const startButton = document.getElementById( 'performance-wizard-start' );
				if ( startButton ) {
					startButton.disabled = false;
				}
			} else {
				console.error( 'Failed to reset conversation:', response.error );
			}
		} ).catch( function( error ) {
			console.error( 'Error resetting conversation:', error );
		} );
	}

	// Load the history panel on page load.
	loadHistory();

	/**
	 * Get the selected AI model from the form.
	 */
	function getSelectedModel() {
		const modelElement = document.getElementById( 'performance-wizard-model' );
		return modelElement ? modelElement.value : '';
	}

	/**
	 * Save the user's AI model preference via AJAX.
	 *
	 * @param {string} model The selected AI model name.
	 */
	function saveModelPreference( model ) {
		if ( ! model || ! wpPerformanceWizard || ! wpPerformanceWizard.nonce ) {
			console.error( 'Missing model or nonce for saving preference' );
			return;
		}

		const params = {
			'model': model,
			'nonce': wpPerformanceWizard.nonce
		};

		wp.apiFetch( {
			path  : '/performance-wizard/v1/save-model-preference/',
			method: 'POST',
			data  : params
		} ).then( function( response ) {
			if ( response.success ) {
				console.log( 'Model preference saved successfully:', model );
			} else {
				console.error( 'Failed to save model preference:', response.error );
			}
		} ).catch( function( error ) {
			console.error( 'Error saving model preference:', error );
		} );
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

		echoToTerminal( `## <g>Running analysis with ${selectedModel}...</g>` );
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
				echoToTerminal( '<div class="info-chip user-chip" aria-label="User">USER</div><br>' );
				echoToTerminal( '<div class="k"><br>' + question + '</div>' );
				// Remove the question field and buttons.
				document.getElementById( 'wp-performance-wizard-follow-up' ).remove();

				// Remove all the buttons with the class "wp-wizard-follow-up-question"
				const buttonsToRemove = document.querySelectorAll( '.wp-wizard-follow-up-question' );
				buttonsToRemove.forEach( button => {
					button.remove();
				} );

				// Show spinner while processing
				showSpinner( 'Processing your question...' );

				let results;
				try {
					// Ask the model for additional questions.
					// Send the question to the server.
					const selectedModel = getSelectedModel();
					results = await runPerfomanceWizardPrompt( question, step, true, selectedModel );
				} finally {
					hideSpinner();
				}

				echoToTerminal( '<br><div class="info-chip agent-chip" aria-label="Agent">AGENT</div><br>' );

				// Use streaming effect for AI responses, routed through the
				// same Markdown renderer used for non-streamed replies so the
				// output looks identical regardless of length.
				const responseDiv = document.createElement( 'div' );
				responseDiv.className = 'dc';
				terminal.appendChild( responseDiv );
				await streamText( responseDiv, results || '', 25 );

				// Record the follow-up question and answer as raw Markdown.
				recordTranscript( 'qa', 'Q: ' + question, 'Q: ' + question + '\n\nA: ' + ( results || '' ) );

				addFollowUpQuestion();
			}
		} );

		function addFollowUpQuestion() {
			echoToTerminal( '<div id="wp-performance-wizard-follow-up"><div class="info-chip user-chip" aria-label="User">USER</div><br><input type="text" id="performance-wizard-question" placeholder="Ask a question..."><button id="performance-wizard-ask">Ask</button></div>' );
		}

		async function outputFormattedResults( result ) {
			// If the results start with "Q: ", then it is a question. Remove that part and format as a question.
			if ( result.startsWith( '>Q: ' ) ) {
				echoToTerminal( '<div class="info-chip user-chip" aria-label="User">USER</div><br><div class="k"><br>' + result.replace( '>Q: ', '' ) + '</div>' );
			}
			// Similarly, results starting with ">A: " are answers. Remove that part format as an answer.
			else if ( result.startsWith( '>A: ' ) ) {
				echoToTerminal( '<div class="info-chip agent-chip" aria-label="Agent">AGENT</div><br>' );

				// Use streaming effect for AI responses. Both streamed and
				// non-streamed branches go through the Markdown renderer so
				// formatting is consistent regardless of response length.
				const responseContent = result.replace( '>A: ', '' );
				if ( responseContent.length > 100 ) {
					const responseDiv = document.createElement( 'div' );
					responseDiv.className = 'dc';
					terminal.appendChild( responseDiv );
					await streamText( responseDiv, responseContent, 25 );
				} else {
					echoToTerminal( '<div class="dc"><br>' + responseContent + '</div>' );
				}
			}
		}

		while ( ! complete && step <= maxSteps ) {
			const nextStep = await getPerfomanceWizardNextStep( step, selectedModel );
			let results;
			// If the next step isn't in the checked data sources, skip it.
			if ( ! dataSources.includes( nextStep.title ) ) {
				step++;
				continue;
			}
			const promptForDisplay = nextStep.display_prompt ? nextStep.display_prompt : nextStep.user_prompt;
			switch ( nextStep.action ) {
				case 'complete':
					// Archive the completed run as a non-destructive snapshot.
					// The transcript is captured client-side because the final
					// recommendations and follow-up Q&A are not persisted
					// server-side.
					archiveSession( selectedModel, dataSources, currentTranscript );

					// Output the input field so users can ask follow up questions.
					addFollowUpQuestion();

					complete = true;
					break;
				case 'run_action':
					echoToTerminal( '<br><div class="info-chip step-chip">Collecting Data</div><br>' );
					echoStep( promptForDisplay, nextStep.title );
					
					// Add status updates based on the step title
					if ( nextStep.title.includes( 'Lighthouse' ) ) {
						showStatusUpdate( 'Running Lighthouse analysis - this may take 30-45 seconds as we analyze your site\'s performance metrics...' );
					} else if ( nextStep.title.includes( 'Theme' ) || nextStep.title.includes( 'Plugin' ) ) {
						showStatusUpdate( 'Gathering plugin and theme data from WordPress.org API - this may take 10-15 seconds...' );
					} else if ( nextStep.title.includes( 'Script' ) ) {
						showStatusUpdate( 'Analyzing JavaScript performance and attribution data...' );
					} else if ( nextStep.title.includes( 'HTML' ) ) {
						showStatusUpdate( 'Fetching and analyzing your site\'s HTML structure...' );
					}
					
					// Show spinner during data collection
					showSpinner( 'Collecting ' + nextStep.title + ' data...' );

					try {
						results = await runPerformanceWizardNextStep( step, selectedModel );
					} finally {
						hideSpinner();
					}

					echoToTerminal( '### <div class="dc">Analysis...</div>' );
					// Iterate thru all of the results returned in the response
					const stepResults = [];
					for( const resultIndex in results ) {
						const result = results[ resultIndex ];
						stepResults.push( String( result || '' ) );
						echoToTerminal();
						await outputFormattedResults( result );
					};

					// Record the concatenated raw Markdown for this data-source step.
					recordTranscript( 'step', nextStep.title, stepResults.join( '\n\n' ) );

					step++;
					break;
				case 'prompt':
					step++
					echoToTerminal( '<br><div class="info-chip user-chip" aria-label="User">USER</div><br>' );
					echoToTerminal( '<div class="k"><br>' + promptForDisplay  + '</div>' );

					// Show spinner while AI processes the prompt
					showSpinner( 'AI is analyzing and generating response...' );

					let result;
					try {
						result = await runPerfomanceWizardPrompt( nextStep.user_prompt, step, false, selectedModel );
					} finally {
						hideSpinner();
					}

					echoToTerminal( '<br><div class="info-chip agent-chip" aria-label="Agent">AGENT</div><br>' );

					// Record the final recommendations response as raw Markdown.
					recordTranscript( 'recommendations', nextStep.title || 'Recommendations', result || '' );

					// Use streaming effect for longer AI responses. Both
					// branches go through the Markdown renderer so formatting
					// is consistent regardless of response length.
					if ( result && result.length > 100 ) {
						const responseDiv = document.createElement( 'div' );
						responseDiv.className = 'dc';
						terminal.appendChild( responseDiv );
						await streamText( responseDiv, result, 20 );
					} else {
						echoToTerminal( '<div class="dc">' + ( result || '' ) + '</div>' );
					}

					break;
				case 'continue':
					step++;
					break;
			}
		}
	}

	/**
	 * Show a loading spinner with optional message
	 */
	function showSpinner( message = 'Processing...' ) {
		const spinnerId = 'wp-performance-wizard-spinner';
		// Remove existing spinner if present
		const existingSpinner = document.getElementById( spinnerId );
		if ( existingSpinner ) {
			existingSpinner.remove();
		}
		
		const spinnerDiv = document.createElement( 'div' );
		spinnerDiv.id = spinnerId;
		spinnerDiv.innerHTML = '<div class="loading-spinner">⟲</div> ' + message;
		terminal.appendChild( spinnerDiv );
		
		return spinnerId;
	}

	/**
	 * Hide the loading spinner
	 */
	function hideSpinner() {
		const spinner = document.getElementById( 'wp-performance-wizard-spinner' );
		if ( spinner ) {
			spinner.remove();
		}
	}

	/**
	 * Show a status update to the user
	 */
	function showStatusUpdate( message ) {
		const statusDiv = document.createElement( 'div' );
		statusDiv.className = 'status-update';
		statusDiv.innerHTML = '💡 ' + message;
		terminal.appendChild( statusDiv );
	}

	/**
	 * Simulate a streaming text effect while keeping Markdown rendering intact.
	 *
	 * The accumulated substring is passed through the same Markdown renderer
	 * used for non-streamed replies on each tick, so formatting (headings,
	 * lists, links, code) matches the non-streamed path exactly.
	 *
	 * @param {HTMLElement} element  Target element to render into.
	 * @param {string}      markdown Raw Markdown source to stream.
	 * @param {number}      speed    Milliseconds between characters.
	 */
	function streamText( element, markdown, speed = 30 ) {
		return new Promise( ( resolve ) => {
			const source = String( markdown || '' );
			const chunk  = 5; // Re-render every N chars to avoid O(n^2) full Markdown parses on long responses.
			let index    = 0;
			element.innerHTML = '';

			const typeChar = () => {
				if ( index < source.length ) {
					index = Math.min( index + chunk, source.length );
					element.innerHTML = marked.marked( source.substring( 0, index ) );
					setTimeout( typeChar, speed * chunk );
				} else {
					element.innerHTML = marked.marked( source );
					resolve();
				}
			};

			typeChar();
		} );
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

	/**
	 * Archive the completed run, then refresh the history panel.
	 *
	 * @param {string} model       The selected AI model.
	 * @param {Array}  dataSources  The data source names used for the run.
	 * @param {Array}  transcript   The captured transcript entries.
	 */
	function archiveSession( model, dataSources, transcript ) {
		if ( ! transcript || transcript.length === 0 ) {
			return;
		}
		wp.apiFetch( {
			path  : '/performance-wizard/v1/command/',
			method: 'POST',
			data  : {
				'command'     : '_archive_session_',
				'model'       : model,
				'data_sources': dataSources,
				'transcript'  : transcript
			}
		} ).then( function() {
			loadHistory();
		} ).catch( function( error ) {
			console.error( 'Error archiving analysis session:', error );
		} );
	}

	/**
	 * Load the list of stored sessions and render the history panel.
	 */
	function loadHistory() {
		const container = document.getElementById( 'performance-wizard-history' );
		if ( ! container ) {
			return;
		}
		wp.apiFetch( {
			path  : '/performance-wizard/v1/command/',
			method: 'POST',
			data  : { 'command': '_get_sessions_' }
		} ).then( function( sessions ) {
			renderHistoryList( container, sessions || [] );
		} ).catch( function( error ) {
			console.error( 'Error loading analysis history:', error );
		} );
	}

	/**
	 * Render the history list into the given container.
	 *
	 * @param {HTMLElement} container The container element.
	 * @param {Array}       sessions  The list of session summaries.
	 */
	function renderHistoryList( container, sessions ) {
		container.innerHTML = '';

		if ( ! sessions.length ) {
			const empty = document.createElement( 'p' );
			empty.textContent = 'No past analyses yet.';
			container.appendChild( empty );
			return;
		}

		const list = document.createElement( 'ul' );
		list.className = 'performance-wizard-history-list';

		sessions.forEach( function( session ) {
			const item = document.createElement( 'li' );

			const label = document.createElement( 'span' );
			const sourceCount = Array.isArray( session.data_sources ) ? session.data_sources.length : 0;
			label.textContent = session.created + ' — ' + ( session.model || 'Unknown model' ) +
				' — ' + sourceCount + ' data source(s)';

			const view = document.createElement( 'button' );
			view.type = 'button';
			view.className = 'button performance-wizard-history-view';
			view.textContent = 'View';
			view.setAttribute( 'data-session-id', session.id );

			item.appendChild( label );
			item.appendChild( document.createTextNode( ' ' ) );
			item.appendChild( view );
			list.appendChild( item );
		} );

		container.appendChild( list );
	}

	// Delegated handler for "View" buttons in the history panel.
	document.addEventListener( 'click', function( event ) {
		if ( ! event.target.classList.contains( 'performance-wizard-history-view' ) ) {
			return;
		}
		const sessionId = event.target.getAttribute( 'data-session-id' );
		if ( ! sessionId ) {
			return;
		}
		wp.apiFetch( {
			path  : '/performance-wizard/v1/command/',
			method: 'POST',
			data  : {
				'command'   : '_get_session_',
				'session_id': sessionId
			}
		} ).then( function( session ) {
			if ( ! session || session.error ) {
				console.error( 'Error loading session:', session && session.error );
				return;
			}
			renderStoredTranscript( session );
		} ).catch( function( error ) {
			console.error( 'Error loading session:', error );
		} );
	} );

	/**
	 * Render a stored session transcript read-only into the terminal.
	 *
	 * Stored content is Markdown and is rendered through the same marked()
	 * renderer used by the live terminal (same trust model). Raw HTML is never
	 * persisted or re-injected.
	 *
	 * @param {Object} session The full stored session record.
	 */
	function renderStoredTranscript( session ) {
		if ( ! terminal ) {
			return;
		}
		// Clear the terminal for the read-only view.
		terminal.innerHTML = '';

		const header = document.createElement( 'div' );
		header.innerHTML = marked.marked(
			'## Viewing past analysis (' + ( session.created || '' ) + ')\n\n' +
			'_Model: ' + ( session.model || 'Unknown' ) + ' — read-only_'
		);
		terminal.appendChild( header );

		const transcript = Array.isArray( session.transcript ) ? session.transcript : [];
		transcript.forEach( function( entry ) {
			if ( entry.title ) {
				const heading = document.createElement( 'div' );
				heading.innerHTML = marked.marked( '### ' + String( entry.title ) );
				terminal.appendChild( heading );
			}
			const body = document.createElement( 'div' );
			body.className = 'dc';
			body.innerHTML = marked.marked( String( entry.content || '' ) );
			terminal.appendChild( body );
		} );
	}
} )();
