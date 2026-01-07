<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Score\Tests;

use MediaWiki\Extension\Score\LilypondErrorMessageBeautifier;
use MediaWikiIntegrationTestCase;

/**
 * Unit tests for LilypondErrorMessageBeautifier class
 *
 * @covers \MediaWiki\Extension\Score\LilypondErrorMessageBeautifier
 */
class LilypondErrorMessageBeautifierTest extends MediaWikiIntegrationTestCase {

	/**
	 * Data provider for beautifyMessage() tests
	 *
	 * @return array[]
	 */
	public static function provideBeautifyMessageTests(): array {
		return [
			'Single error with no offset' => [
				0,
				"test.ly:5:10: error: unexpected character",
				"line 5 - column 10:\nunexpected character"
			],
			'Single error with offset' => [
				7,
				"test.ly:12:15: error: syntax error",
				"line 5 - column 15:\nsyntax error"
			],
			'Multiple errors' => [
				0,
				"test.ly:3:5: error: first error\ntest.ly:7:12: error: second error",
				"line 3 - column 5:\nfirst error\n--------\nline 7 - column 12:\nsecond error"
			],
			'Multiple errors with offset' => [
				5,
				"test.ly:10:8: error: error one\ntest.ly:15:20: error: error two",
				"line 5 - column 8:\nerror one\n--------\nline 10 - column 20:\nerror two"
			],
			'No error matches' => [
				0,
				"Some random text without error format",
				''
			],
			'Empty message' => [
				0,
				'',
				''
			],
		];
	}

	/**
	 * Data provider for constructor tests
	 *
	 * @return array[]
	 */
	public static function provideConstructorTests(): array {
		return [
			'Default offset (0)' => [ 0 ],
			'Positive offset' => [ 7 ],
			'Large offset' => [ 100 ],
		];
	}

	/**
	 * Test beautifyMessage() with various inputs
	 *
	 * @dataProvider provideBeautifyMessageTests
	 * @param int $offset Line offset
	 * @param string $input Input error message
	 * @param string $expected Expected beautified output
	 */
	public function testBeautifyMessage( int $offset, string $input, string $expected ): void {
		$beautifier = new LilypondErrorMessageBeautifier( $offset );
		$result = $beautifier->beautifyMessage( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test constructor with different offsets
	 *
	 * @dataProvider provideConstructorTests
	 * @param int $offset Line offset
	 */
	public function testConstructor( int $offset ): void {
		$beautifier = new LilypondErrorMessageBeautifier( $offset );
		// Test that it doesn't throw and can process the messages.
		$result = $beautifier->beautifyMessage( 'test.ly:5:10: error: test' );
		$this->assertIsString( $result );
	}

	/**
	 * Test that line numbers are correctly adjusted with offset
	 */
	public function testLineNumberAdjustment(): void {
		$offset = 10;
		$beautifier = new LilypondErrorMessageBeautifier( $offset );
		$input = "test.ly:15:5: error: test error";
		$result = $beautifier->beautifyMessage( $input );

		// Line 15 with offset 10 should become line 5.
		$this->assertStringContainsString( 'line 5', $result );
		$this->assertStringNotContainsString( 'line 15', $result );
	}

	/**
	 * Test that column numbers are preserved
	 */
	public function testColumnNumberPreservation() {
		$beautifier = new LilypondErrorMessageBeautifier( 0 );
		$input = "test.ly:5:42: error: test error";
		$result = $beautifier->beautifyMessage( $input );

		$this->assertStringContainsString( 'column 42', $result );
	}

	/**
	 * Test error message extraction
	 */
	public function testErrorMessageExtraction(): void {
		$beautifier = new LilypondErrorMessageBeautifier( 0 );
		$input = "test.ly:5:10: error: unexpected character 'x'";
		$result = $beautifier->beautifyMessage( $input );

		$this->assertStringContainsString( "unexpected character 'x'", $result );
	}

	/**
	 * Test multiple errors are separated correctly
	 */
	public function testMultipleErrorsSeparation(): void {
		$beautifier = new LilypondErrorMessageBeautifier( 0 );
		$input = "test.ly:3:5: error: error1\ntest.ly:7:12: error: error2";
		$result = $beautifier->beautifyMessage( $input );

		$this->assertStringContainsString( '--------', $result );
		$this->assertStringContainsString( 'error1', $result );
		$this->assertStringContainsString( 'error2', $result );
	}

}
