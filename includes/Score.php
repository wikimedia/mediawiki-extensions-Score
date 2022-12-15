<?php
/*
	Score, a MediaWiki extension for rendering musical scores with LilyPond.
	Copyright © 2011 Alexander Klauer

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.

	To contact the author:
	<Graf.Zahl@gmx.net>
	http://en.wikisource.org/wiki/User_talk:GrafZahl
	https://github.com/TheCount/score

 */

namespace MediaWiki\Extension\Score;

use Exception;
use FileBackend;
use FormatJson;
use FSFileBackend;
use Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Message;
use NullLockManager;
use Parser;
use PPFrame;
use Shellbox\Command\BoxedCommand;
use Title;
use WikiMap;
use Wikimedia\ScopedCallback;

/**
 * Score class.
 */
class Score {
	/**
	 * Version for cache invalidation.
	 */
	private const CACHE_VERSION = 1;

	/**
	 * Cache expiry time for the LilyPond version
	 */
	private const VERSION_TTL = 3600;

	/**
	 * Supported score languages.
	 */
	private static $supportedLangs = [ 'lilypond', 'ABC' ];

	/**
	 * Supported note languages.
	 * Key is LilyPond filename. Value is language code
	 */
	public static $supportedNoteLanguages = [
		'arabic' => 'ar',
		'catalan' => 'ca',
		'deutsch' => 'de',
		'english' => 'en',
		'espanol' => 'es',
		'italiano' => 'it',
		'nederlands' => 'nl',
		'norsk' => 'no',
		'portugues' => 'pt',
		'suomi' => 'fi',
		'svenska' => 'sv',
		'vlaams' => 'vls',
	];

	/**
	 * Default language used for notes.
	 */
	public static $defaultNoteLanguage = 'nederlands';

	/**
	 * LilyPond version string.
	 * It defaults to null and is set the first time it is required.
	 */
	private static $lilypondVersion = null;

	/**
	 * FileBackend instance cache
	 */
	private static $backend;

	/**
	 * Throws proper ScoreException in case of failed shell executions.
	 *
	 * @param string $message Message key to display
	 * @param array $params Message parameters
	 * @param string $output collected output from stderr.
	 * @param string|bool $factoryDir The factory directory to replace with "..."
	 *
	 * @throws ScoreException always.
	 * @return never
	 */
	private static function throwCallException( $message, array $params, $output, $factoryDir = false ) {
		/* clean up the output a bit */
		if ( $factoryDir ) {
			$output = str_replace( $factoryDir, '...', $output );
		}
		$params[] = Html::rawElement( 'pre',
			// Error messages from LilyPond & abc2ly are always English
			[ 'lang' => 'en', 'dir' => 'ltr' ],
			htmlspecialchars( $output )
		);
		throw new ScoreException( $message, $params );
	}

	/**
	 * @return string
	 * @throws ScoreException if LilyPond could not be executed properly.
	 */
	public static function getLilypondVersion() {
		global $wgScoreLilyPondFakeVersion;

		if ( strlen( $wgScoreLilyPondFakeVersion ) ) {
			return $wgScoreLilyPondFakeVersion;
		}
		if ( self::$lilypondVersion === null ) {
			// In case fetchLilypondVersion() throws an exception
			self::$lilypondVersion = 'disabled';

			$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
			self::$lilypondVersion = $cache->getWithSetCallback(
				$cache->makeGlobalKey( __CLASS__, 'lilypond-version' ),
				self::VERSION_TTL,
				function () {
					return self::fetchLilypondVersion();
				}
			);
		}

		return self::$lilypondVersion;
	}

	/**
	 * Determines the version of LilyPond in use without caching
	 *
	 * @throws ScoreException if LilyPond could not be executed properly.
	 * @return string
	 */
	private static function fetchLilypondVersion() {
		global $wgScoreLilyPond, $wgScoreEnvironment;

		$result = self::boxedCommand()
			->routeName( 'score-lilypond' )
			->params( $wgScoreLilyPond, '--version' )
			->environment( $wgScoreEnvironment )
			->includeStderr()
			->execute();
		self::recordShellout( 'lilypond_version' );

		$output = $result->getStdout();
		if ( $result->getExitCode() != 0 ) {
			self::throwCallException( 'score-versionerr', [], $output );
		}

		if ( !preg_match( '/^GNU LilyPond (\S+)/', $output, $m ) ) {
			self::throwCallException( 'score-versionerr', [], $output );
		}
		return $m[1];
	}

	/**
	 * Return a BoxedCommand object, or throw an exception if shell execution is
	 * disabled.
	 *
	 * The check for $wgScoreDisableExec should be redundant with checks in the
	 * callers, since the callers generally need to avoid writing input files.
	 * We check twice to be safe.
	 *
	 * @return BoxedCommand
	 * @throws ScoreDisabledException
	 */
	private static function boxedCommand() {
		global $wgScoreDisableExec;

		if ( $wgScoreDisableExec ) {
			throw new ScoreDisabledException();
		}

		return MediaWikiServices::getInstance()->getShellCommandFactory()
			->createBoxed( 'score' )
			->disableNetwork()
			->firejailDefaultSeccomp();
	}

