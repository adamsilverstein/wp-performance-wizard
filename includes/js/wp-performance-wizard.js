
( function() {

	// Pull i18n helpers if available. Fall back to identity when running in a
	// context where wp.i18n is not loaded (e.g. tests or older WP).
	const __ = ( window.wp && window.wp.i18n && window.wp.i18n.__ )
		? window.wp.i18n.__
		: function( s ) { return s; };
	const sprintf = ( window.wp && window.wp.i18n && window.wp.i18n.sprintf )
		? window.wp.i18n.sprintf
		: function( s ) {
			const args = Array.prototype.slice.call( arguments, 1 );
			let i = 0;
			return String( s ).replace( /%s/g, function() { return args[ i++ ]; } );
		};

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
					const responseDiv = document.createElement( 'div' );
					responseDiv.className = 'dc';
					responseDiv.innerHTML = renderTerminalMarkdown( responseContent );
					terminal.appendChild( responseDiv );
				}
			}
		}

		while ( ! complete && step <= maxSteps ) {
			let nextStep;
			try {
				nextStep = await getPerfomanceWizardNextStep( step, selectedModel );
			} catch ( error ) {
				const data = error && ( error.data || error );
				const message = ( data && data.error ) ? data.error : ( error && error.message ) || __( 'Request failed.', 'wp-performance-wizard' );
				const link = ( data && data.connectors_url ) ? ' <a href="' + data.connectors_url + '">' + __( 'Open Connectors', 'wp-performance-wizard' ) + '</a>' : '';
				echoToTerminal( '<r>' + message + '</r>' + link );
				return;
			}
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

					// Offer export controls for the freshly completed run. The
					// session is read lazily so any follow-up Q&A added after
					// completion is included in the export.
					renderExportControls( terminal, function() {
						return {
							model       : selectedModel,
							data_sources: dataSources,
							transcript  : currentTranscript
						};
					} );

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
						const responseDiv = document.createElement( 'div' );
						responseDiv.className = 'dc';
						responseDiv.innerHTML = renderTerminalMarkdown( result || '' );
						terminal.appendChild( responseDiv );
					}

					// If the response includes a structured recommendations
					// block, render it as a checklist below the prose.
					renderRecommendationsChecklist( terminal, result || '' );

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
	 * The animation is capped at ~500ms total regardless of response length, so
	 * long responses still feel snappy. The accumulated substring is passed
	 * through the same Markdown renderer used for non-streamed replies on each
	 * tick, so formatting (headings, lists, links, code) matches the
	 * non-streamed path exactly.
	 *
	 * @param {HTMLElement} element  Target element to render into.
	 * @param {string}      markdown Raw Markdown source to stream.
	 * @param {number}      speed    (Unused, kept for backward compatibility.)
	 */
	function streamText( element, markdown, speed = 0 ) { // eslint-disable-line no-unused-vars
		return new Promise( ( resolve ) => {
			const source = String( markdown || '' );
			if ( source.length === 0 ) {
				element.innerHTML = '';
				resolve();
				return;
			}

			const totalDurationMs = 500;
			const tickIntervalMs  = 25;
			const ticks           = Math.max( 1, Math.round( totalDurationMs / tickIntervalMs ) );
			const chunkSize       = Math.max( 1, Math.ceil( source.length / ticks ) );
			let index             = 0;
			element.innerHTML     = '';

			const typeChar = () => {
				if ( index < source.length ) {
					index = Math.min( index + chunkSize, source.length );
					element.innerHTML = renderTerminalMarkdown( source.substring( 0, index ) );
					setTimeout( typeChar, tickIntervalMs );
				} else {
					element.innerHTML = renderTerminalMarkdown( source );
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
		header.innerHTML = renderTerminalMarkdown(
			'## Viewing past analysis (' + ( session.created || '' ) + ')\n\n' +
			'_Model: ' + ( session.model || 'Unknown' ) + ' — read-only_'
		);
		terminal.appendChild( header );

		// Offer export controls for the loaded historical session.
		renderExportControls( terminal, function() {
			return session;
		} );

		const transcript = Array.isArray( session.transcript ) ? session.transcript : [];
		transcript.forEach( function( entry ) {
			if ( entry.title ) {
				const heading = document.createElement( 'div' );
				heading.innerHTML = renderTerminalMarkdown( '### ' + String( entry.title ) );
				terminal.appendChild( heading );
			}
			const body = document.createElement( 'div' );
			body.className = 'dc';
			body.innerHTML = renderTerminalMarkdown( String( entry.content || '' ) );
			terminal.appendChild( body );

			// If this entry is the final recommendations block and embeds a
			// structured JSON list, render a checklist below it.
			if ( 'recommendations' === entry.type ) {
				renderRecommendationsChecklist( terminal, String( entry.content || '' ), { readOnly: true } );
			}
		} );
	}

	/**
	 * Render an export-controls toolbar into the given container.
	 *
	 * Each button reads the latest session via the supplied getter so a live
	 * run's transcript is captured at click time, not at render time.
	 *
	 * @param {HTMLElement} container     The element to append the toolbar to.
	 * @param {Function}    sessionGetter A function returning the session-shaped object to export.
	 */
	function renderExportControls( container, sessionGetter ) {
		if ( ! container || 'function' !== typeof sessionGetter ) {
			return;
		}

		const bar = document.createElement( 'div' );
		bar.className = 'performance-wizard-export-bar';

		const label = document.createElement( 'span' );
		label.className = 'performance-wizard-export-label';
		label.textContent = __( 'Export report:', 'wp-performance-wizard' ) + ' ';
		bar.appendChild( label );

		const formats = [
			{ key: 'md',   label: __( 'Markdown', 'wp-performance-wizard' ) },
			{ key: 'html', label: __( 'HTML', 'wp-performance-wizard' ) }
		];

		formats.forEach( function( format ) {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'button performance-wizard-export-button';
			btn.textContent = format.label;
			btn.addEventListener( 'click', function() {
				try {
					exportSession( format.key, sessionGetter() || {} );
				} catch ( e ) {
					console.error( 'Performance Wizard export failed:', e );
				}
			} );
			bar.appendChild( btn );
		} );

		container.appendChild( bar );
	}

	/**
	 * Build and trigger a download of the report in the requested format.
	 *
	 * @param {string} format      Either 'md' or 'html'.
	 * @param {Object} sessionData An object with model, data_sources, transcript, and optional created/timestamp.
	 */
	function exportSession( format, sessionData ) {
		const transcript = Array.isArray( sessionData.transcript ) ? sessionData.transcript : [];
		if ( ! transcript.length ) {
			console.warn( 'Performance Wizard export: nothing to export.' );
			return;
		}

		const markdown = buildExportMarkdown( sessionData, transcript );
		const filename = buildExportFilename( sessionData, format );

		if ( 'md' === format ) {
			downloadBlob( filename, 'text/markdown;charset=utf-8', markdown );
			return;
		}

		const html = buildExportHtml( sessionData, markdown );
		downloadBlob( filename, 'text/html;charset=utf-8', html );
	}

	/**
	 * Build the report body as a Markdown string.
	 *
	 * @param {Object} sessionData The session metadata.
	 * @param {Array}  transcript  The transcript entries.
	 * @returns {string} Markdown source.
	 */
	function buildExportMarkdown( sessionData, transcript ) {
		const siteUrl  = ( 'undefined' !== typeof wpPerformanceWizard && wpPerformanceWizard.siteUrl ) || '';
		const siteName = ( 'undefined' !== typeof wpPerformanceWizard && wpPerformanceWizard.siteName ) || '';
		const created  = sessionData.created || new Date().toISOString();
		const model    = sessionData.model || __( 'Unknown', 'wp-performance-wizard' );
		const sources  = Array.isArray( sessionData.data_sources ) ? sessionData.data_sources : [];

		const lines = [];
		lines.push( '# ' + __( 'Performance Wizard Report', 'wp-performance-wizard' ) );
		lines.push( '' );
		if ( siteName ) {
			lines.push( '- **' + __( 'Site:', 'wp-performance-wizard' ) + '** ' + siteName + ( siteUrl ? ' (' + siteUrl + ')' : '' ) );
		} else if ( siteUrl ) {
			lines.push( '- **' + __( 'Site:', 'wp-performance-wizard' ) + '** ' + siteUrl );
		}
		lines.push( '- **' + __( 'Date:', 'wp-performance-wizard' ) + '** ' + created );
		lines.push( '- **' + __( 'Model:', 'wp-performance-wizard' ) + '** ' + model );
		if ( sources.length ) {
			lines.push( '- **' + __( 'Data sources:', 'wp-performance-wizard' ) + '** ' + sources.join( ', ' ) );
		}
		lines.push( '' );
		lines.push( '---' );
		lines.push( '' );

		transcript.forEach( function( entry ) {
			const title = String( entry.title || '' ).trim();
			if ( title ) {
				lines.push( '## ' + title );
				lines.push( '' );
			}
			lines.push( String( entry.content || '' ).trim() );
			lines.push( '' );
		} );

		return lines.join( '\n' );
	}

	/**
	 * Build a self-contained HTML document for the report.
	 *
	 * The Markdown is rendered through the same marked() pipeline used by the
	 * live terminal so styling is consistent. A small print-friendly stylesheet
	 * is inlined so users can also use the browser's "Print to PDF" path.
	 *
	 * @param {Object} sessionData The session metadata.
	 * @param {string} markdown    The report body as Markdown.
	 * @returns {string} A complete HTML document.
	 */
	function buildExportHtml( sessionData, markdown ) {
		const siteName  = ( 'undefined' !== typeof wpPerformanceWizard && wpPerformanceWizard.siteName ) || '';
		const titleBits = [ __( 'Performance Wizard Report', 'wp-performance-wizard' ) ];
		if ( siteName ) {
			titleBits.push( siteName );
		}
		const docTitle = titleBits.join( ' - ' );

		// Render to HTML with raw HTML pass-through disabled and javascript:
		// links neutralised. The exported file may be shared, so AI-generated
		// content cannot be trusted to be free of script payloads.
		const body = renderSafeMarkdown( markdown );

		const styles = [
			'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;max-width:780px;margin:2em auto;padding:0 1em;color:#1d2327;line-height:1.55;}',
			'h1{font-size:1.9em;margin-top:0;}',
			'h2{border-bottom:1px solid #ddd;padding-bottom:.3em;margin-top:1.8em;}',
			'h3{margin-top:1.4em;}',
			'pre,code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}',
			'pre{background:#f6f7f8;padding:1em;overflow:auto;border-radius:4px;}',
			'code{background:#f6f7f8;padding:.1em .3em;border-radius:3px;}',
			'pre code{background:transparent;padding:0;}',
			'hr{border:none;border-top:1px solid #ddd;margin:1.5em 0;}',
			'button.wp-wizard-follow-up-question{display:none;}',
			'@media print{body{margin:0;}h2,h3{break-after:avoid;}pre{white-space:pre-wrap;word-break:break-word;}}'
		].join( '' );

		return [
			'<!DOCTYPE html>',
			'<html lang="en">',
			'<head>',
			'<meta charset="utf-8">',
			'<meta name="viewport" content="width=device-width,initial-scale=1">',
			'<title>' + escapeHtmlAttr( docTitle ) + '</title>',
			'<style>' + styles + '</style>',
			'</head>',
			'<body>',
			body,
			'</body>',
			'</html>'
		].join( '\n' );
	}

	/**
	 * Build a filesystem-safe filename for the export based on date and format.
	 *
	 * @param {Object} sessionData The session metadata.
	 * @param {string} format      Either 'md' or 'html'.
	 * @returns {string} The filename including extension.
	 */
	function buildExportFilename( sessionData, format ) {
		let stamp = '';
		if ( sessionData.created ) {
			stamp = String( sessionData.created );
		} else {
			const now = new Date();
			stamp = now.getFullYear() + '-' +
				pad2( now.getMonth() + 1 ) + '-' +
				pad2( now.getDate() ) + '_' +
				pad2( now.getHours() ) + '-' +
				pad2( now.getMinutes() );
		}
		// Reduce to filesystem-safe characters.
		stamp = stamp.replace( /[^0-9A-Za-z]+/g, '-' ).replace( /^-+|-+$/g, '' );
		const ext = ( 'md' === format ) ? 'md' : 'html';
		return 'wp-performance-wizard-report-' + ( stamp || 'report' ) + '.' + ext;
	}

	function pad2( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	/**
	 * Trigger a browser download for the given content.
	 *
	 * @param {string} filename The download filename.
	 * @param {string} mime     The MIME type (with optional charset).
	 * @param {string} content  The file contents.
	 */
	function downloadBlob( filename, mime, content ) {
		const blob = new Blob( [ content ], { type: mime } );
		const url  = URL.createObjectURL( blob );
		const a    = document.createElement( 'a' );
		a.href     = url;
		a.download = filename;
		a.rel      = 'noopener';
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		// Revoke the URL on the next tick so Safari has time to start the download.
		setTimeout( function() { URL.revokeObjectURL( url ); }, 1000 );
	}

	/**
	 * Escape a string for safe interpolation into an HTML attribute or title.
	 *
	 * @param {string} value The input string.
	 * @returns {string} The escaped string.
	 */
	function escapeHtmlAttr( value ) {
		return String( value ).replace( /[&<>"']/g, function( c ) {
			switch ( c ) {
				case '&': return '&amp;';
				case '<': return '&lt;';
				case '>': return '&gt;';
				case '"': return '&quot;';
				case "'": return '&#39;';
			}
			return c;
		} );
	}

	// Render Markdown to HTML for an exported, potentially-shared document.
	// Drops raw HTML pass-through and unsafe link/image protocols so
	// AI-generated content cannot inject scripts when the file is opened.
	function renderSafeMarkdown( markdown ) {
		const renderer = new marked.Renderer();
		renderer.html = function() { return ''; };

		return marked.marked( String( markdown || '' ), {
			renderer: renderer,
			walkTokens: function( token ) {
				if ( ( 'link' === token.type || 'image' === token.type ) && ! isSafeUrl( token.href ) ) {
					token.href = '';
				}
			}
		} );
	}

	function isSafeUrl( href ) {
		const trimmed = String( href || '' ).trim().toLowerCase();
		if ( ! trimmed ) {
			return false;
		}
		return ! /^(javascript|data|vbscript|file):/i.test( trimmed );
	}

	/**
	 * Render Markdown for display in the live terminal and history view.
	 *
	 * AI responses (and the analyzed site content that informs them) are
	 * untrusted: a prompt-injection payload, a malicious plugin description, or
	 * the model itself could emit raw HTML/script. The marked() default passes
	 * raw HTML straight through, so rendering model output with it would execute
	 * that markup in the admin's authenticated session. This routes everything
	 * through renderSafeMarkdown() (raw HTML dropped, unsafe link/image
	 * protocols neutralised), then re-adds only the one HTML feature the wizard
	 * relies on - the follow-up question buttons - as a narrow, sanitized
	 * allow-list with their labels escaped as plain text.
	 *
	 * @param {string} markdown Raw Markdown (potentially containing model HTML).
	 * @returns {string} Safe HTML.
	 */
	function renderTerminalMarkdown( markdown ) {
		const source = String( markdown || '' );

		// Extract follow-up question button labels before raw HTML is stripped.
		const followUps = [];
		const buttonRe = /<button\b[^>]*\bwp-wizard-follow-up-question\b[^>]*>([\s\S]*?)<\/button>/gi;
		let match;
		while ( null !== ( match = buttonRe.exec( source ) ) ) {
			const label = String( match[1] || '' ).replace( /<[^>]*>/g, '' ).trim();
			if ( label ) {
				followUps.push( label );
			}
		}

		let html = renderSafeMarkdown( source );

		// Re-add the follow-up questions as safe, allow-listed buttons with the
		// label escaped so it can only ever render as text.
		followUps.forEach( function( label ) {
			html += '<button class="wp-wizard-follow-up-question">' + escapeHtmlAttr( label ) + '</button>';
		} );

		return html;
	}

	/**
	 * The localStorage key used to persist completed recommendation state.
	 *
	 * The shape is { "<site-url>": { "<lowercased trimmed title>": true } } so
	 * different WP installs viewed from the same browser do not collide.
	 *
	 * @constant {string}
	 */
	const CHECKLIST_STORAGE_KEY = 'wp-performance-wizard-recommendations-checklist';

	/**
	 * Read the stored "done" state map for the current site.
	 *
	 * @returns {Object<string,boolean>} Title-keyed completion state.
	 */
	function readChecklistState() {
		try {
			const raw = window.localStorage.getItem( CHECKLIST_STORAGE_KEY );
			if ( ! raw ) {
				return {};
			}
			const parsed = JSON.parse( raw );
			const siteKey = checklistSiteKey();
			if ( parsed && 'object' === typeof parsed && parsed[ siteKey ] && 'object' === typeof parsed[ siteKey ] ) {
				return parsed[ siteKey ];
			}
		} catch ( e ) {
			// Storage may be unavailable (private mode, quota); start fresh.
		}
		return {};
	}

	/**
	 * Persist the "done" state map for the current site.
	 *
	 * @param {Object<string,boolean>} state Title-keyed completion state.
	 */
	function writeChecklistState( state ) {
		try {
			const raw = window.localStorage.getItem( CHECKLIST_STORAGE_KEY );
			let parsed = {};
			if ( raw ) {
				try {
					const candidate = JSON.parse( raw );
					if ( candidate && 'object' === typeof candidate ) {
						parsed = candidate;
					}
				} catch ( e ) {
					parsed = {};
				}
			}
			parsed[ checklistSiteKey() ] = state;
			window.localStorage.setItem( CHECKLIST_STORAGE_KEY, JSON.stringify( parsed ) );
		} catch ( e ) {
			// Storage write failed; tolerate silently.
		}
	}

	/**
	 * Stable per-site key for checklist state. Falls back to a sentinel when no
	 * site URL is available (older configurations or test environments).
	 *
	 * @returns {string} The site key.
	 */
	function checklistSiteKey() {
		const siteUrl = ( 'undefined' !== typeof wpPerformanceWizard && wpPerformanceWizard.siteUrl ) || '';
		return siteUrl || '__no_site_url__';
	}

	/**
	 * Normalize a recommendation title so the same recommendation matches across runs.
	 *
	 * @param {string} title The raw title.
	 * @returns {string} A normalized key.
	 */
	function normalizeChecklistTitle( title ) {
		return String( title || '' ).trim().toLowerCase();
	}

	/**
	 * Extract the structured recommendations from a Markdown response.
	 *
	 * Looks for a fenced JSON code block (```json ... ```) anywhere in the
	 * response and returns the `recommendations` array from it. Returns an
	 * empty array when the block is missing or unparsable.
	 *
	 * @param {string} markdown The raw response Markdown.
	 * @returns {Array<{title:string,rationale:string,audit:string,plugin:string}>}
	 */
	function extractRecommendationsJson( markdown ) {
		const source = String( markdown || '' );
		const match = source.match( /```\s*json\s*([\s\S]*?)```/i );
		if ( ! match ) {
			return [];
		}
		try {
			const parsed = JSON.parse( match[1] );
			if ( parsed && Array.isArray( parsed.recommendations ) ) {
				return parsed.recommendations.filter( function( item ) {
					return item && 'object' === typeof item && item.title;
				} );
			}
		} catch ( e ) {
			// JSON block was present but invalid; skip the checklist UI.
		}
		return [];
	}

	/**
	 * Render a checkable list of recommendations into the given container if
	 * the response includes a structured JSON block. No-op otherwise.
	 *
	 * @param {HTMLElement} container       The element to append the checklist to.
	 * @param {string}      responseMarkdown The full AI response Markdown.
	 * @param {Object}      [options]        Render options. Pass { readOnly: true }
	 *                                       from the history view so checkboxes
	 *                                       and the Re-test button are inert
	 *                                       (archived sessions cannot mutate
	 *                                       completion state or trigger live
	 *                                       follow-up questions).
	 */
	function renderRecommendationsChecklist( container, responseMarkdown, options ) {
		const items = extractRecommendationsJson( responseMarkdown );
		if ( ! container || 0 === items.length ) {
			return;
		}

		const readOnly = !! ( options && options.readOnly );

		const state = readChecklistState();

		const wrap = document.createElement( 'div' );
		wrap.className = 'performance-wizard-checklist';

		const heading = document.createElement( 'h3' );
		heading.className = 'performance-wizard-checklist-title';
		heading.textContent = __( 'Recommendations checklist', 'wp-performance-wizard' );
		wrap.appendChild( heading );

		const help = document.createElement( 'p' );
		help.className = 'performance-wizard-checklist-help';
		help.textContent = __( 'Track which recommendations you have applied. Completion state is saved locally in your browser for this site.', 'wp-performance-wizard' );
		wrap.appendChild( help );

		const list = document.createElement( 'ul' );
		list.className = 'performance-wizard-checklist-list';

		items.forEach( function( item ) {
			const key = normalizeChecklistTitle( item.title );
			const done = !! state[ key ];

			const li = document.createElement( 'li' );
			li.className = 'performance-wizard-checklist-item' + ( done ? ' is-done' : '' );

			const label = document.createElement( 'label' );
			label.className = 'performance-wizard-checklist-label';

			const checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.className = 'performance-wizard-checklist-checkbox';
			checkbox.checked = done;
			if ( readOnly ) {
				checkbox.disabled = true;
			}
			label.appendChild( checkbox );

			const titleSpan = document.createElement( 'span' );
			titleSpan.className = 'performance-wizard-checklist-item-title';
			titleSpan.textContent = String( item.title );
			label.appendChild( titleSpan );

			li.appendChild( label );

			if ( item.rationale ) {
				const rationale = document.createElement( 'p' );
				rationale.className = 'performance-wizard-checklist-rationale';
				rationale.textContent = String( item.rationale );
				li.appendChild( rationale );
			}

			const meta = [];
			if ( item.audit ) {
				meta.push( sprintf(
					/* translators: %s: Lighthouse audit name. */
					__( 'Audit: %s', 'wp-performance-wizard' ),
					String( item.audit )
				) );
			}
			if ( item.plugin ) {
				meta.push( sprintf(
					/* translators: %s: plugin or theme slug. */
					__( 'Plugin/theme: %s', 'wp-performance-wizard' ),
					String( item.plugin )
				) );
			}
			if ( meta.length ) {
				const metaEl = document.createElement( 'p' );
				metaEl.className = 'performance-wizard-checklist-meta';
				metaEl.textContent = meta.join( ' · ' );
				li.appendChild( metaEl );
			}

			let retest = null;
			if ( ! readOnly ) {
				retest = document.createElement( 'button' );
				retest.type = 'button';
				retest.className = 'button button-secondary performance-wizard-checklist-retest';
				retest.textContent = __( 'Re-test', 'wp-performance-wizard' );
				retest.style.display = done ? 'inline-block' : 'none';
				retest.addEventListener( 'click', function() {
					askChecklistFollowUp( item );
				} );
				li.appendChild( retest );

				checkbox.addEventListener( 'change', function() {
					const newState = readChecklistState();
					if ( checkbox.checked ) {
						newState[ key ] = true;
						li.classList.add( 'is-done' );
						retest.style.display = 'inline-block';
					} else {
						delete newState[ key ];
						li.classList.remove( 'is-done' );
						retest.style.display = 'none';
					}
					writeChecklistState( newState );
				} );
			}

			list.appendChild( li );
		} );

		wrap.appendChild( list );
		container.appendChild( wrap );
	}

	/**
	 * Fill the follow-up question input with a re-test prompt and click Ask.
	 *
	 * No-op when the analysis is not yet in its Q&A phase (the input is only
	 * added once the run has reached the 'complete' step).
	 *
	 * @param {Object} item The recommendation item being re-tested.
	 */
	function askChecklistFollowUp( item ) {
		const input = document.getElementById( 'performance-wizard-question' );
		const askBtn = document.getElementById( 'performance-wizard-ask' );
		if ( ! input || ! askBtn ) {
			return;
		}
		const title = String( item.title || '' );
		input.value = sprintf(
			/* translators: %s: the recommendation title. */
			__( 'I implemented this recommendation: "%s". Based on the data already gathered, how should I verify the change is working and what specific metrics should improve?', 'wp-performance-wizard' ),
			title
		);
		askBtn.click();
	}
} )();
