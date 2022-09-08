<?php

namespace MediaWiki\Extension\Score;

use MediaWiki\ResourceLoader as RL;

class ScoreVeResourceLoaderModule extends RL\FileModule {

	/**
	 * @param RL\Context $context
	 * @return string JavaScript code
	 */
	public function getScript( RL\Context $context ) {
		return $this->getDataScript( $context ) . parent::getScript( $context );
	}

	/**
	 * @param RL\Context $context
	 * @return string JavaScript code
	 */
	private function getDataScript( RL\Context $context ) {
		return 'mw.config.set('
			. $context->encodeJson( [
				'wgScoreNoteLanguages' => array_map(
					'Language::fetchLanguageName',
					Score::$supportedNoteLanguages
				),
			] )
			. ');';
	}

	/**
	 * @param RL\Context $context
	 * @return array
	 */
	public function getDefinitionSummary( RL\Context $context ) {
		// Used for the module version hash
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = [
			'data' => $this->getDataScript( $context ),
		];
		return $summary;
	}
}
