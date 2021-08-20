<?php

namespace MediaWiki\Extension\Score;

function errorExit( $msg ) {
	fwrite( STDERR, "mw-msg:\t$msg\n" );
	exit( 20 );
}

function removeTagline() {
	global $argv;

	if ( PHP_SAPI !== 'cli' ) {
		exit( 1 );
	}
	if ( !isset( $argv[1] ) ) {
		fwrite( STDERR, "Usage: removeTagline.php <filename>\n" );
		exit( 1 );
	}
	$fileName = $argv[1];
	$lyData = file_get_contents( $fileName );
	if ( $lyData === false ) {
		errorExit( 'score-abcconversionerr' );
	}

	// Lower backtracking limit as extra hardening
	ini_set( 'pcre.backtrack_limit', '500' );

	$lyData = preg_replace( '/^(\s*tagline\s*=).*/m', '$1 ##f', $lyData );
	if ( $lyData === null ) {
		errorExit( 'score-pregreplaceerr' );
	}
	file_put_contents( $fileName, $lyData );
	exit( 0 );
}

removeTagline();
