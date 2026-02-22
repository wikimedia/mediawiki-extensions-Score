<?php

namespace MediaWiki\Extension\Score;

use ValueFormatters\FormatterOptions;
use Wikibase\Repo\Rdf\DedupeBag;
use Wikibase\Repo\Rdf\EntityMentionListener;
use Wikibase\Repo\Rdf\NullEntityRdfBuilder;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Purtle\RdfWriter;

class WikibaseHookHandler {
	/**
	 * Add Datatype "Musical notation" to the Wikibase Repository
	 * @param array[] &$dataTypeDefinitions
	 */
	public static function onWikibaseRepoDataTypes( array &$dataTypeDefinitions ) {
		global $wgMusicalNotationEnableWikibaseDataType;

		/**
		 * Enable the datatype in Quibble (CI) contexts so that we can test the integration
		 * of Score with Wikibase.
		 */
		if ( !$wgMusicalNotationEnableWikibaseDataType && !defined( 'MW_QUIBBLE_CI' ) ) {
			return;
		}

		$dataTypeDefinitions['PT:musical-notation'] = [
			'value-type' => 'string',
			'validator-factory-callback' => static function () {
				global $wgScoreMaxLength;
				// load validator builders
				$factory = WikibaseRepo::getDefaultValidatorBuilders();
				// initialize an array with string validators
				// returns an array of validators
				// that add basic string validation such as preventing empty strings
				$validators = $factory->buildStringValidators( $wgScoreMaxLength );
				// $validators[] = new ScoreValidator();
				// TODO: Take out the validation out of Score
				return $validators;
			},
			'formatter-factory-callback' => static function ( $format, FormatterOptions $options ) {
				return new ScoreFormatter( $format );
			},
			'rdf-builder-factory-callback' => static function (
				$mode,
				RdfVocabulary $vocab,
				RdfWriter $writer,
				EntityMentionListener $tracker,
				DedupeBag $dedupe
			) {
				// TODO: Implement
				return new NullEntityRdfBuilder();
			},
		];
	}

	/**
	 * Add Datatype "Musical notation" to the Wikibase Client
	 * @param array[] &$dataTypeDefinitions
	 */
	public static function onWikibaseClientDataTypes( array &$dataTypeDefinitions ) {
		global $wgMusicalNotationEnableWikibaseDataType;

		/**
		 * Enable the datatype in Quibble (CI) contexts so that we can test the integration
		 * of Score with Wikibase.
		 */
		if ( !$wgMusicalNotationEnableWikibaseDataType && !defined( 'MW_QUIBBLE_CI' ) ) {
			return;
		}
		$dataTypeDefinitions['PT:musical-notation'] = [
			'value-type' => 'string',
			'formatter-factory-callback' => static function ( $format, FormatterOptions $options ) {
				return new ScoreFormatter( $format );
			},
		];
	}

}
