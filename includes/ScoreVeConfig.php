<?php

namespace MediaWiki\Extension\Score;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;

class ScoreVeConfig {

	/**
	 * @param RL\Context $context
	 * @return string JavaScript code
	 */
	public static function makeScript( RL\Context $context ) {
		$utils = MediaWikiServices::getInstance()->getLanguageNameUtils();
		$supportedLangs = [];
		foreach ( Score::SUPPORTED_NOTE_LANGUAGES as $lilyName => $code ) {
			$supportedLangs[$lilyName] = $utils->getLanguageName( $code );
		}
		return 'mw.config.set('
			. $context->encodeJson( [ 'wgScoreNoteLanguages' => $supportedLangs ] )
			. ');';
	}
}