	/**
	 * Creates the specified local directory if it does not exist yet.
	 * Otherwise does nothing.
	 *
	 * @param string $path Local path to directory to be created.
	 * @param int|null $mode Chmod value of the new directory.
	 *
	 * @throws ScoreException if the directory does not exist and could not
	 * 	be created.
	 */
	private static function createDirectory( $path, $mode = null ) {
		if ( !is_dir( $path ) ) {
			$rc = wfMkdirParents( $path, $mode, __METHOD__ );
			if ( !$rc ) {
				throw new ScoreException( 'score-nooutput', [ $path ] );
			}
		}
	}

	/**
	 * @return bool|string
	 */
	private static function getBaseUrl() {
		global $wgScorePath, $wgUploadPath;
		if ( $wgScorePath === false ) {
			return "{$wgUploadPath}/lilypond";
		}

		return $wgScorePath;
	}

	/**
	 * @return FileBackend
	 */
	public static function getBackend() {
		global $wgScoreFileBackend;

		if ( $wgScoreFileBackend ) {
			return MediaWikiServices::getInstance()->getFileBackendGroup()
				->get( $wgScoreFileBackend );
		}

		if ( !self::$backend ) {
			global $wgScoreDirectory, $wgUploadDirectory;
			if ( $wgScoreDirectory === false ) {
				// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
				$dir = "{$wgUploadDirectory}/lilypond";
			} else {
				$dir = $wgScoreDirectory;
			}
			self::$backend = new FSFileBackend( [
				'name'           => 'score-backend',
				'wikiId'         => WikiMap::getCurrentWikiId(),
				'lockManager'    => new NullLockManager( [] ),
				'containerPaths' => [ 'score-render' => $dir ],
				'fileMode'       => 0777,
				'obResetFunc' => 'wfResetOutputBuffers',
				'streamMimeFunc' => [ 'StreamFile', 'contentTypeFromPath' ],
				'statusWrapper' => [ 'Status', 'wrap' ],
				'logger' => LoggerFactory::getInstance( 'score' ),
			] );
		}

		return self::$backend;
	}

	/**
	 * Callback for Parser's hook on 'score' tags. Renders the score code.
	 *
	 * @param string $code score code.
	 * @param array $args array of score tag attributes.
	 * @param Parser $parser Parser of Mediawiki.
	 * @param PPFrame $frame expansion frame, not used by this extension.
	 *
	 * @throws ScoreException
	 * @return string Image link HTML, and possibly anchor to MIDI file.
	 */
	public static function render( $code, array $args, Parser $parser, PPFrame $frame ) {
		return self::renderScore( $code, $args, $parser );
	}

