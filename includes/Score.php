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

use MediaWiki\Shell\Shell;

/**
 * Score class.
 */
class Score {
	/**
	 * Default audio player width.
	 */
	const DEFAULT_PLAYER_WIDTH = 300;

	/**
	 * Version for cache invalidation.
	 */
	const CACHE_VERSION = 0;

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
			$message->rawParams(
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
		global $wgScoreLilyPond;

		if ( !is_executable( $wgScoreLilyPond ) ) {
			throw new ScoreException( wfMessage( 'score-notexecutable', $wgScoreLilyPond ) );
		}

		$result = Shell::command( $wgScoreLilyPond, '--version' )
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
	 * Creates the specified local directory if it does not exist yet.
	 * Otherwise does nothing.
	 *
	 * @param string $path Local path to directory to be created.
	 * @param int $mode Chmod value of the new directory.
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
	private static function getBackend() {
		global $wgScoreFileBackend;

		if ( $wgScoreFileBackend ) {
			return FileBackendGroup::singleton()->get( $wgScoreFileBackend );
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
	 * Renders the score code (LilyPond, ABC, etc.) in a <score>…</score> tag.
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
		global $wgTmpDirectory;

		try {
			$baseUrl = self::getBaseUrl();
			$baseStoragePath = self::getBackend()->getRootStoragePath() . '/score-render';

			$options = []; // options to self::generateHTML()

			/* temporary working directory to use */
			$fuzz = md5( mt_rand() );
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

			/* Override MIDI file? */
			if ( array_key_exists( 'override_midi', $args ) ) {
				$file = wfFindFile( $args['override_midi'] );
				if ( $file === false ) {
					throw new ScoreException( wfMessage( 'score-midioverridenotfound',
						htmlspecialchars( $args['override_midi'] ) ) );
				}
				$parser->getOutput()->addImage( $file->getName() );
				$options['override_midi'] = true;
				$options['midi_file'] = $file;
				/* Set OGG stuff in case Vorbis rendering is requested */
				$sha1 = $file->getSha1();
				$oggRelDir = "override-midi/{$sha1[0]}/{$sha1[1]}";
				$oggRel = "$oggRelDir/$sha1.ogg";
				$options['audio_storage_dir'] = "$baseStoragePath/$oggRelDir";
				$options['audio_storage_path'] = "$baseStoragePath/$oggRel";
				$options['audio_url'] = "$baseUrl/$oggRel";
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
			if ( !in_array( $options['note-language'], array_keys( self::$supportedNoteLanguages ) ) ) {
				throw new ScoreException(
					wfMessage( 'score-invalidnotelanguage' )->plaintextParams(
						$options['note-language'],
						join( ', ', array_keys( self::$supportedNoteLanguages ) )
					)
				);
			}

			/* Override audio file? */
			if ( array_key_exists( 'override_audio', $args )
				|| array_key_exists( 'override_ogg', $args ) ) {
				$overrideAudio = isset( $args['override_ogg'] )
					? $args['override_ogg']
					: $args['override_audio'];
				$t = Title::newFromText( $overrideAudio, NS_FILE );
				if ( is_null( $t ) ) {
					throw new ScoreException( wfMessage( 'score-invalidaudiooverride',
						htmlspecialchars( $overrideAudio ) ) );
				}
				if ( !$t->isKnown() ) {
					throw new ScoreException( wfMessage( 'score-audiooverridenotfound',
						htmlspecialchars( $overrideAudio ) ) );
				}
				$options['override_audio'] = true;
				$options['audio_name'] = $overrideAudio;
			} else {
				$options['override_audio'] = false;
			}

			/* Audio rendering? */
			$options['generate_ogg'] = array_key_exists( 'sound', $args )
				|| array_key_exists( 'vorbis', $args );

			if ( $options['generate_ogg']
				&& !class_exists( 'TimedMediaTransformOutput' )
			) {
				throw new ScoreException( wfMessage( 'score-nomediahandler' ) );
			}
			if ( $options['generate_ogg'] && ( $options['override_audio'] !== false ) ) {
				throw new ScoreException( wfMessage( 'score-vorbisoverrideaudio' ) );
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
			$parser->addTrackingCategory( 'score-error-category' );
			$html = "$e";
		}

		// Mark the page as using the score extension, it makes easier
		// to track all those pages.
		$scoreNum = $parser->getOutput()->getProperty( 'score' );
		$parser->getOutput()->setProperty( 'score', $scoreNum += 1 );

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
	 * 	- generate_ogg: bool Whether to create an Ogg/Vorbis file in
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
	 * 	- audio_storage_dir: If override_midi and generate_ogg are true, the
	 * 		backend directory in which the audio file is to be stored.
	 * 	- audio_storage_path: string If override_midi and generate_ogg are true,
	 * 		the backend path at which the generated audio file is to be
	 * 		stored.
	 * 	- audio_url: string If override_midi and generate_ogg is true,
	 * 		the URL corresponding to audio_storage_path
	 * 	- override_audio: bool Whether to generate a wikilink to a
	 * 		user-provided audio file. If set to true, the vorbis
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
	private static function generateHTML( &$parser, $code, $options ) {
		try {
			$parser->getOutput()->addModules( 'ext.score.popup' );

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
				|| !isset( $existingFiles[$midiFileName] ) ) {
				$existingFiles += self::generatePngAndMidi( $code, $options, $metaData );
			}

			/* Generate Ogg/Vorbis file if necessary */
			if ( $options['generate_ogg'] ) {
				if ( $options['override_midi'] ) {
					$oggUrl = $options['audio_url'];
					$oggPath = $options['audio_storage_path'];
					$exists = $backend->fileExists( [ 'src' => $options['audio_storage_path'] ] );
					if ( !$exists ) {
						$backend->prepare( [ 'dir' => $options['audio_storage_dir'] ] );
						$sourcePath = $options['midi_file']->getLocalRefPath();
						self::generateOgg( $sourcePath, $options, $oggPath, $metaData );
					}
				} else {
					$oggFileName = "{$options['file_name_prefix']}.ogg";
					$oggUrl = "{$options['dest_url']}/$oggFileName";
					$oggPath = "{$options['dest_storage_path']}/$oggFileName";
					if (
						!isset( $existingFiles[$oggFileName] ) ||
						!isset( $metaData[$oggFileName]['length'] )
					) {
						// Maybe we just generated it
						$sourcePath = "{$options['factory_directory']}/file.midi";
						if ( !file_exists( $sourcePath ) ) {
							// No, need to fetch it from the backend
							$sourceFileRef = $backend->getLocalReference(
								[ 'src' => "{$options['dest_storage_path']}/$midiFileName" ] );
							$sourcePath = $sourceFileRef->getPath();
						}
						self::generateOgg( $sourcePath, $options, $oggPath, $metaData );
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
						'title' => $pageNumb
					] );
				}
			} else {
				/* No images; this may happen in raw mode or when the user omits the score code */
				throw new ScoreException( wfMessage( 'score-noimages' ) );
			}
			if ( $options['generate_ogg'] ) {
				$length = $metaData[basename( $oggPath )]['length'];
				$player = new TimedMediaTransformOutput( [
					'length' => $length,
					'sources' => [
						[
							'src' => $oggUrl,
							'type' => 'audio/ogg; codecs="vorbis"'
						]
					],
					'disablecontrols' => 'options,timedText',
					'width' => self::DEFAULT_PLAYER_WIDTH
				] );
				$link .= $player->toHtml();
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

		if ( isset( $existingFiles["{$options['file_name_prefix']}.ly"] ) ) {
			$attributes['data-source'] = "{$options['dest_url']}/{$options['file_name_prefix']}.ly";
		}

		// Wrap score in div container.
		$link = HTML::rawElement( 'div', $attributes, $link );

		return $link;
	}

	/**
	 * Generates score PNG file(s) and a MIDI file. Stores lilypond file.
	 *
	 * @param string $code Score code.
	 * @param array $options Rendering options. They are the same as for
	 * 	Score::generateHTML().
	 * @param array $metaData array to hold information about images
	 *
	 * @return array of file names placed in the remote dest dir, with the
	 * 	file names in each key.
	 *
	 * @throws ScoreException on error.
	 */
	private static function generatePngAndMidi( $code, $options, &$metaData ) {
		global $wgScoreLilyPond, $wgScoreTrim, $wgScoreSafeMode;

		if ( !is_executable( $wgScoreLilyPond ) ) {
			throw new ScoreException( wfMessage( 'score-notexecutable', $wgScoreLilyPond ) );
		}

		/* Create the working environment */
		$factoryDirectory = $options['factory_directory'];
		self::createDirectory( $factoryDirectory, 0700 );
		$factoryLy = "$factoryDirectory/file.ly";
		$factoryMidi = "$factoryDirectory/file.midi";
		$factoryImage = "$factoryDirectory/file.png";
		$factoryImageTrimmed = "$factoryDirectory/file-trimmed.png";

		/* Generate LilyPond input file */
		if ( $options['lang'] == 'lilypond' ) {
			if ( $options['raw'] ) {
				$lilypondCode = $code;
			} else {
				$lilypondCode = self::embedLilypondCode( $code, $options['note-language'] );
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

		$result = Shell::command(
			$wgScoreLilyPond,
			'-dmidi-extension=midi', // midi needed for Windows to generate the file
			$mode,
			'--png',
			'--header=texidoc',
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
			$output = $result->getStdout() . "\nexited with status: " . $result->getExitCode();
			self::throwCallException( wfMessage( 'score-compilererr' ), $output,
				$options['factory_directory'] );
		}
		$needMidi = false;
		if ( !$options['raw'] || $options['generate_ogg'] && !$options['override_midi'] ) {
			$needMidi = true;
			if ( !file_exists( $factoryMidi ) ) {
				throw new ScoreException( wfMessage( 'score-nomidi' ) );
			}
		}

		/* trim output images if wanted */
		if ( $wgScoreTrim ) {
			if ( file_exists( $factoryImage ) ) {
				self::trimImage( $factoryImage, $factoryImageTrimmed );
			} else {
				for ( $i = 1; ; ++$i ) {
					$src = "$factoryDirectory/file-page$i.png";
					if ( !file_exists( $src ) ) {
						break;
					}
					$dest = "$factoryDirectory/file-page$i-trimmed.png";
					self::trimImage( $src, $dest );
				}
			}
		}

		// Create the destination directory if it doesn't exist
		$backend = self::getBackend();
		$status = $backend->prepare( [ 'dir' => $options['dest_storage_path'] ] );
		if ( !$status->isOK() ) {
			throw new ScoreException( wfMessage( 'score-backend-error', $status->getWikiText() ) );
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
				throw new ScoreException( wfMessage( 'score-backend-error', $status->getWikiText() ) );
			}
		}

		// Add the PNGs
		if ( $wgScoreTrim ) {
			$src = $factoryImageTrimmed;
		} else {
			$src = $factoryImage;
		}
		if ( file_exists( $src ) ) {
			$dstFileName = "{$options['file_name_prefix']}.png";
			$ops[] = [
				'op' => 'store',
				'src' => $src,
				'dst' => "{$options['dest_storage_path']}/$dstFileName" ];

			list( $width, $height ) = self::imageSize( $src );
			$metaData[$dstFileName]['size'] = [ $width, $height ];
			$newFiles[$dstFileName] = true;
		} else {
			for ( $i = 1; ; ++$i ) {
				if ( $wgScoreTrim ) {
					$src = "$factoryDirectory/file-page$i-trimmed.png";
				} else {
					$src = "$factoryDirectory/file-page$i.png";
				}
				if ( !file_exists( $src ) ) {
					break;
				}
				$dstFileName = "{$options['file_name_prefix']}-page$i.png";
				$dest = "{$options['dest_storage_path']}/$dstFileName";
				$ops[] = [
					'op' => 'store',
					'src' => $src,
					'dst' => $dest ];

				list( $width, $height ) = self::imageSize( $src );
				$metaData[$dstFileName]['size'] = [ $width, $height ];
				$newFiles[$dstFileName] = true;
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
			throw new ScoreException( wfMessage( 'score-backend-error', $status->getWikiText() ) );
		}
		return $newFiles;
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
	 * Embeds simple LilyPond code in a score block.
	 *
	 * @param string $lilypondCode Simple LilyPond code.
	 * @param string $noteLanguage Language of notes.
	 *
	 * @return string Raw lilypond code.
	 *
	 * @throws ScoreException if determining the LilyPond version fails.
	 */
	private static function embedLilypondCode( $lilypondCode, $noteLanguage ) {
		$version = self::getLilypondVersion();

		// Check if parameters have already been supplied (hybrid-raw mode)
		$options = "";
		if ( !strpos( $lilypondCode, "\\layout" ) ) {
			$options .= "\\layout { }\n";
		}
		if ( !strpos( $lilypondCode, "\\midi" ) ) {
			$options .= <<<LY
				\\midi {
					\\context { \Score tempoWholesPerMinute = #(ly:make-moment 100 4) }
				}\n
LY;
		}

		/* Raw code. In Scheme, ##f is false and ##t is true. */
		/* Set the default MIDI tempo to 100, 60 is a bit too slow */
		$raw = <<<LILYPOND
\\header {
	tagline = ##f
}
\\paper {
	raggedright = ##t
	raggedbottom = ##t
	indent = 0\mm
}
\\version "$version"
\\language "$noteLanguage"
\\score {
	$lilypondCode
	$options
}
LILYPOND;

		return $raw;
	}

	/**
	 * Generates an Ogg/Vorbis file from a MIDI file using timidity.
	 *
	 * @param string $sourceFile The local filename of the MIDI file
	 * @param array $options array of rendering options.
	 * @param string $remoteDest The backend storage path to upload the Ogg file to
	 * @param array $metaData Array with metadata information
	 *
	 * @throws ScoreException if an error occurs.
	 */
	private static function generateOgg( $sourceFile, $options, $remoteDest, &$metaData ) {
		global $wgScoreTimidity;

		if ( !is_executable( $wgScoreTimidity ) ) {
			throw new ScoreException( wfMessage( 'score-timiditynotexecutable', $wgScoreTimidity ) );
		}

		/* Working environment */
		$factoryDir = $options['factory_directory'];
		self::createDirectory( $factoryDir, 0700 );
		$factoryOgg = "$factoryDir/file.ogg";

		/* Run timidity */
		$result = Shell::command(
			$wgScoreTimidity,
			'-Ov', // Vorbis output
			'--output-file=' . $factoryOgg,
			$sourceFile
		)
			->includeStderr()
			->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
			->execute();

		if ( ( $result->getExitCode() != 0 ) || !file_exists( $factoryOgg ) ) {
			self::throwCallException(
				wfMessage( 'score-oggconversionerr' ), $result->getStdout(), $factoryDir
			);
		}
		$ops = [];
		// Move resultant file to proper place
		$ops[] = [
			'op' => 'store',
			'src' => $factoryOgg,
			'dst' => $remoteDest ];

		// Create metadata json
		$metaData[basename( $remoteDest )]['length'] = self::getLength( $factoryOgg );
		$dstFileName = "{$options['file_name_prefix']}.json";
		$dest = "{$options['dest_storage_path']}/$dstFileName";
		$ops[] = [
			'op' => 'create',
			'content' => FormatJson::encode( $metaData ),
			'dst' => $dest ];

		// Execute the batch
		$backend = self::getBackend();
		$status = $backend->doQuickOperations( $ops );
		if ( !$status->isOK() ) {
			throw new ScoreException( wfMessage( 'score-backend-error', $status->getWikiText() ) );
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
		global $wgScoreAbc2Ly;

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
		$result = Shell::command(
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
	 * get length of ogg vorbis file
	 *
	 * @param string $path file system path to file
	 *
	 * @return float duration in seconds
	 */
	private static function getLength( $path ) {
		$f = new File_Ogg( $path );
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

		$result = Shell::command(
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
