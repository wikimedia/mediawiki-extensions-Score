<?php

declare( strict_types = 1 );

use MediaWiki\Extension\Score\ResourceLoaderRegisterModulesHookHandler;
use MediaWiki\ResourceLoader\ResourceLoader;
use PHPUnit\Framework\TestCase;
use Wikibase\Lib\SettingsArray;

/**
 * @license GPL-2.0-or-later
 * @covers \MediaWiki\Extension\Score\ResourceLoaderRegisterModulesHookHandler
 */
class ResourceLoaderRegisterModulesHookHandlerTest extends TestCase {

	public function testRegistersNoModulesIfWbuiFeatureDisabled(): void {
		$settings = new SettingsArray( [
			'tmpMobileEditingUI' => false,
			'tmpEnableMobileEditingUIBetaFeature' => false,
		] );
		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->expects( $this->never() )->method( 'register' );
		( new ResourceLoaderRegisterModulesHookHandler( $settings ) )
			->onResourceLoaderRegisterModules( $resourceLoader );
	}

	public static function provideSettingsAndModuleExpectation() {
		yield 'module registered if tmpMobileEditingUI set' => [ true, false ];
		yield 'module registered if tmpEnableMobileEditingUIBetaFeature set' => [ false, true ];
		yield 'module registered if both set' => [ true, true ];
	}

	/**
	 * @dataProvider provideSettingsAndModuleExpectation
	 */
	public function testRegistersWbuiModuleIfWbuiFeatureEnabled(
		bool $tmpMobileEditingUI,
		bool $tmpEnableMobileEditingUIBetaFeature
	): void {
		$settings = new SettingsArray( [
			'tmpMobileEditingUI' => $tmpMobileEditingUI,
			'tmpEnableMobileEditingUIBetaFeature' => $tmpEnableMobileEditingUIBetaFeature,
		] );
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