	/**
	 * Renders the score code (LilyPond, ABC, etc.) in a <score>…</score> tag.
	 *
	 * @param string $code score code.
	 * @param array $args array of score tag attributes.
	 * @param Parser $parser Parser of Mediawiki.
	 *
	 * @throws ScoreException
	 * @return string Image link HTML, and possibly anchor to MIDI file.
	 */
	public static function renderScore( $code, array $args, Parser $parser ) {
		global $wgTmpDirectory;

		try {
			$baseUrl = self::getBaseUrl();
			$baseStoragePath = self::getBackend()->getRootStoragePath() . '/score-render';

			// options to self::generateHTML()
			$options = [];

			if ( isset( $args['line_width_inches'] ) ) {
				$lineWidthInches = abs( (float)$args[ 'line_width_inches' ] );
				if ( $lineWidthInches > 0 ) {
					$options['line_width_inches'] = $lineWidthInches;
				}
			}

			/* temporary working directory to use */
			$fuzz = md5( (string)mt_rand() );
			$options['factory_directory'] = $wgTmpDirectory . "/MWLP.$fuzz";

			/* Score language selection */
			if ( array_key_exists( 'lang', $args ) ) {
				$options['lang'] = $args['lang'];
			} else {
				$options['lang'] = 'lilypond';
			}
			if ( !in_array( $options['lang'], self::$supportedLangs ) ) {
				throw new ScoreException( 'score-invalidlang',
					[ htmlspecialchars( $options['lang'] ) ] );
			}

			/* Override MIDI file? */
			if ( array_key_exists( 'override_midi', $args ) ) {
				$file = MediaWikiServices::getInstance()->getRepoGroup()
					->findFile( $args['override_midi'] );
				if ( $file === false ) {
					throw new ScoreException( 'score-midioverridenotfound',
						[ htmlspecialchars( $args['override_midi'] ) ] );
				}
				if ( $parser->getOutput() !== null ) {
					$parser->getOutput()->addImage( $file->getName() );
				}

				$options['override_midi'] = true;
				$options['midi_file'] = $file;
				/* Set output stuff in case audio rendering is requested */
				$sha1 = $file->getSha1();
				$audioRelDir = "override-midi/{$sha1[0]}/{$sha1[1]}";
				$audioRel = "$audioRelDir/$sha1.mp3";
				$options['audio_storage_dir'] = "$baseStoragePath/$audioRelDir";
				$options['audio_storage_path'] = "$baseStoragePath/$audioRel";
				$options['audio_url'] = "$baseUrl/$audioRel";
				$options['audio_sha_name'] = "$sha1.mp3";
				$parser->addTrackingCategory( 'score-deprecated-category' );
			} else {
				$options['override_midi'] = false;
			}

			// Raw rendering?
			$options['raw'] = array_key_exists( 'raw', $args );

			/* Note language selection */
			if ( array_key_exists( 'note-language', $args ) ) {
				if ( !$options['raw'] ) {
					$options['note-language'] = $args['note-language'];
				} else {
					throw new ScoreException( 'score-notelanguagewithraw' );
				}
			} else {
				$options['note-language'] = self::$defaultNoteLanguage;
			}
			if ( !isset( self::$supportedNoteLanguages[$options['note-language']] ) ) {
				throw new ScoreException(
					'score-invalidnotelanguage', [
						Message::plaintextParam( $options['note-language'] ),
						Message::plaintextParam( implode( ', ', array_keys( self::$supportedNoteLanguages ) ) )
					]
				);
			}

			/* Override audio file? */
			if ( array_key_exists( 'override_audio', $args )
				|| array_key_exists( 'override_ogg', $args ) ) {
				$overrideAudio = $args['override_ogg'] ?? $args['override_audio'];
				$t = Title::newFromText( $overrideAudio, NS_FILE );
				if ( $t === null ) {
					throw new ScoreException( 'score-invalidaudiooverride',
						[ htmlspecialchars( $overrideAudio ) ] );
				}
				if ( !$t->isKnown() ) {
					throw new ScoreException( 'score-audiooverridenotfound',
						[ htmlspecialchars( $overrideAudio ) ] );
				}
				$options['override_audio'] = true;
				$options['audio_name'] = $overrideAudio;
				$parser->addTrackingCategory( 'score-deprecated-category' );
			} else {
				$options['override_audio'] = false;
			}

			/* Audio rendering? */
			$options['generate_audio'] = array_key_exists( 'sound', $args )
				|| array_key_exists( 'vorbis', $args );

			if ( $options['generate_audio'] && $options['override_audio'] ) {
				throw new ScoreException( 'score-convertoverrideaudio' );
			}

			// Input for cache key
			$cacheOptions = [
				'code' => $code,
				'lang' => $options['lang'],
				'note-language' => $options['note-language'],
				'raw'  => $options['raw'],
				'ExtVersion' => self::CACHE_VERSION,
				'LyVersion' => self::getLilypondVersion(),
			];

			/* image file path and URL prefixes */
			$imageCacheName = \Wikimedia\base_convert( sha1( serialize( $cacheOptions ) ), 16, 36, 31 );
			$imagePrefixEnd = "{$imageCacheName[0]}/" .
				"{$imageCacheName[1]}/$imageCacheName";
			$options['dest_storage_path'] = "$baseStoragePath/$imagePrefixEnd";
			$options['dest_url'] = "$baseUrl/$imagePrefixEnd";
			$options['file_name_prefix'] = substr( $imageCacheName, 0, 8 );

			$html = self::generateHTML( $parser, $code, $options );
		} catch ( ScoreException $e ) {
			if ( $parser->getOutput() !== null ) {
				$parser->getOutput()->addModules( [ 'ext.score.errors' ] );
				if ( $e->isTracked() ) {
					$parser->addTrackingCategory( 'score-error-category' );
				}
				self::recordError( $e );
			}
			$html = $e->getHtml();
		}

		// Mark the page as using the score extension, it makes easier
		// to track all those pages.
		if ( $parser->getOutput() !== null ) {
			$parser->getOutput()->setPageProperty( 'score', '' );
			// Transition to a tracking category
			$parser->addTrackingCategory( 'score-use-category' );
		}

		return $html;
	}

