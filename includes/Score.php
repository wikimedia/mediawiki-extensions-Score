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

use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Command;
use MediaWiki\Shell\Shell;

/**
 * Score class.
 */
class Score {
	/**
	 * Default audio player width.
	 */
	private const DEFAULT_PLAYER_WIDTH = 300;

	/**
	 * Version for cache invalidation.
	 */
	private const CACHE_VERSION = 1;

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
	 * @param Message $message Message to display.
	 * @param string $output collected output from stderr.
	 * @param string|bool $factoryDir The factory directory to replace with "..."
	 *
	 * @throws ScoreException always.
	 */
	private static function throwCallException( $message, $output, $factoryDir = false ) {
		/* clean up the output a bit */
		if ( $factoryDir ) {
			$output = str_replace( $factoryDir, '...', $output );
		}
		throw new ScoreException(
			$message->params(
				Html::rawElement( 'pre',
					// Error messages from LilyPond & abc2ly are always English
					[ 'lang' => 'en', 'dir' => 'ltr' ],
					htmlspecialchars( $output )
				)
			)
		);
	}

	/**
	 * @return string
	 * @throws ScoreException if LilyPond could not be executed properly.
	 */
	public static function getLilypondVersion() {
		if ( self::$lilypondVersion === null ) {
			self::fetchLilypondVersion();
		}

		return self::$lilypondVersion;
	}

	/**
	 * Determines the version of LilyPond in use and writes the version
	 * string to self::$lilypondVersion.
	 *
	 * @throws ScoreException if LilyPond could not be executed properly.
	 */
	private static function fetchLilypondVersion() {
		global $wgScoreLilyPond, $wgScoreLilyPondFakeVersion;

		if ( strlen( $wgScoreLilyPondFakeVersion ) ) {
			self::$lilypondVersion = $wgScoreLilyPondFakeVersion;
			return;
		}
		if ( !is_executable( $wgScoreLilyPond ) ) {
			throw new ScoreException( wfMessage( 'score-notexecutable', $wgScoreLilyPond ) );
		}

		$result = self::command( $wgScoreLilyPond, '--version' )
			->includeStderr()
			->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
			->execute();
		$output = $result->getStdout();
		if ( $result->getExitCode() != 0 ) {
			self::throwCallException( wfMessage( 'score-versionerr' ), $output );
		}

		$n = sscanf( $output, 'GNU LilyPond %s', self::$lilypondVersion );
		if ( $n != 1 ) {
			self::$lilypondVersion = null;
			self::throwCallException( wfMessage( 'score-versionerr' ), $output );
		}
	}

