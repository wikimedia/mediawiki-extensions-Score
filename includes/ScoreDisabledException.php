<?php

namespace MediaWiki\Extension\Score;

use MediaWiki\Html\Html;

class ScoreDisabledException extends ScoreException {
	public function __construct() {
		parent::__construct( 'score-exec-disabled' );
	}

	/** @inheritDoc */
	protected function getBox( string $content ): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'mw-ext-score-disabled' ],
			$content
		);
	}

	/** @inheritDoc */
	public function isTracked() {
		return false;
	}

	/** @inheritDoc */
	public function getStatsdKey() {
		return false;
	}
}
