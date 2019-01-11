( function () {
	var popupShown = false;

	function showPopup( $score ) {
		var $popup, midi = $score.data( 'midi' ), source = $score.data( 'source' );

		// Don't show popup when there is no midi or source.
		if ( typeof midi === 'undefined' && typeof source === 'undefined' ) {
			return;
		}

		$popup = $( '<div>' )
			.addClass( 'mw-ext-score-popup' );

		if ( typeof midi !== 'undefined' ) {
			$popup.append( $( '<a>' )
				.attr( 'href', midi )
				.html( $( '<span>' ).text( mw.msg( 'score-download-midi-file' ) ) )
			);
		}

		if ( typeof source !== 'undefined' ) {
			$popup.append( $( '<a>' )
				.attr( 'href', source )
				.attr( 'download', '' )
				.html( $( '<span>' ).text( mw.msg( 'score-download-source-file' ) ) )
			);
		}

		$score.append( $popup );

		setTimeout( function () {
			$popup.addClass( 'mw-ext-score-popup-open' );
		} );

		popupShown = true;
		$score.children( 'img' ).attr( 'aria-describedby', 'mw-ext-score-popup' );
	}

	function hidePopups( callback ) {
		// eslint-disable-next-line jquery/no-global-selector
		var $popup = $( '.mw-ext-score-popup' ), $score = $popup.closest( '.mw-ext-score' );

		$popup.removeClass( 'mw-ext-score-popup-open' );

		setTimeout( function () {
			$score.children( 'img' ).removeAttr( 'aria-describedby' );
			$popup.remove();
			popupShown = false;

			if ( callback ) {
				callback();
			}
		}, 100 );
	}

	$( document ).on( 'click', '.mw-ext-score img', function ( e ) {
		var $target = $( e.target ), $score = $target.parent(), sameScore;

		e.stopPropagation();

		// Hide popup on second click, and if it was on the other score,
		// then show new popup immediately.
		if ( popupShown ) {
			// eslint-disable-next-line jquery/no-global-selector
			sameScore = $score.is( $( '.mw-ext-score-popup' ).parent() );

			hidePopups( function () {
				if ( !sameScore ) {
					showPopup( $score );
				}
			} );

			return;
		}

		showPopup( $score );
	} );

	$( document ).on( 'click', function ( e ) {
		var $target = $( e.target );

		// Don't hide popup when clicked inside it.
		if ( $target.closest( '.mw-ext-score-popup' ).length ) {
			return;
		}

		hidePopups();
	} );
}() );