	/**
	 * Return a Command object, or throw an exception if shell execution is
	 * disabled.
	 *
	 * The check for $wgScoreDisableExec should be redundant with checks in the
	 * callers, since the callers generally need to avoid writing input files.
	 * We check twice to be safe.
	 *
	 * @param string|string[] ...$params String or array of strings representing the command to
	 *   be executed, each value will be escaped.
	 * @return Command
	 * @throws ScoreDisabledException
	 */
	private static function command( ...$params ) {
		global $wgScoreDisableExec;

		if ( $wgScoreDisableExec ) {
			throw new ScoreDisabledException( wfMessage( 'score-exec-disabled' ) );
		}

		return Shell::command( ...$params );
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
				throw new ScoreException( wfMessage( 'score-nooutput', $path ) );
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
		} else {
			return $wgScorePath;
		}
	}

	/**
	 * @return FileBackend
	 */
	public static function getBackend() {
		global $wgScoreFileBackend;

		if ( $wgScoreFileBackend ) {
			return MediaWikiServices::getInstance()->getFileBackendGroup()
				->get( $wgScoreFileBackend );
		} else {
			if ( !self::$backend ) {
				global $wgScoreDirectory, $wgUploadDirectory;
				if ( $wgScoreDirectory === false ) {
					$dir = "{$wgUploadDirectory}/lilypond";
				} else {
					$dir = $wgScoreDirectory;
				}
				self::$backend = new FSFileBackend( [
					'name'           => 'score-backend',
					'wikiId'         => wfWikiID(),
					'lockManager'    => new NullLockManager( [] ),
					'containerPaths' => [ 'score-render' => $dir ],
					'fileMode'       => 0777,
					'obResetFunc' => 'wfResetOutputBuffers',
					'streamMimeFunc' => [ 'StreamFile', 'contentTypeFromPath' ],
					'statusWrapper' => [ 'Status', 'wrap' ],
				] );
			}
			return self::$backend;
		}
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
		global $wgTmpDirectory, $wgScoreLame;

		try {
			$baseUrl = self::getBaseUrl();
			$baseStoragePath = self::getBackend()->getRootStoragePath() . '/score-render';

			$options = []; // options to self::generateHTML()

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
				throw new ScoreException( wfMessage( 'score-invalidlang',
					htmlspecialchars( $options['lang'] ) ) );
			}

			// Set extension for audio output
			$options['audio_extension'] = is_executable( $wgScoreLame ) ? 'mp3' : 'ogg';

			/* Override MIDI file? */
			if ( array_key_exists( 'override_midi', $args ) ) {
				$file = MediaWikiServices::getInstance()->getRepoGroup()
					->findFile( $args['override_midi'] );
				if ( $file === false ) {
					throw new ScoreException( wfMessage( 'score-midioverridenotfound',
						htmlspecialchars( $args['override_midi'] ) ) );
				}
				if ( $parser->getOutput() !== null ) {
					$parser->getOutput()->addImage( $file->getName() );
				}

				$options['override_midi'] = true;
				$options['midi_file'] = $file;
				/* Set output stuff in case audio rendering is requested */
				$sha1 = $file->getSha1();
				$audioRelDir = "override-midi/{$sha1[0]}/{$sha1[1]}";
				$audioRel = "$audioRelDir/$sha1.{$options['audio_extension']}";
				$options['audio_storage_dir'] = "$baseStoragePath/$audioRelDir";
				$options['audio_storage_path'] = "$baseStoragePath/$audioRel";
				$options['audio_url'] = "$baseUrl/$audioRel";
				$options['audio_sha_name'] = "$sha1.{$options['audio_extension']}";
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
					throw new ScoreException( wfMessage( 'score-notelanguagewithraw' ) );
				}
			} else {
				$options['note-language'] = self::$defaultNoteLanguage;
			}
			if ( !isset( self::$supportedNoteLanguages[$options['note-language']] ) ) {
				throw new ScoreException(
					wfMessage( 'score-invalidnotelanguage' )->plaintextParams(
						$options['note-language'],
						implode( ', ', array_keys( self::$supportedNoteLanguages ) )
					)
				);
			}

			/* Override audio file? */
			if ( array_key_exists( 'override_audio', $args )
				|| array_key_exists( 'override_ogg', $args ) ) {
				$overrideAudio = $args['override_ogg'] ?? $args['override_audio'];
				$t = Title::newFromText( $overrideAudio, NS_FILE );
				if ( $t === null ) {
					throw new ScoreException( wfMessage( 'score-invalidaudiooverride',
						htmlspecialchars( $overrideAudio ) ) );
				}
				if ( !$t->isKnown() ) {
					throw new ScoreException( wfMessage( 'score-audiooverridenotfound',
						htmlspecialchars( $overrideAudio ) ) );
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

			if ( $options['generate_audio']
				&& !ExtensionRegistry::getInstance()->isLoaded( 'TimedMediaHandler' )
			) {
				throw new ScoreException( wfMessage( 'score-nomediahandler' ) );
			}
			if ( $options['generate_audio'] && ( $options['override_audio'] !== false ) ) {
				throw new ScoreException( wfMessage( 'score-convertoverrideaudio' ) );
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
			$imageCacheName = Wikimedia\base_convert( sha1( serialize( $cacheOptions ) ), 16, 36, 31 );
			$imagePrefixEnd = "{$imageCacheName[0]}/" .
				"{$imageCacheName[1]}/$imageCacheName";
			$options['dest_storage_path'] = "$baseStoragePath/$imagePrefixEnd";
			$options['dest_url'] = "$baseUrl/$imagePrefixEnd";
			$options['file_name_prefix'] = substr( $imageCacheName, 0, 8 );

			$html = self::generateHTML( $parser, $code, $options );
		} catch ( ScoreException $e ) {
			if ( $parser->getOutput() !== null && $e->isTracked() ) {
				$parser->addTrackingCategory( 'score-error-category' );
			}
			$parser->getOutput()->addModules( 'ext.score.errors' );
			$html = "$e";
		}

		// Mark the page as using the score extension, it makes easier
		// to track all those pages.
		if ( $parser->getOutput() !== null ) {
			$scoreNum = $parser->getOutput()->getProperty( 'score' );
			$parser->getOutput()->setProperty( 'score', $scoreNum += 1 );
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
	 * 	- audio_extension: string If override_midi and generate_audio are true,
	 * 		the audio output format in which the audio file is to be generated.
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
		global $wgScoreOfferSourceDownload;

		$link = '';
		try {
			if ( $parser->getOutput() !== null ) {
				$parser->getOutput()->addModules( 'ext.score.popup' );
			}

			$backend = self::getBackend();
			$fileIter = $backend->getFileList(
				[ 'dir' => $options['dest_storage_path'], 'topOnly' => true ] );
			$existingFiles = [];
			foreach ( $fileIter as $file ) {
				$existingFiles[$file] = true;
			}

			/* Generate PNG and MIDI files if necessary */
			$imageFileName = "{$options['file_name_prefix']}.png";
			$multi1FileName = "{$options['file_name_prefix']}-page1.png";
			$midiFileName = "{$options['file_name_prefix']}.midi";
			$metaDataFileName = "{$options['file_name_prefix']}.json";
			$audioFileName = '';
			$audioUrl = '';

			if ( isset( $existingFiles[$metaDataFileName] ) ) {
				$metaDataFile = $backend->getFileContents(
					[ 'src' => "{$options['dest_storage_path']}/$metaDataFileName" ] );
				if ( $metaDataFile === false ) {
					throw new ScoreException( wfMessage( 'score-nocontent', $metaDataFileName ) );
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
					!isset( $metaData[$imageFileName]['size'] )
					&& !isset( $metaData[$multi1FileName]['size'] )
				)
				|| !isset( $existingFiles[$midiFileName] ) ) {
				$existingFiles += self::generatePngAndMidi( $code, $options, $metaData );
			}

			/* Generate audio file if necessary */
			if ( $options['generate_audio'] ) {
				$audioFileName = "{$options['file_name_prefix']}.{$options['audio_extension']}";
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
				$link = Html::rawElement( 'img', [
					'src' => "{$options['dest_url']}/$imageFileName",
					'width' => $width,
					'height' => $height,
					'alt' => $code,
				] );
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
					$link .= Html::rawElement( 'img', [
						'src' => "{$options['dest_url']}/$fileName",
						'width' => $width,
						'height' => $height,
						'alt' => $pageNumb,
						'title' => $pageNumb,
						'style' => "margin-bottom:1em"
					] );
				}
			}
			if ( $options['generate_audio'] ) {
				$audioHash = $options['override_midi'] ? $options['audio_sha_name'] : $audioFileName;
				$length = $metaData[$audioHash]['length'];
				$mimetype = pathinfo( $audioUrl, PATHINFO_EXTENSION ) === 'mp3'
					? 'audio/mpeg'
					: 'application/ogg'; // TMH needs application/ogg
				$player = new TimedMediaTransformOutput( [
					'length' => $length,
					'sources' => [
						[
							'src' => $audioUrl,
							'type' => $mimetype
						]
					],
					'disablecontrols' => 'options,timedText',
					'width' => self::DEFAULT_PLAYER_WIDTH
				] );
				$link .= $player->toHtml();

				// This is a hack for T148716 to load the TMH frontend
				// which we're sort of side-using here. In the future,
				// we should use a clean standard interface for this.
				$tmh = new TimedMediaHandler();
				if ( method_exists( $tmh, 'parserTransformHook' ) ) {
					$tmh->parserTransformHook( $parser, null );
				}
			}
			if ( $options['override_audio'] !== false ) {
				$link .= $parser->recursiveTagParse( "[[File:{$options['audio_name']}]]" );
			}
		} catch ( Exception $e ) {
			self::eraseFactory( $options['factory_directory'] );
			throw $e;
		}

		self::eraseFactory( $options['factory_directory'] );

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
			$wgScoreGhostscript;

		if ( $wgScoreDisableExec ) {
			throw new ScoreDisabledException( wfMessage( 'score-exec-disabled' ) );
		}

		if ( !is_executable( $wgScoreLilyPond ) ) {
			throw new ScoreException( wfMessage( 'score-notexecutable', $wgScoreLilyPond ) );
		}

		/* Create the working environment */
		$factoryDirectory = $options['factory_directory'];
		self::createDirectory( $factoryDirectory, 0700 );
		$factoryLy = "$factoryDirectory/file.ly";
		$factoryPs = "$factoryDirectory/file.ps";
		$factoryMidi = "$factoryDirectory/file.midi";
		$factoryImagePattern = "$factoryDirectory/file-page%d.png";

		/* Generate LilyPond input file */
		if ( $options['lang'] == 'lilypond' ) {
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
			$rc = file_put_contents( $factoryLy, $lilypondCode );
			if ( $rc === false ) {
				throw new ScoreException( wfMessage( 'score-noinput', $factoryLy ) );
			}
		} else {
			$options['lilypond_path'] = $factoryLy;
			self::generateLilypond( $code, $options );
		}

		/* generate lilypond output files in working environment */
		$oldcwd = getcwd();
		if ( $oldcwd === false ) {
			throw new ScoreException( wfMessage( 'score-getcwderr' ) );
		}
		$rc = chdir( $factoryDirectory );
		if ( !$rc ) {
			throw new ScoreException( wfMessage( 'score-chdirerr', $factoryDirectory ) );
		}

		// Reduce the GC yield to 25% since testing indicates that this will
		// reduce memory usage by a factor of 3 or so with minimal impact on
		// CPU time. Tested with http://www.mutopiaproject.org/cgibin/piece-info.cgi?id=108
		// Note that if Lilypond is compiled against Guile 2.0+, this
		// probably won't do anything.
		$env = [ 'LILYPOND_GC_YIELD' => '25' ];
		$mode = $wgScoreSafeMode ? '-dsafe' : null;

		$result = self::command(
			$wgScoreLilyPond,
			'-dmidi-extension=midi', // midi needed for Windows to generate the file
			$mode,
			'--ps',
			'--header=texidoc',
			'--loglevel=ERROR',
			$factoryLy
		)
			->includeStderr()
			->environment( $env )
			->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
			->execute();
		$rc = chdir( $oldcwd );
		if ( !$rc ) {
			throw new ScoreException( wfMessage( 'score-chdirerr', $oldcwd ) );
		}
		if ( $result->getExitCode() != 0 ) {
			// when input is not raw, we build the final lilypond file content
			// in self::embedLilypondCode. The user input then is not inserted
			// on the first line in the file we pass to lilypond and so we need
			// to offset error messages back.
			$scoreFirstLineOffset = $options['raw'] ? 0 : 7;
			$errMsgBeautifier = new LilypondErrorMessageBeautifier( $scoreFirstLineOffset );

			$beautifiedMessage = $errMsgBeautifier->beautifyMessage( $result->getStdout() );

			self::throwCallException(
				wfMessage( 'score-compilererr' ),
				$beautifiedMessage,
				$options['factory_directory']
			);
		}

		if ( !file_exists( $factoryPs ) ) {
			throw new ScoreException( wfMessage( 'score-nops' ) );
		}

		// Extract the page size in points from the PS header
		$pageSize = self::extractPostScriptPageSize( $factoryPs );

		$result = self::command(
			$wgScoreGhostscript,
			'-q',
			'-dGraphicsAlphaBits=4',
			'-dTextAlphaBits=4',
			"-dDEVICEWIDTHPOINTS={$pageSize['width']}",
			"-dDEVICEHEIGHTPOINTS={$pageSize['height']}",
			'-dNOPAUSE',
			'-dSAFER',
			'-sDEVICE=png16m',
			"-sOutputFile=$factoryImagePattern",
			// Match LilyPond's default resolution of 101 DPI
			'-r101',
			$factoryPs,
			'-c', 'quit'
		)
			->includeStderr()
			->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
			->execute();

		if ( $result->getExitCode() != 0 ) {
			self::throwCallException(
				wfMessage( 'score-gs-error' ),
				$result->getStdout(),
				$options['factory_directory']
			);
		}

		$numPages = 0;
		for ( $i = 1; ; $i++ ) {
			if ( !file_exists( "$factoryDirectory/file-page$i.png" ) ) {
				$numPages = $i - 1;
				break;
			}
		}

		// @phan-file-suppress PhanRedundantCondition
		if ( !$numPages ) {
			throw new ScoreException( wfMessage( 'score-noimages' ) );
		}

		$needMidi = false;
		if ( !$options['raw'] || $options['generate_audio'] && !$options['override_midi'] ) {
			$needMidi = true;
			if ( !file_exists( $factoryMidi ) ) {
				throw new ScoreException( wfMessage( 'score-nomidi' ) );
			}
		}

		/* trim output images if wanted */
		if ( $wgScoreTrim ) {
			for ( $i = 1; $i <= $numPages; ++$i ) {
				$src = "$factoryDirectory/file-page$i.png";
				$dest = "$factoryDirectory/file-page$i-trimmed.png";
				self::trimImage( $src, $dest );
			}
		}

		// Create the destination directory if it doesn't exist
		$backend = self::getBackend();
		$status = $backend->prepare( [ 'dir' => $options['dest_storage_path'] ] );
		if ( !$status->isOK() ) {
			throw new ScoreException(
				wfMessage( 'score-backend-error', Status::wrap( $status )->getWikitext() )
			);
		}

		// File names of generated files
		$newFiles = [];
		// Backend operation batch
		$ops = [];

		// Add LY source to its file
		$ops[] = [
			'op' => 'store',
			'src' => $factoryLy,
			'dst' => "{$options['dest_storage_path']}/{$options['file_name_prefix']}.ly"
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
				throw new ScoreException(
					wfMessage( 'score-backend-error', Status::wrap( $status )->getWikitext() )
				);
			}
		}

		// Add the PNGs
		for ( $i = 1; $i <= $numPages; ++$i ) {
			if ( $wgScoreTrim ) {
				$src = "$factoryDirectory/file-page$i-trimmed.png";
			} else {
				$src = "$factoryDirectory/file-page$i.png";
			}
			if ( $numPages === 1 ) {
				$dstFileName = "{$options['file_name_prefix']}.png";
			} else {
				$dstFileName = "{$options['file_name_prefix']}-page$i.png";
			}
			$dest = "{$options['dest_storage_path']}/$dstFileName";
			$ops[] = [
				'op' => 'store',
				'src' => $src,
				'dst' => $dest ];

			list( $width, $height ) = self::imageSize( $src );
			$metaData[$dstFileName]['size'] = [ $width, $height ];
			$newFiles[$dstFileName] = true;
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
			throw new ScoreException(
				wfMessage( 'score-backend-error', Status::wrap( $status )->getWikitext() )
			);
		}
		return $newFiles;
	}

	/**
	 * Get the page size from the header of a PostScript file
	 *
	 * @param string $fileName
	 * @return array
	 */
	private static function extractPostScriptPageSize( $fileName ) {
		$f = fopen( $fileName, 'r' );
		if ( !$f ) {
			throw new ScoreException( wfMessage( 'score-readerr', basename( $fileName ) ) );
		}
		while ( !feof( $f ) ) {
			$line = fgets( $f );
			if ( $line === false ) {
				throw new ScoreException( wfMessage( 'score-readerr', basename( $fileName ) ) );
			}
			if ( preg_match( '/^%%DocumentMedia: [^ ]* ([\d.]+) ([\d.]+)/', $line, $m ) ) {
				return [
					'width' => $m[1],
					'height' => $m[2]
				];
			}
		}
		throw new ScoreException( wfMessage( 'score-readerr', basename( $fileName ) ) );
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
		global $wgScoreFluidsynth, $wgScoreSoundfont, $wgScoreLame, $wgScoreDisableExec;
		global $wgScoreTimidity; // TODO: Remove TiMidity++ as fallback

		if ( $wgScoreDisableExec ) {
			throw new ScoreDisabledException( wfMessage( 'score-exec-disabled' ) );
		}

		// Check whether the output is mp3 or ogg by extension
		$extension = pathinfo( $remoteDest, PATHINFO_EXTENSION );
		$isOutputMp3 = $extension === 'mp3';

		/* Working environment */
		$factoryDir = $options['factory_directory'];
		self::createDirectory( $factoryDir, 0700 );
		$factoryOutput = "$factoryDir/output.wav";
		$factoryFile = "$factoryDir/file.$extension";

		if ( is_executable( $wgScoreFluidsynth ) ) {
			if ( !file_exists( $wgScoreSoundfont ) ) {
				throw new ScoreException( wfMessage( 'score-soundfontnotexists', $wgScoreSoundfont ) );
			}

			// Use fluidsynth
			$cmdArgs = [
				$wgScoreFluidsynth,
				'-T',
				$isOutputMp3 ? 'wav' : 'oga', // wav output if mp3
				'-F',
				$factoryOutput,
				$wgScoreSoundfont,
				$sourceFile
			];
		} elseif ( is_executable( $wgScoreTimidity ) ) {
			// Use TiMidity++ as a fallback
			$cmdArgs = [
				$wgScoreTimidity,
				$isOutputMp3 ? '-Ow' : '-Ov', // wav output if mp3
				'--output-file=' . $factoryOutput,
				$sourceFile
			];
		} else {
			throw new ScoreException( wfMessage( 'score-fallbacknotexecutable', $wgScoreTimidity ) );
		}

		/* Run fluidsynth */
		$result = self::command( $cmdArgs )
			->includeStderr()
			->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
			->limits( [ 'filesize' => 153600 ] ) // 150 MB max. filesize (for large MIDIs)
			->execute();

		if ( ( $result->getExitCode() != 0 ) || !file_exists( $factoryOutput ) ) {
			self::throwCallException(
				wfMessage( 'score-audioconversionerr' ), $result->getStdout(), $factoryDir
			);
		}

		if ( $isOutputMp3 ) {
			if ( !is_executable( $wgScoreLame ) ) {
				throw new ScoreException( wfMessage( 'score-lamenotexecutable', $wgScoreLame ) );
			}

			/* Convert wav -> mp3 using LAME */
			$result = self::command( $wgScoreLame, $factoryOutput, $factoryFile )
				->includeStderr()
				->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
				->execute();

			if ( ( $result->getExitCode() != 0 ) || !file_exists( $factoryFile ) ) {
				self::throwCallException(
					wfMessage( 'score-audioconversionerr' ), $result->getStdout(), $factoryDir
				);
			}
		} else {
			// No conversion required for ogg
			$factoryFile = $factoryOutput;
		}

		// Move file to the final destination
		$backend = self::getBackend();
		$status = $backend->doQuickOperation( [
			'op' => 'store',
			'src' => $factoryFile,
			'dst' => $remoteDest
		] );

		if ( !$status->isOK() ) {
			throw new ScoreException(
				wfMessage( 'score-backend-error', Status::wrap( $status )->getWikitext() )
			);
		}

		// Create metadata json
		$metaData[basename( $remoteDest )]['length'] = self::getLength( $remoteDest );
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
			throw new ScoreException(
				wfMessage( 'score-backend-error', Status::wrap( $status )->getWikitext() )
			);
		}
	}

	/**
	 * Generates LilyPond code.
	 *
	 * @param string $code Score code.
	 * @param array $options Rendering options. They are the same as for
	 * 	Score::generateHTML(), with the following addition:
	 * 	* lilypond_path: local path to the LilyPond file that is to be
	 * 		generated.
	 *
	 * @throws Exception if an error occurs.
	 */
	private static function generateLilypond( $code, $options ) {
		/* Delete old file if necessary */
		self::cleanupFile( $options['lilypond_path'] );

		/* Generate LilyPond code by score language */
		switch ( $options['lang'] ) {
		case 'ABC':
			self::generateLilypondFromAbc(
				$code, $options['factory_directory'], $options['lilypond_path'] );
			break;
		case 'lilypond':
			throw new Exception( 'lang="lilypond" in ' . __METHOD__ . ". " .
				"This should not happen.\n" );
		default:
			throw new Exception( 'Unknown score language in ' . __METHOD__ . ". " .
				"This should not happen.\n" );
		}
	}

	/**
	 * Runs abc2ly, creating the LilyPond input file.
	 *
	 * @param string $code ABC code.
	 * @param string $factoryDirectory Local temporary directory
	 * @param string $destFile Local destination path
	 *
	 * @throws ScoreException if the conversion fails.
	 */
	private static function generateLilypondFromAbc( $code, $factoryDirectory, $destFile ) {
		global $wgScoreAbc2Ly, $wgScoreDisableExec;

		if ( $wgScoreDisableExec ) {
			throw new ScoreDisabledException( wfMessage( 'score-exec-disabled' ) );
		}
		if ( !is_executable( $wgScoreAbc2Ly ) ) {
			throw new ScoreException( wfMessage( 'score-abc2lynotexecutable', $wgScoreAbc2Ly ) );
		}

		/* File names */
		$factoryAbc = "$factoryDirectory/file.abc";

		/* Create ABC input file */
		$rc = file_put_contents( $factoryAbc, $code );
		if ( $rc === false ) {
			throw new ScoreException( wfMessage( 'score-noabcinput', $factoryAbc ) );
		}

		/* Convert to LilyPond file */
		$result = self::command(
			$wgScoreAbc2Ly,
			'-s',
			'-o',
			$destFile,
			$factoryAbc
		)
			->includeStderr()
			->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
			->execute();

		if ( ( $result->getExitCode() != 0 ) || !file_exists( $destFile ) ) {
			self::throwCallException(
				wfMessage( 'score-abcconversionerr' ), $result->getStdout(), $factoryDirectory
			);
		}

		/* The output file has a tagline which should be removed in a wiki context */
		$lyData = file_get_contents( $destFile );
		if ( $lyData === false ) {
			throw new ScoreException( wfMessage( 'score-readerr', $destFile ) );
		}
		$lyData = preg_replace( '/^(\s*tagline\s*=).*/m', '$1 ##f', $lyData );
		if ( $lyData === null ) {
			throw new ScoreException( wfMessage( 'score-pregreplaceerr' ) );
		}
		$rc = file_put_contents( $destFile, $lyData );
		if ( $rc === false ) {
			throw new ScoreException( wfMessage( 'score-noinput', $destFile ) );
		}
	}

	/**
	 * get length of audio file
	 *
	 * @param string $path file system path to file
	 *
	 * @return float duration in seconds
	 */
	private static function getLength( $path ) {
		$isFileMp3 = pathinfo( $path, PATHINFO_EXTENSION ) === 'mp3';
		$repo = new FileRepo( [
			'name' => 'foo',
			'backend' => self::getBackend()
		] );

		$f = new UnregisteredLocalFile( false, $repo, $path, $isFileMp3
			? 'audio/mpeg'
			: 'application/ogg' // Wrong MIME type, but used in TMH
		);

		return $f->getLength();
	}

	/**
	 * Trims an image with ImageMagick.
	 *
	 * @param string $source Local path to the source image.
	 * @param string $dest Local path to the target (trimmed) image.
	 *
	 * @throws ScoreException on error.
	 */
	private static function trimImage( $source, $dest ) {
		global $wgImageMagickConvertCommand;

		$result = self::command(
			$wgImageMagickConvertCommand,
			'-trim',
			'-transparent',
			'white',
			$source,
			$dest
		)
			->includeStderr()
			->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
			->execute();
		if ( $result->getExitCode() != 0 ) {
			self::throwCallException( wfMessage( 'score-trimerr' ), $result->getStdout() );
		}
	}

	/**
	 * Deletes a local directory with no subdirectories with all files in it.
	 *
	 * @param string $dir Local path to the directory that is to be deleted.
	 *
	 * @return bool true on success, false on error
	 */
	private static function eraseFactory( $dir ) {
		if ( file_exists( $dir ) ) {
			array_map( 'unlink', glob( "$dir/*", GLOB_NOSORT ) );
			$rc = rmdir( $dir );
			if ( !$rc ) {
				self::debug( "Unable to remove directory $dir\n." );
			}
			return $rc;

		} else {
			/* Nothing to do */
			return true;
		}
	}

	/**
	 * Deletes a local file if it exists.
	 *
	 * @param string $path Local path to the file to be deleted.
	 *
	 * @throws ScoreException if the file specified by $path exists but
	 * 	could not be deleted.
	 */
	private static function cleanupFile( $path ) {
		if ( file_exists( $path ) ) {
			$rc = unlink( $path );
			if ( !$rc ) {
				throw new ScoreException( wfMessage( 'score-cleanerr' ) );
			}
		}
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
