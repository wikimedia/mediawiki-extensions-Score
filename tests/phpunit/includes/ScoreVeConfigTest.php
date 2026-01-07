<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Score\Tests;

use MediaWiki\Extension\Score\Score;
use MediaWiki\Extension\Score\ScoreVeConfig;
use MediaWiki\ResourceLoader\Context;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ScoreVeConfig class
 *
 * @covers \MediaWiki\Extension\Score\ScoreVeConfig
 */
class ScoreVeConfigTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var MockObject|Context
	 */
	private MockObject|Context $mockContext;

	protected function setUp(): void {
		parent::setUp();
		$this->mockContext = $this->createMockContext();
	}

	/**
	 * Create a mock ResourceLoader Context
	 *
	 * @return MockObject|Context
	 */
	private function createMockContext(): MockObject|Context {
		$context = $this->createMock( Context::class );
		$context->method( 'encodeJson' )
			->willReturnCallback( static function ( $data ) {
				return json_encode( $data );
			} );
		return $context;
	}

	/**
	 * Test makeScript() returns valid JavaScript
	 */
	public function testMakeScriptReturnsJavaScript(): void {
		$script = ScoreVeConfig::makeScript( $this->mockContext );

		$this->assertIsString( $script );
		$this->assertStringStartsWith( 'mw.config.set(', $script );
		$this->assertStringEndsWith( ');', $script );
	}

	/**
	 * Test makeScript() includes wgScoreNoteLanguages
	 */
	public function testMakeScriptIncludesNoteLanguages(): void {
		$script = ScoreVeConfig::makeScript( $this->mockContext );

		$this->assertStringContainsString( 'wgScoreNoteLanguages', $script );
	}

	/**
	 * Test makeScript() contains all supported note languages
	 */
	public function testMakeScriptContainsAllSupportedLanguages(): void {
		$script = ScoreVeConfig::makeScript( $this->mockContext );

		// Extract the JSON fron the script
		preg_match( '/mw\.config\.set\((.*)\);/s', $script, $matches );
		$this->assertNotEmpty( $matches[1], 'Should extract JSON from script' );

		$config = json_decode( $matches[1], true );
		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'wgScoreNoteLanguages', $config );

		$noteLanguages = $config['wgScoreNoteLanguages'];
		$this->assertIsArray( $noteLanguages );

		// Verify all supported languages from Score::SUPPORTED_NOTE_LANGUAGES are present
		$supportedLanguages = Score::SUPPORTED_NOTE_LANGUAGES;
		foreach ( array_keys( $supportedLanguages ) as $lilyName ) {
			$this->assertArrayHasKey( $lilyName, $noteLanguages, "Should include $lilyName" );
		}
	}

	/**
	 * Test makeScript() JSON is valid
	 */
	public function testMakeScriptValidJson(): void {
		$script = ScoreVeConfig::makeScript( $this->mockContext );

		// Extract JSON from the script
		preg_match( '/mw\.config\.set\((.*)\);/s', $script, $matches );
		$this->assertNotEmpty( $matches[1], 'Should extract JSON from script' );

		$decoded = json_decode( $matches[1], true );
		$this->assertNotNull( $decoded, 'JSON should be valid' );
		$this->assertIsArray( $decoded );
	}

	/**
	 * Test makeScript() with different context encodeJson implementations
	 */
	public function testMakeScriptWithDifferentContext(): void {
		$context1 = $this->createMock( Context::class );
		$context1->method( 'encodeJson' )
			->willReturn( '{"test":"value"}' );

		$script1 = ScoreVeConfig::makeScript( $context1 );
		$this->assertStringContainsString( '{"test":"value"}', $script1 );

		$context2 = $this->createMock( Context::class );
		$context2->method( 'encodeJson' )
			->willReturn( '{"different":"data"}' );

		$script2 = ScoreVeConfig::makeScript( $context2 );
		$this->assertStringContainsString( '{"different":"data"}', $script2 );
	}

	/**
	 * Test that makeScript() is static
	 */
	public function testMakeScriptIsStatic(): void {
		$reflection = new \ReflectionMethod( ScoreVeConfig::class, 'makeScript' );

		$this->assertTrue( $reflection->isStatic(), 'makeScript should be a stativ method' );
	}

}
