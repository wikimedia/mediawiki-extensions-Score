<?php

namespace MediaWiki\Extension\Score;

use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\ResourceLoader;
use Xml;

class ScoreVeResourceLoaderModule extends RL\FileModule {

	/**
	 * @param RL\Context $context
	 * @return string JavaScript code
	 */
	public function getScript( RL\Context $context ) {
		return $this->getDataScript() . parent::getScript( $context );
	}

	/** @return string JavaScript code */
	private function getDataScript() {
		return Xml::encodeJsCall(
			'mw.config.set',
			[ [
				'wgScoreNoteLanguages' => array_map(
					'Language::fetchLanguageName',
					Score::$supportedNoteLanguages
				),
			] ],
			(bool)ResourceLoader::inDebugMode()
		);
	}

	/**
	 * @param RL\Context $context
	 * @return array
	 */
	public function getDefinitionSummary( RL\Context $context ) {
		// Used for the module version hash
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = [
			'dataScript' => $this->getDataScript(),
		];
		return $summary;
	}
}
