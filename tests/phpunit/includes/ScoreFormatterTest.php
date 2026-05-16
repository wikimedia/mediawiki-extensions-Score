<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Score\Tests;

use DataValues\StringValue;
use InvalidArgumentException;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Score\ScoreFormatter;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use Wikibase\Lib\Formatters\SnakFormatter;

/**
 * Unit tests for ScoreFormatter class (musical-notation datatype)
 *
 * @covers \MediaWiki\Extension\Score\ScoreFormatter
 */
class ScoreFormatterTest extends MediaWikiIntegrationTestCase {

	private array $constMapping;

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseClient' ) &&
			!ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' )
		) {
			$this->markTestSkipped( "Extension WikibaseClient or WikibaseRepository are required for this test" );
		}
		$this->constMapping = [
			'FORMAT_PLAIN' => SnakFormatter::FORMAT_PLAIN,
			'FORMAT_WIKI' => SnakFormatter::FORMAT_WIKI,
			'FORMAT_HTML' => SnakFormatter::FORMAT_HTML,
		];
	}

	/**
	 * Data provider for format() tests with different formats and inputs
	 *
	 * @return array[]
	 */
	public static function provideFormatTests(): array {
		$simpleNotation = 'c d e f g';
		$complexLilypond = <<<LY
\\relative c' {
  c d e f
  g a b c'
}
LY;
		$singleLineComplex = <<<LY
\\relative c' {
  c d e f
}
LY;

		return [
			'Plain format with simple notation' => [
				'FORMAT_PLAIN',
				$simpleNotation,
				$simpleNotation
			],
			'Plain format with empty string' => [
				'FORMAT_PLAIN',
				'',
				''
			],
			'Plain format with complex LilyPond' => [
				'FORMAT_PLAIN',
				$complexLilypond,
				$complexLilypond
			],
			'Wiki format with simple notation' => [
				'FORMAT_WIKI',
				$simpleNotation,
				"<score>$simpleNotation</score>"
			],
			'Wiki format with empty string' => [
				'FORMAT_WIKI',
				'',
				'<score></score>'
			],
			'Wiki format with complex LilyPond' => [
				'FORMAT_WIKI',
				$singleLineComplex,
				"<score>$singleLineComplex</score>"
			],
		];
	}

	/**
	 * Data provider for getFormat() tests
	 *
	 * @return array[]
	 */
	public static function provideGetFormatTests(): array {
		return [
			'Plain format' => [ 'FORMAT_PLAIN' ],
			'Wiki format' => [ 'FORMAT_WIKI' ],
			'HTML format' => [ 'FORMAT_HTML' ],
		];
	}

	/**
	 * Data provider for invalid value tests
	 *
	 * @return array[]
	 */
	public static function provideInvalidValues(): array {
		return [
			'String value' => [ 'not a StringValue' ],
			'Integer value' => [ 123 ],
			'Array value' => [ [ 'test' ] ],
			'Null value' => [ null ],
			'Boolean value' => [ true ],
		];
	}

	/**
	 * Test format() method with different formats and inputs
	 *
	 * @dataProvider provideFormatTests
	 * @param string $format Format type
	 * @param string $input Input musical notation
	 * @param string $expected Expected output
	 */
	public function testFormat( string $format, string $input, string $expected ): void {
		$formatter = new ScoreFormatter( new HashConfig(), $this->constMapping[$format] );
		$value = new StringValue( $input );
		$result = $formatter->format( $value );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test getFormat() returns the correct format
	 *
	 * @dataProvider provideGetFormatTests
	 * @param string $format Format type to test
	 */
	public function testGetFormat( string $format ): void {
		$format = $this->constMapping[$format];
		$formatter = new ScoreFormatter( new HashConfig(), $format );
		$this->assertEquals( $format, $formatter->getFormat() );
	}

	/**
	 * Test format() throws InvalidArgumentException for invalid values
	 *
	 * @dataProvider provideInvalidValues
	 * @param mixed $invalidValue Invalid input value
	 */
	public function testFormatInvalidValue( mixed $invalidValue ): void {
		$formatter = new ScoreFormatter( new HashConfig(), SnakFormatter::FORMAT_PLAIN );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( '$value must be a StringValue' );

		$formatter->format( $invalidValue );
	}

}
