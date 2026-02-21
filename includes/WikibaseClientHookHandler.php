<?php

namespace MediaWiki\Extension\Score;

use MediaWiki\Config\Config;
use ValueFormatters\FormatterOptions;
use Wikibase\Client\Hooks\WikibaseClientDataTypesHook;

class WikibaseClientHookHandler implements
	WikibaseClientDataTypesHook
{
	public function __construct(
		private readonly Config $config,
	) {
	}

	/**
	 * Add Datatype "Musical notation" to the Wikibase Client
	 * @param array[] &$dataTypeDefinitions
	 */
	public function onWikibaseClientDataTypes( array &$dataTypeDefinitions ): void {
		/**
		 * Enable the datatype in Quibble (CI) contexts so that we can test the integration
		 * of Score with Wikibase.
		 */
		if (
			!$this->config->get( 'MusicalNotationEnableWikibaseDataType' ) &&
			!defined( 'MW_QUIBBLE_CI' )
		) {
			return;
		}
		$dataTypeDefinitions['PT:musical-notation'] = [
			'value-type' => 'string',
			'formatter-factory-callback' => function ( $format, FormatterOptions $options ) {
				return new ScoreFormatter( $this->config, $format );
			},
		];
	}

}
