<?php

namespace MediaWiki\Extension\Score;

use Parser;
use ParserOptions;
use ValueFormatters\FormatterOptions;
use ValueParsers\StringParser;
use Wikibase\Repo\Parsers\WikibaseStringValueNormalizer;
use Wikibase\Repo\Rdf\DedupeBag;
use Wikibase\Repo\Rdf\EntityMentionListener;
use Wikibase\Repo\Rdf\NullEntityRdfBuilder;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Purtle\RdfWriter;

class Hooks {
	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		global $wgUseImageMagick, $wgScoreTrim;
		if ( $wgScoreTrim === null ) {
			// Default to if we use Image Magick, since it requires Image Magick.
			$wgScoreTrim = $wgUseImageMagick;
		}
		$parser->setHook( 'score', [ Score::class, 'render' ] );
	}

	public static function onSoftwareInfo( array &$software ) {
		try {
			$software[ '[http://lilypond.org/ LilyPond]' ] = Score::getLilypondVersion();
		} catch ( ScoreException $ex ) {
			// LilyPond executable can't found
		}
	}

	/**
	 * Add Datatype "Musical notation" to the Wikibase Repository
	 * @param array[] &$dataTypeDefinitions
	 */
	public static function onWikibaseRepoDataTypes( array &$dataTypeDefinitions ) {
		global $wgMusicalNotationEnableWikibaseDataType;

		if ( !$wgMusicalNotationEnableWikibaseDataType ) {
			return;
		}

		$dataTypeDefinitions['PT:musical-notation'] = [
			'value-type'                 => 'string',
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
			'parser-factory-callback' => static function ( ParserOptions $options ) {
				$normalizer = new WikibaseStringValueNormalizer( WikibaseRepo::getStringNormalizer() );
				return new StringParser( $normalizer );
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
		if ( !$wgMusicalNotationEnableWikibaseDataType ) {
			return;
		}
		$dataTypeDefinitions['PT:musical-notation'] = [
			'value-type'                 => 'string',
			'formatter-factory-callback' => static function ( $format, FormatterOptions $options ) {
				return new ScoreFormatter( $format );
			},
		];
	}

}
