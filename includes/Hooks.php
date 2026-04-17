<?php

namespace MediaWiki\Extension\Score;

use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Specials\Hook\SoftwareInfoHook;

class Hooks implements
	ParserFirstCallInitHook,
	SoftwareInfoHook
{
	public function __construct(
		private readonly Config $config,
	) {
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
		} catch ( ScoreException ) {
			// LilyPond executable can't found
		}
	}

}
