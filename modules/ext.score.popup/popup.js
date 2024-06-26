let popupShown = false;

function showPopup( $score ) {
	const midi = $score.data( 'midi' ),
		source = $score.data( 'source' );

	// Don't show popup when there is no midi or source.
	if ( midi === undefined && source === undefined ) {
		return;
	}

	const $popup = $( '<div>' ).addClass( 'mw-ext-score-popup' );

	if ( midi !== undefined ) {
		$popup.append( $( '<a>' )
			.prop( 'href', midi )
			.append( $( '<span>' ).text( mw.msg( 'score-download-midi-file' ) ) )
		);
	}

	if ( source !== undefined ) {
		$popup.append( $( '<a>' )
			.prop( { href: source, download: '' } )
			.append( $( '<span>' ).text( mw.msg( 'score-download-source-file' ) ) )
		);
	}

	$score.append( $popup );

	setTimeout( () => {
		$popup.addClass( 'mw-ext-score-popup-open' );
	} );

	popupShown = true;
	$score.children( 'img' ).attr( 'aria-describedby', 'mw-ext-score-popup' );
}

function hidePopups( callback ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $popup = $( '.mw-ext-score-popup' ),
		$score = $popup.closest( '.mw-ext-score' );

	$popup.removeClass( 'mw-ext-score-popup-open' );

	setTimeout( () => {
		$score.children( 'img' ).removeAttr( 'aria-describedby' );
		$popup.remove();
		popupShown = false;

		if ( callback ) {
			callback();
		}
	}, 100 );
}

$( document ).on( 'click', '.mw-ext-score img', ( e ) => {
	const $target = $( e.target ),
		$score = $target.parent();

	e.stopPropagation();

	// Hide popup on second click, and if it was on the other score,
	// then show new popup immediately.
	if ( popupShown ) {
		// eslint-disable-next-line no-jquery/no-global-selector
		const sameScore = $score.is( $( '.mw-ext-score-popup' ).parent() );

		hidePopups( () => {
			if ( !sameScore ) {
				showPopup( $score );
			}
		} );

		return;
	}

	showPopup( $score );
} );

$( document ).on( 'click', ( e ) => {
	const $target = $( e.target );

	if ( !$target.closest( '.mw-ext-score-popup' ).length ) {
		// Only hide popup when clicked outside of it.
		hidePopups();
	}
} );
