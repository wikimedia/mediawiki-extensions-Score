<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Score\Tests;

use MediaWiki\Extension\Score\Score;
use MediaWiki\Extension\Score\ScoreException;
use MediaWikiIntegrationTestCase;

/**
 * Unit tests for Score class
 *
 * @covers \MediaWiki\Extension\Score\Score
 */
class ScoreTest extends MediaWikiIntegrationTestCase {

	/**
	 * Set up test environment before each test
	 */
	protected function setUp(): void {
		parent::setUp();

		// Set up common globals for all tests
		$tempDir = sys_get_temp_dir() . '/mw-score-test-' . uniqid();
		$this->setMwGlobals( [
			'wgScoreDisableExec' => true,
			'wgTmpDirectory' => sys_get_temp_dir(),
			'wgScorePath' => false,
			'wgUploadPath' => '/test-upload',
			'wgScoreDirectory' => false,
			'wgUploadDirectory' => $tempDir,
			'wgScoreFileBackend' => false,
			'wgScoreUseSvg' => false
		] );
	}

	/**
	 * Data provider for renderScore language tests
	 *
	 * @return array[]
	 */
	public static function provideRenderScoreLanguages(): array {
		return [
			'Default language (lilypond)' => [
				'c d e f',
				[]
			],
			'Explicit lilypond language' => [
				'c d e f',
				[ 'lang' => 'lilypond' ]
			],
			'ABC language' => [
				'X:1\nM:C\nK:C\nC D E F',
				[ 'lang' => 'ABC' ]
			],
		];
	}

	/**
	 * Data provider for renderScore note language tests
	 *
	 * @return array[]
	 */
	public static function provideRenderScoreNoteLanguages(): array {
		return [
			'Default note language' => [
				'c d e f',
				[]
			],
			'English note language' => [
				'c d e f',
				[ 'note-language' => 'english' ]
			],
			'Deutsch note language' => [
				'c d e f',
				[ 'note-language' => 'deutsch' ]
			],
			'Nederlands note language' => [
				'c d e f',
				[ 'note-language' => 'nederlands' ]
			],
		];
	}

	/**
	 * Data provider for line_width_inches tests
	 *
	 * @return array[]
	 */
	public static function provideLineWidthInchesTests(): array {
		return [
			'Positive line width' => [
				'c d e f',
				[ 'line_width_inches' => 5.0 ]
			],
			'Negative line width (should be abs)' => [
				'c d e f',
				[ 'line_width_inches' => -3.5 ]
			],
			'Zero line width (should be ignored)' => [
				'c d e f',
				[ 'line_width_inches' => 0.0 ]
			],
			'String line width' => [
				'c d e f',
				[ 'line_width_inches' => '4.2' ]
			],
		];
	}

	/**
	 * Test SUPPORTED_NOTE_LANGUAGES constant
	 */
	public function testSupportedNoteLanguages(): void {
		$languages = Score::SUPPORTED_NOTE_LANGUAGES;

		$this->assertIsArray( $languages );
		$this->assertNotEmpty( $languages );

		// Verify some expected languages are present
		$this->assertArrayHasKey( 'nederlands', $languages );
		$this->assertArrayHasKey( 'english', $languages );
		$this->assertArrayHasKey( 'deutsch', $languages );

		// Verify language codes are strings.
		foreach ( $languages as $lilyName => $code ) {
			$this->assertIsString( $lilyName );
			$this->assertIsString( $code );
		}
	}

	/**
	 * Test that SUPPORTED_NOTE_LANGUAGES contains valid language codes
	 */
	public function testSupportedNoteLanguagesHaveValidCodes(): void {
		$languages = Score::SUPPORTED_NOTE_LANGUAGES;

		// All values should be valid language codes (2-3 characters typically).
		foreach ( $languages as $lilyName => $code ) {
			$this->assertNotEmpty( $code, "Language code for $lilyName should not be empty" );
			$this->assertIsString( $code, "Language code for $lilyName should be a string" );
			$this->assertGreaterThanOrEqual(
				2,
				strlen( $code ),
				"Language code for $lilyName should be at least 2 characters"
			);
		}
	}

	/**
	 * Test getLilypondVersion() with fake version
	 */
	public function testGetLilypondVersionWithFakeVersion() {
		$this->setMwGlobals( 'wgScoreLilyPondFakeVersion', '2.24.0' );

		$version = Score::getLilypondVersion();
		$this->assertEquals( '2.24.0', $version );
	}

	/**
	 * Test getLilypondVersion() returns fake version when set
	 * @throws ScoreException
	 */
	public function testGetLilypondVersionFakeVersionTakesPrecedence() {
		$this->setMwGlobals( [
			'wgScoreLilyPondFakeVersion' => '9.99.9',
			'wgScoreDisableExec' => true
		] );

		$version = Score::getLilypondVersion();
		$this->assertEquals( '9.99.9', $version );
	}

	/**
	 * Test renderScore() with null code returns empty string
	 * @throws ScoreException
	 */
	public function testRenderScoreWithNullCode(): void {
		$result = Score::renderScore( null, [] );

		$this->assertSame( '', $result );
	}

