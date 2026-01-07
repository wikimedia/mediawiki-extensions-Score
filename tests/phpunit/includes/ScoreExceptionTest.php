<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Score\Tests;

use MediaWiki\Extension\Score\ScoreBackendException;
use MediaWiki\Extension\Score\ScoreDisabledException;
use MediaWiki\Extension\Score\ScoreException;
use MediaWiki\Status\Status;
use MediaWikiIntegrationTestCase;

/**
 * Unit tests for ScoreException and related exception classes
 *
 * @covers \MediaWiki\Extension\Score\ScoreException
 * @covers \MediaWiki\Extension\Score\ScoreDisabledException
 * @covers \MediaWiki\Extension\Score\ScoreBackendException
 */
class ScoreExceptionTest extends MediaWikiIntegrationTestCase {

	/**
	 * Data provider for ScoreException constructor tests
	 *
	 * @return array[]
	 */
	public static function provideScoreExceptionTests(): array {
		return [
			'Exception with message only' => [
				'score-error',
				[]
			],
			'Exception with message and one argument' => [
				'score-error',
				[ 'arg1' ]
			],
			'Exception with message and multiple arguments' => [
				'score-error',
				[ 'arg1', 'arg2', 'arg3' ]
			],
		];
	}

	/**
	 * Test ScoreException constructor and basic methods
	 *
	 * @dataProvider provideScoreExceptionTests
	 * @param string $message Message key
	 * @param array $args Arguments
	 */
	public function testScoreException( string $message, array $args ): void {
		$exception = new ScoreException( $message, $args );

		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertTrue( $exception->isTracked() );
		$this->assertEquals( 'score_error', $exception->getStatsdKey() );
	}

	/**
	 * Test ScoreException getStatsdKey() normalizes message keys
	 */
	public function testScoreExceptionStatsdKeyNormalization(): void {
		$exception = new ScoreException( 'score-test-message' );
		$this->assertEquals( 'score_test_message', $exception->getStatsdKey() );
	}

	/**
	 * Test ScoreDisabledException
	 */
	public function testScoreDisabledException(): void {
		$exception = new ScoreDisabledException();

		$this->assertEquals( 'score-exec-disabled', $exception->getMessage() );
		$this->assertFalse( $exception->isTracked() );
		$this->assertFalse( $exception->getStatsdKey() );
	}

	/**
	 * Test ScoreBackendException
	 */
	public function testScoreBackendException(): void {
		$status = Status::newGood();
		$status->fatal( 'test-error' );
		$exception = new ScoreBackendException( $status );

		$this->assertEquals( 'score-backend-error', $exception->getMessage() );
		$this->assertTrue( $exception->isTracked() );
	}

	/**
	 * Data provider for exception inheritance tests
	 *
	 * @return array[]
	 */
	public static function provideExceptionInheritance(): array {
		return [
			'ScoreException' => [ ScoreException::class, 'score-error', [] ],
			'ScoreDisabledException' => [ ScoreDisabledException::class, null, null ],
			'ScoreBackendException' => [
				ScoreBackendException::class,
				null,
				Status::newGood()
			],
		];
	}

	/**
	 * Test that all exception classes extend ScoreException
	 *
	 * @dataProvider provideExceptionInheritance
	 * @param string $exceptionClass Exception class name
	 * @param string|null $message Message key (if applicable)
	 * @param mixed $constructorArg Constructor argument (if applicable)
	 */
	public function testExceptionInheritance( string $exceptionClass, ?string $message, mixed $constructorArg ): void {
		if ( $exceptionClass === ScoreDisabledException::class ) {
			$exception = new $exceptionClass();
		} elseif ( $exceptionClass === ScoreBackendException::class ) {
			$exception = new $exceptionClass( $constructorArg );
		} else {
			$exception = new $exceptionClass( $message, [] );
		}

		$this->assertInstanceOf( ScoreException::class, $exception );
		$this->assertInstanceOf( \Exception::class, $exception );
	}

}
