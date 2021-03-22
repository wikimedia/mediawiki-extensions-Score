<?php

namespace MediaWiki\Extension\Score;

function errorExit( $msg ) {
	fwrite( STDERR, "mw-msg:\t$msg\n" );
	exit( 20 );
}

function extractPostScriptPageSize() {
	global $argv;

	if ( PHP_SAPI !== 'cli' ) {
		exit( 1 );
	}
	if ( !isset( $argv[1] ) ) {
		fwrite( STDERR, "Usage: extractPostScriptPageSize.php <filename>\n" );
		exit( 1 );
	}

	// Lower backtracking limit as extra hardening
	ini_set( 'pcre.backtrack_limit', '500' );

	$fileName = $argv[1];
	$f = fopen( $fileName, 'r' );
	if ( !$f ) {
		errorExit( 'score-readerr' );
	}
	while ( !feof( $f ) ) {
		$line = fgets( $f );
		if ( $line === false ) {
			errorExit( 'score-readerr' );
		}
		if ( preg_match( '/^%%DocumentMedia: [^ ]* ([\d.]+) ([\d.]+)/', $line, $m ) ) {
			echo $m[1] . ' ' . $m[2] . "\n";
			exit( 0 );
		}
	}
	errorExit( 'score-readerr' );
}

extractPostScriptPageSize();
