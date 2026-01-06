'use strict';

jest.mock(
	'wikibase.wbui2025.lib',
	() => ( {
		store: {
			snakValueStrategyFactory: {
				registerStrategyForDatatype: jest.fn()
			},
			StringValueStrategy: class {}
		}
	} ),
	{ virtual: true }
);

// eslint-disable-next-line n/no-missing-require
const wbui2025 = require( 'wikibase.wbui2025.lib' );

describe( 'entityViewInit', () => {
	it( 'loads the score value strategy', async () => {
		require( '../../../modules/wikibase.wbui2025/score.wbui2025.entityViewInit.js' );
		expect( wbui2025.store.snakValueStrategyFactory.registerStrategyForDatatype )
			.toHaveBeenCalledWith( 'musical-notation', expect.anything() );
	} );
} );