	/**
	 * Generates the HTML code for a score tag.
	 *
	 * @param Parser $parser MediaWiki parser.
	 * @param string $code Score code.
	 * @param array $options array of rendering options.
	 * 	The options keys are:
	 * 	- factory_directory: string Path to directory in which files
	 * 		may be generated without stepping on someone else's
	 * 		toes. The directory may not exist yet. Required.
	 * 	- generate_audio: bool Whether to create an audio file in
	 * 		TimedMediaHandler. If set to true, the override_audio option
	 * 		must be set to false. Required.
	 *  - dest_storage_path: The path of the destination directory relative to
	 *  	the current backend. Required.
	 *  - dest_url: The default destination URL. Required.
	 *  - file_name_prefix: The filename prefix used for all files
	 *  	in the default destination directory. Required.
	 * 	- lang: string Score language. Required.
	 * 	- override_midi: bool Whether to use a user-provided MIDI file.
	 * 		Required.
	 * 	- midi_file: If override_midi is true, MIDI file object.
	 * 	- audio_storage_dir: If override_midi and generate_audio are true, the
	 * 		backend directory in which the audio file is to be stored.
	 * 	- audio_storage_path: string If override_midi and generate_audio are true,
	 * 		the backend path at which the generated audio file is to be
	 * 		stored.
	 * 	- audio_url: string If override_midi and generate_audio is true,
	 * 		the URL corresponding to audio_storage_path
	 *  - audio_sha_name: string If override_midi, generated audio file name.
	 * 	- override_audio: bool Whether to generate a wikilink to a
	 * 		user-provided audio file. If set to true, the sound
	 * 		option must be set to false. Required.
	 * 	- audio_name: string If override_audio is true, the audio file name
	 * 	- raw: bool Whether to assume raw LilyPond code. Ignored if the
	 * 		language is not lilypond, required otherwise.
	 *	- note-language: language to use for notes (one of supported by LilyPond)
	 *
	 * @return string HTML.
	 *
	 * @throws Exception
	 * @throws ScoreException if an error occurs.
	 */
	private static function generateHTML( Parser $parser, $code, $options ) {
		global $wgScoreOfferSourceDownload, $wgScoreUseSvg;

		$cleanup = new ScopedCallback( function () use ( $options ) {
			self::eraseDirectory( $options['factory_directory'] );
		} );
		if ( $parser->getOutput() !== null ) {
			$parser->getOutput()->addModules( [ 'ext.score.popup' ] );
		}

		$backend = self::getBackend();
		$fileIter = $backend->getFileList(
			[ 'dir' => $options['dest_storage_path'], 'topOnly' => true ] );
		if ( $fileIter === null ) {
			throw new ScoreException( 'score-file-list-error' );
		}
		$existingFiles = [];
		foreach ( $fileIter as $file ) {
			$existingFiles[$file] = true;
		}

		/* Generate SVG, PNG and MIDI files if necessary */
		$imageFileName = "{$options['file_name_prefix']}.png";
		$imageSvgFileName = "{$options['file_name_prefix']}.svg";
		$multi1FileName = "{$options['file_name_prefix']}-page1.png";
		$multi1SvgFileName = "{$options['file_name_prefix']}-1.svg";
		$midiFileName = "{$options['file_name_prefix']}.midi";
		$metaDataFileName = "{$options['file_name_prefix']}.json";
		$audioUrl = '';

		if ( isset( $existingFiles[$metaDataFileName] ) ) {
			$metaDataFile = $backend->getFileContents(
				[ 'src' => "{$options['dest_storage_path']}/$metaDataFileName" ] );
			if ( $metaDataFile === false ) {
				throw new ScoreException( 'score-nocontent', [ $metaDataFileName ] );
			}
			$metaData = FormatJson::decode( $metaDataFile, true );
		} else {
			$metaData = [];
		}

		if (
			!isset( $existingFiles[$metaDataFileName] )
			|| (
				!isset( $existingFiles[$imageFileName] )
				&& !isset( $existingFiles[$multi1FileName] )
			)
			|| (
				$wgScoreUseSvg
				&& !isset( $existingFiles[$multi1SvgFileName] )
				&& !isset( $existingFiles[$imageSvgFileName] )
			)
			|| (
				!isset( $metaData[$imageFileName]['size'] )
				&& !isset( $metaData[$multi1FileName]['size'] )
			)
			|| !isset( $existingFiles[$midiFileName] ) ) {
			$existingFiles += self::generatePngAndMidi( $code, $options, $metaData );
		}

		/* Generate audio file if necessary */
		if ( $options['generate_audio'] ) {
			$audioFileName = "{$options['file_name_prefix']}.mp3";
			if ( $options['override_midi'] ) {
				$audioUrl = $options['audio_url'];
				$audioPath = $options['audio_storage_path'];
				$exists = $backend->fileExists( [ 'src' => $options['audio_storage_path'] ] );
				if (
					!$exists ||
					!isset( $metaData[ $options['audio_sha_name'] ]['length'] ) ||
					!$metaData[ $options['audio_sha_name'] ]['length']
				) {
					$backend->prepare( [ 'dir' => $options['audio_storage_dir'] ] );
					$sourcePath = $options['midi_file']->getLocalRefPath();
					self::generateAudio( $sourcePath, $options, $audioPath, $metaData );
				}
			} else {
				$audioUrl = "{$options['dest_url']}/$audioFileName";
				$audioPath = "{$options['dest_storage_path']}/$audioFileName";
				if (
					!isset( $existingFiles[$audioFileName] ) ||
					!isset( $metaData[$audioFileName]['length'] ) ||
					!$metaData[$audioFileName]['length']
				) {
					// Maybe we just generated it
					$sourcePath = "{$options['factory_directory']}/file.midi";
					if ( !file_exists( $sourcePath ) ) {
						// No, need to fetch it from the backend
						$sourceFileRef = $backend->getLocalReference(
							[ 'src' => "{$options['dest_storage_path']}/$midiFileName" ] );
						$sourcePath = $sourceFileRef->getPath();
					}
					self::generateAudio( $sourcePath, $options, $audioPath, $metaData );
				}
			}
		}

		/* return output link(s) */
		if ( isset( $existingFiles[$imageFileName] ) ) {
			list( $width, $height ) = $metaData[$imageFileName]['size'];
			$attribs = [
				'src' => "{$options['dest_url']}/$imageFileName",
				'width' => $width,
				'height' => $height,
				'alt' => $code,
			];
			if ( $wgScoreUseSvg ) {
				$attribs['srcset'] = "{$options['dest_url']}/$imageSvgFileName 1x";
			}
			$link = Html::rawElement( 'img', $attribs );
		} elseif ( isset( $existingFiles[$multi1FileName] ) ) {
			$link = '';
			for ( $i = 1; ; ++$i ) {
				$fileName = "{$options['file_name_prefix']}-page$i.png";
				if ( !isset( $existingFiles[$fileName] ) ) {
					break;
				}
				$pageNumb = wfMessage( 'score-page' )
					->inContentLanguage()
					->numParams( $i )
					->plain();
				list( $width, $height ) = $metaData[$fileName]['size'];
				$attribs = [
					'src' => "{$options['dest_url']}/$fileName",
					'width' => $width,
					'height' => $height,
					'alt' => $pageNumb,
					'title' => $pageNumb,
					'style' => "margin-bottom:1em"
				];
				if ( $wgScoreUseSvg ) {
					$svgFileName = "{$options['file_name_prefix']}-$i.svg";
					$attribs['srcset'] = "{$options['dest_url']}/$svgFileName 1x";
				}
				$link .= Html::rawElement( 'img', $attribs );
			}
		} else {
			$link = '';
		}
		if ( $options['generate_audio'] ) {
			$link .= '<div style="margin-top: 3px;">' .
				Html::rawElement(
					'audio',
					[
						'controls' => true
					],
					Html::openElement(
						'source',
						[
							'src' => $audioUrl,
							'type' => 'audio/mpeg',
						]
					) .
					"<div>" .
					wfMessage( 'score-audio-alt' )
						->rawParams(
							Html::element( 'a', [ 'href' => $audioUrl ],
								wfMessage( 'score-audio-alt-link' )->text()
							)
						)
						->escaped() .
					'</div>'
				) .
				'</div>';
		}
		if ( $options['override_audio'] !== false ) {
			$link .= $parser->recursiveTagParse( "[[File:{$options['audio_name']}]]" );
		}

		// Clean up the factory directory now
		ScopedCallback::consume( $cleanup );

		$attributes = [
			'class' => 'mw-ext-score'
		];

		if ( $options['override_midi']
			|| isset( $existingFiles["{$options['file_name_prefix']}.midi"] ) ) {
			$attributes['data-midi'] = $options['override_midi'] ?
				$options['midi_file']->getUrl()
				: "{$options['dest_url']}/{$options['file_name_prefix']}.midi";
		}

		if ( $wgScoreOfferSourceDownload
			&& isset( $existingFiles["{$options['file_name_prefix']}.ly"] )
		) {
			$attributes['data-source'] = "{$options['dest_url']}/{$options['file_name_prefix']}.ly";
		}

		// Wrap score in div container.
		return Html::rawElement( 'div', $attributes, $link );
	}

