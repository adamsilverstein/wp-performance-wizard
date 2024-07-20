jQuery( function( $, undefined ) {
	const terminal = $( '#performance-wizard-terminal' ).terminal( function( command ) {
		// We have received input from the user.
		if ( command !== '' ) {
			var result = '';
			if ( result !== '' ) {
				this.echo( String( result ) );
			}
		}
	}, {
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
	terminal.echo( terminal.settings );
} );