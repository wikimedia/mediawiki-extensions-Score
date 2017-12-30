( function ( $ ) {
	var popupShown = false;

	function showPopup( $score ) {
		var $popup, midi = $score.data( 'midi' );
		$popup = $( '<div>' )
			.addClass( 'mw-ext-score-popup' )
			.attr( 'id', 'mw-ext-score-popup' )
			.html(
				$( '<a>' )
					.attr( 'href', midi )
					.text( mw.msg( 'score-download-midi-file' ) )
			)
			.css( 'opacity', 0 );

		$score.append( $popup );

		$popup.animate( {
			opacity: 1
		}, {
			duration: 300,
			step: function ( now ) {
				$( this ).css( 'transform', 'translateY( ' + ( -20 + now * 20 ) + 'px )' );
			}
		} );

		popupShown = true;
		$score.children( 'img' ).attr( 'aria-describedby', 'mw-ext-score-popup' );
	}

	function hidePopups( callback ) {
		var $popup = $( '.mw-ext-score-popup' ), $score = $popup.closest( '.mw-ext-score' );

		$popup.animate( {
			opacity: 0
		}, {
			duration: 300,
			step: function ( now ) {
				$( this ).css( 'transform', 'translateY( ' + ( -20 + now * 20 ) + 'px )' );
			},
			complete: function () {
				$score.children( 'img' ).removeAttr( 'aria-describedby' );
				$popup.remove();
				popupShown = false;

				if ( callback ) {
					callback();
				}
			}
		} );
	}

	$( document ).on( 'click', '.mw-ext-score img', function ( e ) {
		var $target = $( e.target ), $score = $target.parent(), sameScore;

		e.stopPropagation();

		// Hide popup on second click, and if it was on the other score, then show new popup immediately.
		if ( popupShown ) {
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

	$( document ).click( 'click', function ( e ) {
		var $target = $( e.target );

		// Don't hide popup when clicked inside it.
		if ( $target.closest( '.mw-ext-score-popup' ).length ) {
			return;
		}

		hidePopups();
	} );
}( jQuery ) );