	/**
	 * Generates score PNG file(s) and a MIDI file. Stores lilypond file.
	 *
	 * @param string $code Score code.
	 * @param array $options Rendering options. They are the same as for
	 * 	Score::generateHTML().
	 * @param array &$metaData array to hold information about images
	 *
	 * @return array of file names placed in the remote dest dir, with the
	 * 	file names in each key.
	 *
	 * @throws ScoreException on error.
	 */
	private static function generatePngAndMidi( $code, $options, &$metaData ) {
		global $wgScoreLilyPond, $wgScoreTrim, $wgScoreSafeMode, $wgScoreDisableExec,
			$wgScoreGhostscript, $wgScoreAbc2Ly, $wgImageMagickConvertCommand, $wgScoreUseSvg,
			$wgScoreShell, $wgPhpCli, $wgScoreEnvironment, $wgScoreImageMagickConvert;

		if ( $wgScoreDisableExec ) {
			throw new ScoreDisabledException();
		}

		if ( $wgScoreSafeMode
			&& version_compare( self::getLilypondVersion(), '2.23.12', '>=' )
		) {
			throw new ScoreException( 'score-safe-mode' );
		}

		/* Create the working environment */
		$factoryDirectory = $options['factory_directory'];
		self::createDirectory( $factoryDirectory, 0700 );
		$factoryMidi = "$factoryDirectory/file.midi";

		$command = self::boxedCommand()
			->routeName( 'score-lilypond' )
			->params(
				$wgScoreShell,
				'scripts/generatePngAndMidi.sh' )
			->outputFileToFile( 'file.midi', $factoryMidi )
			->outputGlobToFile( 'file', 'png', $factoryDirectory )
			->outputGlobToFile( 'file', 'svg', $factoryDirectory )
			->includeStderr()
			->environment( [
				'SCORE_ABC2LY' => $wgScoreAbc2Ly,
				'SCORE_LILYPOND' => $wgScoreLilyPond,
				'SCORE_USESVG' => $wgScoreUseSvg ? 'yes' : 'no',
				'SCORE_SAFE' => $wgScoreSafeMode ? 'yes' : 'no',
				'SCORE_GHOSTSCRIPT' => $wgScoreGhostscript,
				'SCORE_CONVERT' => $wgScoreImageMagickConvert ?: $wgImageMagickConvertCommand,
				'SCORE_TRIM' => $wgScoreTrim ? 'yes' : 'no',
				'SCORE_PHP' => $wgPhpCli
			] + $wgScoreEnvironment );
		self::addScript( $command, 'generatePngAndMidi.sh' );
		if ( !$wgScoreUseSvg ) {
			self::addScript( $command, 'extractPostScriptPageSize.php' );
		}
		if ( $options['lang'] === 'lilypond' ) {
			if ( $options['raw'] ) {
				$lilypondCode = $code;
			} else {
				$paperConfig = [];
				if ( isset( $options['line_width_inches'] ) ) {
					$paperConfig['line-width'] = $options['line_width_inches'] . "\in";
				}
				$paperCode = self::getPaperCode( $paperConfig );

				$lilypondCode = self::embedLilypondCode( $code, $options['note-language'], $paperCode );
			}
			$command->inputFileFromString( 'file.ly', $lilypondCode );
		} else {
			self::addScript( $command, 'removeTagline.php' );
			$command->inputFileFromString( 'file.abc', $code );
			$command->outputFileToString( 'file.ly' );
			$lilypondCode = '';
		}
		$result = $command->execute();
		self::recordShellout( 'generate_png_and_midi' );

		if ( $result->getExitCode() != 0 ) {
			self::throwCompileException( $result->getStdout(), $options );
		}

		if ( $result->wasReceived( 'file.ly' ) ) {
			$lilypondCode = $result->getFileContents( 'file.ly' );
		}

		$numPages = 0;
		for ( $i = 1; ; $i++ ) {
			if ( !$result->wasReceived( "file-page$i.png" ) ) {
				$numPages = $i - 1;
				break;
			}
		}

		# LilyPond 2.24+ generates file.png and file.svg if there is only one page
		if ( $wgScoreUseSvg && $result->wasReceived( 'file.svg' ) ) {
			$numPages = 1;
		}

		if ( $numPages === 0 ) {
			throw new ScoreException( 'score-noimages' );
		}

		$needMidi = false;
		$haveMidi = $result->wasReceived( 'file.midi' );
		if ( !$options['raw'] || $options['generate_audio'] && !$options['override_midi'] ) {
			$needMidi = true;
			if ( !$haveMidi ) {
				throw new ScoreException( 'score-nomidi' );
			}
		}

		// Create the destination directory if it doesn't exist
		$backend = self::getBackend();
		$status = $backend->prepare( [ 'dir' => $options['dest_storage_path'] ] );
		if ( !$status->isOK() ) {
			throw new ScoreBackendException( $status );
		}

		// File names of generated files
		$newFiles = [];
		// Backend operation batch
		$ops = [];

		// Add LY source to its file
		$ops[] = [
			'op' => 'create',
			'content' => $lilypondCode,
			'dst' => "{$options['dest_storage_path']}/{$options['file_name_prefix']}.ly",
			'headers' => [
				'Content-Type' => 'text/x-lilypond; charset=utf-8'
			]
		];
		$newFiles["{$options['file_name_prefix']}.ly"] = true;

		if ( $needMidi ) {
			// Add the MIDI file to the batch
			$ops[] = [
				'op' => 'store',
				'src' => $factoryMidi,
				'dst' => "{$options['dest_storage_path']}/{$options['file_name_prefix']}.midi" ];
			$newFiles["{$options['file_name_prefix']}.midi"] = true;
			if ( !$status->isOK() ) {
				throw new ScoreBackendException( $status );
			}
		}

		// Add the PNG and SVG image files
		for ( $i = 1; $i <= $numPages; ++$i ) {
			$srcPng = "$factoryDirectory/file-page$i.png";
			$srcSvg = "$factoryDirectory/file-$i.svg";
			$dstPngFileName = "{$options['file_name_prefix']}-page$i.png";
			$dstSvgFileName = "{$options['file_name_prefix']}-$i.svg";
			if ( $numPages === 1 ) {
				$dstPngFileName = "{$options['file_name_prefix']}.png";
				if ( $wgScoreUseSvg ) {
					$srcPng = "$factoryDirectory/file.png";
					$srcSvg = "$factoryDirectory/file.svg";
					$dstSvgFileName = "{$options['file_name_prefix']}.svg";
				}
			}
			$destPng = "{$options['dest_storage_path']}/$dstPngFileName";
			$ops[] = [
				'op' => 'store',
				'src' => $srcPng,
				'dst' => $destPng
			];
			list( $width, $height ) = self::imageSize( $srcPng );
			$metaData[$dstPngFileName]['size'] = [ $width, $height ];
			$newFiles[$dstPngFileName] = true;

			if ( $wgScoreUseSvg ) {
				$destSvg = "{$options['dest_storage_path']}/$dstSvgFileName";
				$ops[] = [
					'op' => 'store',
					'src' => $srcSvg,
					'dst' => $destSvg,
					'headers' => [
						'Content-Type' => 'image/svg+xml'
					]
				];
				$newFiles[$dstSvgFileName] = true;
			}
		}

		$dstFileName = "{$options['file_name_prefix']}.json";
		$dest = "{$options['dest_storage_path']}/$dstFileName";
		$ops[] = [
			'op' => 'create',
			'content' => FormatJson::encode( $metaData ),
			'dst' => $dest ];

		$newFiles[$dstFileName] = true;

		// Execute the batch
		$status = $backend->doQuickOperations( $ops );
		if ( !$status->isOK() ) {
			throw new ScoreBackendException( $status );
		}
		return $newFiles;
	}

