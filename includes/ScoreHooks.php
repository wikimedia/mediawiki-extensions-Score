<?php

use ValueFormatters\FormatterOptions;
use ValueParsers\StringParser;
use Wikibase\Rdf\DedupeBag;
use Wikibase\Rdf\EntityMentionListener;
use Wikibase\Rdf\NullEntityRdfBuilder;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Repo\Parsers\WikibaseStringValueNormalizer;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Purtle\RdfWriter;

class ScoreHooks {
	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		global $wgUseImageMagick, $wgScoreTrim;
		if ( $wgScoreTrim === null ) {
			// Default to if we use Image Magick, since it requires Image Magick.
			$wgScoreTrim = $wgUseImageMagick;
		}
		$parser->setHook( 'score', 'Score::render' );
	}

	public static function onSoftwareInfo( array &$software ) {
		try {
			$software[ '[http://lilypond.org/ LilyPond]' ] = Score::getLilypondVersion();
		} catch ( ScoreException $ex ) {
			// LilyPond executable can't found
		}
	}

	/**
	 * Adds needed config variables to the output.
	 *
	 * This is attached to the MediaWiki 'BeforePageDisplay' hook.
	 *
	 * @param OutputPage &$output The page view.
	 * @param Skin &$skin The skin that's going to build the UI.
	 * @return bool Always true.
	 */
	public static function onBeforePageDisplay( OutputPage &$output, Skin &$skin ) {
		$output->addJsConfigVars( [
			'wgScoreNoteLanguages' => array_map(
				'Language::fetchLanguageName',
				Score::$supportedNoteLanguages
			),
			'wgScoreDefaultNoteLanguage' => Score::$defaultNoteLanguage,
		] );
		return true;
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
			'validator-factory-callback' => function () {
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
			'parser-factory-callback' => function ( ParserOptions $options ) {
				$repo = WikibaseRepo::getDefaultInstance();
				$normalizer = new WikibaseStringValueNormalizer( $repo->getStringNormalizer() );
				return new StringParser( $normalizer );
			},
			'formatter-factory-callback' => function ( $format, FormatterOptions $options ) {
				return new ScoreFormatter( $format );
			},
			'rdf-builder-factory-callback' => function (
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
			'formatter-factory-callback' => function ( $format, FormatterOptions $options ) {
				return new ScoreFormatter( $format );
			},
		];
	}

}