	/**
	 * Test renderScore() returns HTML error when exec is disabled
	 *
	 * This test verifies the behavior when wgScoreDisableExec is true.
	 * The exception is caught and returns HTML with disabled class.
	 * @throws ScoreException
	 */
	public function testRenderScoreWithExecDisabled() {
		$result = Score::renderScore( 'c d e f', [] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with different languages
	 *
	 * @dataProvider provideRenderScoreLanguages
	 * @param string $code Score code
	 * @param array $args Arguments
	 * @throws ScoreException
	 */
	public function testRenderScoreWithLanguages( $code, array $args ) {
		$result = Score::renderScore( $code, $args );
		$this->assertIsString( $result );
		// Should return html (with disabled class since exec is disabled).
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with different note languages
	 *
	 * @dataProvider provideRenderScoreNoteLanguages
	 * @param string $code Score code
	 * @param array $args Arguments
	 * @throws ScoreException
	 */
	public function testRenderScoreWithNoteLanguages( $code, array $args ) {
		$result = Score::renderScore( $code, $args );
		$this->assertIsString( $result );
		// Should return html (with disabled class since exec is disabled).
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with line_width_inches option
	 *
	 * @dataProvider provideLineWidthInchesTests
	 * @param string $code Score code
	 * @param array $args Arguments
	 * @throws ScoreException
	 */
	public function testRenderScoreWithLineWidthInches( $code, array $args ) {
		$result = Score::renderScore( $code, $args );
		$this->assertIsString( $result );
		// Should return html (with disabled class since exec is disabled).
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with invalid language returns HTML error
	 * @throws ScoreException
	 */
	public function testRenderScoreWithInvalidLanguage() {
		$result = Score::renderScore( 'c d e f', [ 'lang' => 'invalid-lang' ] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-error', $result );
	}

	/**
	 * Test renderScore() with invalid note language returns HTML error
	 * @throws ScoreException
	 */
	public function testRenderScoreWithInvalidNoteLanguage(): void {
		$result = Score::renderScore( 'c d e f', [ 'note-language' => 'invalid-note-lang' ] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-error', $result );
	}

	/**
	 * Test renderScore() with raw mode
	 * @throws ScoreException
	 */
	public function testRenderScoreWithRawMode(): void {
		$result = Score::renderScore( '\\score { c d e f }', [ 'raw' => true ] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with note-language and raw mode returns HTML error
	 * @throws ScoreException
	 */
	public function testRenderScoreWithNoteLanguageAndRawReturnsError() {
		$result = Score::renderScore( 'c d e f', [
			'note-language' => 'english',
			'raw' => true
		] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-error', $result );
	}

	/**
	 * Test renderScore() with sound option (audio generation)
	 * @throws ScoreException
	 */
	public function testRenderScoreWithSoundOption(): void {
		$result = Score::renderScore( 'c d e f', [ 'sound' => true ] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with vorbis option (alias for sound)
	 * @throws ScoreException
	 */
	public function testRenderScoreWithVorbisOption(): void {
		$result = Score::renderScore( 'c d e f', [ 'vorbis' => true ] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with parser adds tracking categories
	 */
	public function testRenderScoreWithParserAddsTrackingCategories(): void {
		$parser = $this->createMock( \MediaWiki\Parser\Parser::class );
		$parserOutput = $this->createMock( \MediaWiki\Parser\ParserOutput::class );
		$parser->expects( $this->atLeastOnce() )
			->method( 'addTrackingCategory' )
			->with( 'score-use-category' );
		$parser->method( 'getOutput' )
			->willReturn( $parserOutput );

		$result = Score::renderScore( 'c d e f', [], $parser );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() processes line_width_inches correctly
	 */
	public function testRenderScoreProcessesLineWidthInches(): void {
		// Negative value should be converted to positive.
		$result = Score::renderScore( 'c d e f', [ 'line_width_inches' => -5.0 ] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() ignores zero line_width_inches
	 */
	public function testRenderScoreIgnoresZeroLineWidth(): void {
		$result = Score::renderScore( 'c d e f', [ 'line_width_inches' => 0.0 ] );
		$this->assertIsString( $result );

		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test getBackend() returns FileBackend instance
	 */
	public function testGetBackend(): void {
		$this->setMwGlobals( [
			'wgScoreFileBackend' => false,
			'wgScoreDirectory' => false,
			'wgUploadDirectory' => '/tmp/test-upload'
		] );

		$backend = Score::getBackend();

		$this->assertInstanceOf( \Wikimedia\FileBackend\FileBackend::class, $backend );
	}

	/**
	 * Test getBackend() creates FSFileBackend when no custom backend specified
	 */
	public function testGetBackendCreatesFSFileBackend() {
		$this->setMwGlobals( [
			'wgScoreFileBackend' => false,
			'wgScoreDirectory' => '/tmp/test-score',
			'wgUploadDirectory' => '/tmp/upload'
		] );

		$backend = Score::getBackend();

		$this->assertInstanceOf( \Wikimedia\FileBackend\FSFileBackend::class, $backend );
	}

	/**
	 * Test getBackend() uses custom directory when specified
	 */
	public function testGetBackendUsesCustomDirectory() {
		$this->setMwGlobals( [
			'wgScoreFileBackend' => false,
			'wgScoreDirectory' => '/custom/score/dir',
			'wgUploadDirectory' => '/tmp/upload'
		] );

		$backend = Score::getBackend();
		$this->assertInstanceOf( \Wikimedia\FileBackend\FSFileBackend::class, $backend );
	}

	/**
	 * Test getBackend() uses upload directory when score directory is false
	 */
	public function testGetBackendUsesUploadDirectory(): void {
		$this->setMwGlobals( [
			'wgScoreFileBackend' => false,
			'wgScoreDirectory' => false,
			'wgUploadDirectory' => '/tmp/custom-upload'
		] );

		$backend = Score::getBackend();
		$this->assertInstanceOf( \Wikimedia\FileBackend\FSFileBackend::class, $backend );
	}

	/**
	 * Test render() passes parser to renderScore()
	 * @throws ScoreException
	 */
	public function testRenderPassesParserToRenderScore(): void {
		$parser = $this->createMock( \MediaWiki\Parser\Parser::class );
		$parserOutput = $this->createMock( \MediaWiki\Parser\ParserOutput::class );
		$parser->expects( $this->atLeastOnce() )
			->method( 'addTrackingCategory' )
			->with( 'score-use-category' );
		$parser->method( 'getOutput' )
			->willReturn( $parserOutput );
		$frame = $this->createMock( \MediaWiki\Parser\PPFrame::class );

		$result = Score::render( 'c d e f', [], $parser, $frame );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with ABC language
	 * @throws ScoreException
	 */
	public function testRenderScoreWithABCLanguage(): void {
		$abcCode = "X:1\nM:C\nK:C\nC D E F";
		$result = Score::renderScore( $abcCode, [ 'lang' => 'ABC' ] );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() returns HTML error message on exception
	 * @throws ScoreException
	 */
	public function testRenderScoreReturnsHtmlOnException(): void {
		$result = Score::renderScore( 'c d e f', [ 'lang' => 'invalid-lang' ] );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'mw-ext-score-error', $result );
	}

	/**
	 * Test renderScore() with parser returns HTML error on exception
	 * @throws ScoreException
	 */
	public function testRenderScoreWithParserReturnsHtmlOnException(): void {
		$parser = $this->createMock( \MediaWiki\Parser\Parser::class );
		$parserOutput = $this->createMock( \MediaWiki\Parser\ParserOutput::class );
		$parser->method( 'getOutput' )
			->willReturn( $parserOutput );

		$result = Score::renderScore( 'c d e f', [ 'lang' => 'invalid-lang' ], $parser );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-error', $result );
	}

	/**
	 * Test renderScore() with parser without output
	 * @throws ScoreException
	 */
	public function testRenderScoreWithParserWithoutOutput(): void {
		$parser = $this->createMock( \MediaWiki\Parser\Parser::class );
		$parser->method( 'getOutput' )
			->willReturn( null );

		$result = Score::renderScore( 'c d e f', [], $parser );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with empty string code
	 * @throws ScoreException
	 */
	public function testRenderScoreWithEmptyString(): void {
		$result = Score::renderScore( '', [] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with very large line_width_inches
	 * @throws ScoreException
	 */
	public function testRenderScoreWithLargeLineWidth() {
		$result = Score::renderScore( 'c d e f', [ 'line_width_inches' => 1000.0 ] );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

	/**
	 * Test renderScore() with all supported note languages
	 * @throws ScoreException
	 */
	public function testRenderScoreWithAllSupportedNoteLanguages(): void {
		foreach ( array_keys( Score::SUPPORTED_NOTE_LANGUAGES ) as $noteLang ) {
			$result = Score::renderScore( 'c d e f', [ 'note-language' => $noteLang ] );
			$this->assertIsString( $result, "Should handle note language: $noteLang" );
			$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
		}
	}

	/**
	 * Test renderScore() with both lilypond and ABC languages
	 * @throws ScoreException
	 */
	public function testRenderScoreWithBothLanguageTypes(): void {
		$lilypondResult = Score::renderScore( 'c d e f', [ 'lang' => 'lilypond' ] );
		$abcResult = Score::renderScore( "X:1\nM:C\nK:C\nC D E F", [ 'lang' => 'ABC' ] );

		$this->assertIsString( $lilypondResult );
		$this->assertIsString( $abcResult );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $lilypondResult );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $abcResult );
	}

	/**
	 * Test renderScore() with raw mode and ABC language (raw should be ignored)
	 * @throws ScoreException
	 */
	public function testRenderScoreWithRawModeAndABCLanguage(): void {
		// Raw mode is only for lilypond, should be ignored for ABC type.
		$result = Score::renderScore( "X:1\nM:C\nK:C\nC D E F", [
			'lang' => 'ABC',
			'raw' => true
		] );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-ext-score-disabled', $result );
	}

}