	/**
	 * Add an input file from the scripts directory
	 *
	 * @param BoxedCommand $command
	 * @param string $script
	 */
	private static function addScript( BoxedCommand $command, string $script ) {
		$command->inputFileFromFile( "scripts/$script",
			__DIR__ . "/../scripts/$script" );
	}

	/**
	 * Get error information from the output returned by scripts/generatePngAndMidi.sh
	 * and throw a relevant error.
	 *
	 * @param string $stdout
	 * @param array $options
	 * @throws ScoreException
	 */
	private static function throwCompileException( $stdout, $options ) {
		global $wgScoreDebugOutput;

		$message = self::extractMessage( $stdout );
		if ( !$message ) {
			$message = [ 'score-compilererr', [] ];
		} elseif ( !$wgScoreDebugOutput && $message[0] === 'score-compilererr' ) {
			// when input is not raw, we build the final lilypond file content
			// in self::embedLilypondCode. The user input then is not inserted
			// on the first line in the file we pass to lilypond and so we need
			// to offset error messages back.
			$scoreFirstLineOffset = $options['raw'] ? 0 : 7;
			$errMsgBeautifier = new LilypondErrorMessageBeautifier( $scoreFirstLineOffset );

			$stdout = $errMsgBeautifier->beautifyMessage( $stdout );
		}
		self::throwCallException(
			$message[0],
			$message[1],
			$stdout
		);
	}

