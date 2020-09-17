<?php

namespace MediaWiki\Extension\Score;

/**
 * Estimate the duration of an uncompressed WAV file from its length
 */
function getWavDuration() {
	global $argv;

	if ( PHP_SAPI !== 'cli' ) {
		exit( 1 );
	}
	if ( !isset( $argv[1] ) ) {
		fwrite( STDERR, "Usage: getWavDuration.php <filename>\n" );
		exit( 1 );
	}
	// phpcs:ignore Generic.PHP.NoSilencedErrors
	$size = @filesize( $argv[1] );
	print "wavDuration: " .
		( ( $size >= 36 ? $size - 36 : 0 ) / 44100 / 4 ) .
		"\n";
}

getWavDuration();
