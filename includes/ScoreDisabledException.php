<?php

class ScoreDisabledException extends ScoreException {
	public function __toString() {
		return Html::rawElement(
			'div',
			[ 'class' => [ 'mw-ext-score-disabled' ] ],
			$this->getMessage()
		);
	}

	public function isTracked() {
		return false;
	}
}
