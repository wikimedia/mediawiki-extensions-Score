<?php

declare( strict_types = 1 );

use MediaWiki\Extension\Score\ResourceLoaderRegisterModulesHookHandler;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\ResourceLoader;
use PHPUnit\Framework\TestCase;
use Wikibase\Lib\SettingsArray;

/**
 * @license GPL-2.0-or-later
 * @covers \MediaWiki\Extension\Score\ResourceLoaderRegisterModulesHookHandler
 */
class ResourceLoaderRegisterModulesHookHandlerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseClient' ) &&
			!ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' )
		) {
			$this->markTestSkipped( "Extension WikibaseClient or WikibaseRepository are required for this test" );
		}
	}

	public function testRegistersNoModulesIfWbuiFeatureDisabled(): void {
		$settings = new SettingsArray( [
			'tmpMobileEditingUI' => false,
		] );
		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->expects( $this->never() )->method( 'register' );
		( new ResourceLoaderRegisterModulesHookHandler( $settings ) )
			->onResourceLoaderRegisterModules( $resourceLoader );
	}

	public function testRegistersWbuiModuleIfWbuiFeatureEnabled(): void {
		$settings = new SettingsArray( [ 'tmpMobileEditingUI' => true ] );
		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader
			->expects( $this->once() )
			->method( 'register' )
			->willReturnCallback( function ( $modules ) {
				$this->assertArrayHasKey( 'score.wbui2025.entityViewInit', $modules );
				return false;
			} );
		( new ResourceLoaderRegisterModulesHookHandler( $settings ) )
			->onResourceLoaderRegisterModules( $resourceLoader );
	}

}
