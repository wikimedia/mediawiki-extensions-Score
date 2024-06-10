<?php

namespace MediaWiki\Extension\Score;

use MediaWiki\Config\Config;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SoftwareInfoHook;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\Parser;
use ValueFormatters\FormatterOptions;
use Wikibase\Repo\Rdf\DedupeBag;
use Wikibase\Repo\Rdf\EntityMentionListener;
use Wikibase\Repo\Rdf\NullEntityRdfBuilder;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Purtle\RdfWriter;

class Hooks implements
	ParserFirstCallInitHook,
	SoftwareInfoHook
{
	private Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	/**
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		global $wgScoreTrim, $wgScoreUseSvg;
		if ( $wgScoreUseSvg ) {
			// For SVG, always set true
			$wgScoreTrim = true;
		}
		if ( $wgScoreTrim === null ) {
			// Default to if we use Image Magick, since it requires Image Magick.
			$wgScoreTrim = $this->config->get( MainConfigNames::UseImageMagick );
		}
		$parser->setHook( 'score', [ Score::class, 'render' ] );
	}

	/** @inheritDoc */
	public function onSoftwareInfo( &$software ) {
		try {
			$software[ '[https://lilypond.org/ LilyPond]' ] = Score::getLilypondVersion();
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
		if ( !$wgMusicalNotationEnableWikibaseDataType ) {
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
