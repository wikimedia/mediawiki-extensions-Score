<?php

class ScoreVeResourceLoaderModule extends ResourceLoaderFileModule {

	/**
	 * @param ResourceLoaderContext $context
	 * @return string JavaScript code
	 */
	public function getScript( ResourceLoaderContext $context ) {
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
			ResourceLoader::inDebugMode()
		);
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		// Used for the module version hash
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = [
			'dataScript' => $this->getDataScript(),
		];
		return $summary;
	}
}
