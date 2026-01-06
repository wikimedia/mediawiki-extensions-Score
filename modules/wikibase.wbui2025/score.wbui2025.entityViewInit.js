/**
 * @license GPL-2.0-or-later
 */
/**
 * @param {Object} wikibase Wikibase object
 */
( function () {
	'use strict';

	const wbui2025 = require( 'wikibase.wbui2025.lib' );

	class ScoreValueStrategy extends wbui2025.store.StringValueStrategy {
	}

	/**
	 * Score Registration
	 */
	wbui2025.store.snakValueStrategyFactory.registerStrategyForDatatype(
		'musical-notation', ( store ) => new ScoreValueStrategy( store )
	);

}(
	wikibase
) );