	/**
	 * Get error information from the output returned by scripts/synth.sh
	 * and throw a relevant error.
	 *
	 * @param string $stdout
	 * @throws ScoreException
	 */
	private static function throwSynthException( $stdout ) {
		$message = self::extractMessage( $stdout );
		if ( !$message ) {
			$message = [ 'score-audioconversionerr', [] ];
		}
		self::throwCallException(
			$message[0],
			$message[1],
			$stdout
		);
	}

	/**
	 * Parse the script return value and extract any mw-msg lines. Modify the
	 * text to remove the lines. Return the first mw-msg line as a message
	 * key and parameters. If there was no mw-msg line, return null.
	 *
	 * @param string &$stdout
	 * @return array|null
	 */
	private static function extractMessage( &$stdout ) {
		$filteredStdout = '';
		$messageParams = [];
		foreach ( explode( "\n", $stdout ) as $line ) {
			if ( preg_match( '/^mw-msg:\t/', $line ) ) {
				if ( !$messageParams ) {
					$messageParams = array_slice( explode( "\t", $line ), 1 );
				}
			} else {
				if ( $filteredStdout !== '' ) {
					$filteredStdout .= "\n";
				}
				$filteredStdout .= $line;
			}
		}
		$stdout = $filteredStdout;
		if ( $messageParams ) {
			$messageName = array_shift( $messageParams );
			// Used messages:
			// - score-abc2lynotexecutable
			// - score-abcconversionerr
			// - score-notexecutable
			// - score-compilererr
			// - score-nops
			// - score-scripterr
			// - score-gs-error
			// - score-trimerr
			// - score-readerr
			// - score-pregreplaceerr
			// - score-audioconversionerr
			// - score-soundfontnotexists
			// - score-fallbacknotexecutable
			// - score-lamenotexecutable
			return [ $messageName, $messageParams ];
		} else {
			return null;
		}
	}

	/**
	 * Extract the size of one of our generated PNG images
	 *
	 * @param string $filename
	 * @return array of ints (width, height)
	 */
	private static function imageSize( $filename ) {
		list( $width, $height ) = getimagesize( $filename );
		return [ $width, $height ];
	}

	/**
	 * @param array $paperConfig
	 * @return string
	 */
	private static function getPaperCode( $paperConfig = [] ) {
		$config = array_merge( [
			"indent" => "0\\mm",
		], $paperConfig );

		$paperCode = "\\paper {\n";
		foreach ( $config as $key => $value ) {
			$paperCode .= "\t$key = $value\n";
		}
		$paperCode .= "}";

		return $paperCode;
	}

