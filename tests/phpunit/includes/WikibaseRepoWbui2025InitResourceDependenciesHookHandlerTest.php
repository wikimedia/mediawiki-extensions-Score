<?php

declare( strict_types = 1 );

use MediaWiki\Extension\Score\WikibaseRepoWbui2025InitResourceDependenciesHookHandler;

/**
 * @license GPL-2.0-or-later
 * @covers \MediaWiki\Extension\Score\WikibaseRepoWbui2025InitResourceDependenciesHookHandler
 */
class WikibaseRepoWbui2025InitResourceDependenciesHookHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
	}

	public function testDependencyIsAddedToArray(): void {
		$dependencies = [ 'test.dependency' ];
		( new WikibaseRepoWbui2025InitResourceDependenciesHookHandler() )
			->onWikibaseRepoWbui2025InitResourceDependenciesHook( $dependencies );
		$this->assertContains( 'score.wbui2025.entityViewInit', $dependencies );
		$this->assertContains( 'test.dependency', $dependencies );
	}

}
