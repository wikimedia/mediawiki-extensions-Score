<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Score;

use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use Wikibase\Lib\SettingsArray;

/**
 * @license GPL-2.0-or-later
 */
class ResourceLoaderRegisterModulesHookHandler implements ResourceLoaderRegisterModulesHook {

	private ?SettingsArray $settings;

	public function __construct( ?SettingsArray $settings ) {
		$this->settings = $settings;
	}

	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		if ( !$this->settings ) {
			return;
		}
		$moduleTemplate = [
			'localBasePath' => __DIR__ . '/..',
			'remoteExtPath' => 'Score',
		];

		if (
			$this->settings->getSetting( 'tmpMobileEditingUI' ) ||
			$this->settings->getSetting( 'tmpEnableMobileEditingUIBetaFeature' )
		) {
			$modules = [ 'score.wbui2025.entityViewInit' => $moduleTemplate +
				[
					'packageFiles' => [
						'modules/wikibase.wbui2025/score.wbui2025.entityViewInit.js',
					],
					'dependencies' => [
						'wikibase.wbui2025.lib',
					],
				],
			];
			$rl->register( $modules );
		}
	}
}