	/**
	 * Embeds simple LilyPond code in a score block.
	 *
	 * @param string $lilypondCode Simple LilyPond code.
	 * @param string $noteLanguage Language of notes.
	 * @param string $paperCode
	 *
	 * @return string Raw lilypond code.
	 *
	 * @throws ScoreException if determining the LilyPond version fails.
	 */
	private static function embedLilypondCode( $lilypondCode, $noteLanguage, $paperCode ) {
		$version = self::getLilypondVersion();

		// Check if parameters have already been supplied (hybrid-raw mode)
		$options = "";
		if ( strpos( $lilypondCode, "\\layout" ) === false ) {
			$options .= "\\layout { }\n";
		}
		if ( strpos( $lilypondCode, "\\midi" ) === false ) {
			$options .= <<<LY
	\\midi {
		\\context { \Score tempoWholesPerMinute = #(ly:make-moment 100 4) }
	}
LY;
		}

		/* Raw code. In Scheme, ##f is false and ##t is true. */
		/* Set the default MIDI tempo to 100, 60 is a bit too slow */
		$raw = <<<LILYPOND
\\header {
	tagline = ##f
}
\\version "$version"
\\language "$noteLanguage"
\\score {

$lilypondCode
$options

}
$paperCode
LILYPOND;

		return $raw;
	}

	/**
	 * Generates an audio file from a MIDI file using fluidsynth with TiMidity as fallback.
	 *
	 * @param string $sourceFile The local filename of the MIDI file
	 * @param array $options array of rendering options.
	 * @param string $remoteDest The backend storage path to upload the audio file to
	 * @param array &$metaData Array with metadata information
	 *
	 * @throws ScoreException if an error occurs.
	 */
	private static function generateAudio( $sourceFile, $options, $remoteDest, &$metaData ) {
		global $wgScoreFluidsynth, $wgScoreSoundfont, $wgScoreLame, $wgScoreDisableExec,
			$wgScoreEnvironment, $wgScoreShell, $wgPhpCli;

		if ( $wgScoreDisableExec ) {
			throw new ScoreDisabledException();
		}

		// Working environment
		$factoryDir = $options['factory_directory'];
		self::createDirectory( $factoryDir, 0700 );
		$factoryFile = "$factoryDir/file.mp3";

		// Run FluidSynth and LAME
		$command = self::boxedCommand()
			->routeName( 'score-fluidsynth' )
			->params(
				$wgScoreShell,
				'scripts/synth.sh'
			)
			->environment( [
				'SCORE_FLUIDSYNTH' => $wgScoreFluidsynth,
				'SCORE_SOUNDFONT' => $wgScoreSoundfont,
				'SCORE_LAME' => $wgScoreLame,
				'SCORE_PHP' => $wgPhpCli
			] + $wgScoreEnvironment )
			->inputFileFromFile( 'file.midi', $sourceFile )
			->outputFileToFile( 'file.mp3', $factoryFile )
			->includeStderr()
			// 150 MB max. filesize (for large MIDIs)
			->fileSizeLimit( 150 * 1024 * 1024 );

		self::addScript( $command, 'synth.sh' );
		self::addScript( $command, 'getWavDuration.php' );

		$result = $command->execute();
		self::recordShellout( 'generate_audio' );

		if ( ( $result->getExitCode() != 0 ) || !$result->wasReceived( 'file.mp3' ) ) {
			self::throwSynthException( $result->getStdout() );
		}

		// Move file to the final destination
		$backend = self::getBackend();
		$status = $backend->doQuickOperation( [
			'op' => 'store',
			'src' => $factoryFile,
			'dst' => $remoteDest
		] );

		if ( !$status->isOK() ) {
			throw new ScoreBackendException( $status );
		}

		// Create metadata json
		$metaData[basename( $remoteDest )]['length'] = self::getDurationFromScriptOutput(
			$result->getStdout() );
		$dstFileName = "{$options['file_name_prefix']}.json";
		$dest = "{$options['dest_storage_path']}/$dstFileName";

		// Store metadata in backend
		$backend = self::getBackend();
		$status = $backend->doQuickOperation( [
			'op' => 'create',
			'content' => FormatJson::encode( $metaData ),
			'dst' => $dest
		] );

		if ( !$status->isOK() ) {
			throw new ScoreBackendException( $status );
		}
	}

	/**
	 * Get the duration of the audio file from the script stdout
	 *
	 * @param string $stdout The script output
	 * @return float duration in seconds
	 */
	private static function getDurationFromScriptOutput( $stdout ) {
		if ( preg_match( '/^wavDuration: ([0-9.]+)$/m', $stdout, $m ) ) {
			return (float)$m[1];
		} else {
			return 0.0;
		}
	}

	/**
	 * Track how often we do each type of shellout in statsd
	 *
	 * @param string $type Type of shellout
	 */
	private static function recordShellout( $type ) {
		$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$statsd->increment( "score.$type" );
	}

	/**
	 * Track how often each error occurs in statsd
	 *
	 * @param ScoreException $ex
	 */
	private static function recordError( ScoreException $ex ) {
		$key = $ex->getStatsdKey();
		if ( $key === false ) {
			return;
		}
		$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$statsd->increment( "score_error.$key" );
	}

	/**
	 * Deletes a local directory with no subdirectories with all files in it.
	 *
	 * @param string $dir Local path to the directory that is to be deleted.
	 *
	 * @return bool true on success, false on error
	 */
	private static function eraseDirectory( $dir ) {
		if ( file_exists( $dir ) ) {
			// @phan-suppress-next-line PhanPluginUseReturnValueInternalKnown
			array_map( 'unlink', glob( "$dir/*", GLOB_NOSORT ) );
			$rc = rmdir( $dir );
			if ( !$rc ) {
				self::debug( "Unable to remove directory $dir\n." );
			}
			return $rc;
		}

		/* Nothing to do */
		return true;
	}

	/**
	 * Writes the specified message to the Score debug log.
	 *
	 * @param string $msg message to log.
	 */
	private static function debug( $msg ) {
		wfDebugLog( 'Score', $msg );
	}

}
