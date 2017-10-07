<?php
class ScoreHooks {
	/**
	 * @param Parser &$parser
	 *
	 * @return bool Returns true
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		global $wgUseImageMagick, $wgScoreTrim;
		if ( $wgScoreTrim === null ) {
			// Default to if we use Image Magick, since it requires Image Magick.
			$wgScoreTrim = $wgUseImageMagick;
		}
		$parser->setHook( 'score', 'Score::render' );
		return true;
	}

	public static function onSoftwareInfo( array &$software ) {
		try {
			$software[ '[http://lilypond.org/ LilyPond]' ] = Score::getLilypondVersion();
		} catch ( ScoreException $ex ) {
			// LilyPond executable can't found
		}
	}
}
