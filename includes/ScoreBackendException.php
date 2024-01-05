<?php

namespace MediaWiki\Extension\Score;

use MediaWiki\Status\Status;
use StatusValue;

/**
 * Convenient wrapper for score-backend-error
 */
class ScoreBackendException extends ScoreException {
	/**
	 * @param StatusValue $sv Status to be passed as $1 to the exception message
	 */
	public function __construct( StatusValue $sv ) {
		parent::__construct( 'score-backend-error', [ Status::wrap( $sv )->getWikitext() ] );
	}
}
