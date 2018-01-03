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
			'wgScoreNoteLanguages' => Score::$supportedNoteLanguages,
			'wgScoreDefaultNoteLanguage' => Score::$defaultNoteLanguage,
		] );
		return true;
	}

}
