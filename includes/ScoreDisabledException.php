<?php

namespace MediaWiki\Extension\Score;

class ScoreDisabledException extends ScoreException {
	public function __construct() {
		parent::__construct( 'score-exec-disabled' );
	}

	/** @inheritDoc */
	protected function getCSSClasses(): array {
		return [ 'mw-ext-score-disabled' ];
	}

	public function isTracked() {
		return false;
	}

	public function getStatsdKey() {
		return false;
	}
}
