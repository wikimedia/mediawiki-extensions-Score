'use strict';

const { createSSRApp } = require( 'vue' );
const { renderToString } = require( 'vue/server-renderer' );
const { CdxMessage } = require( '@wikimedia/codex' );

global.wikibase = {};

const mockRegisterStrategyForDatatype = jest.fn();
const mockRegisterErrorPopoverFormatter = jest.fn();

jest.mock(
	'wikibase.wbui2025.lib',
	() => ( {
		store: {
			snakValueStrategyFactory: {
				registerStrategyForDatatype: mockRegisterStrategyForDatatype,
				registerErrorPopoverFormatter: mockRegisterErrorPopoverFormatter
			},
			StringValueStrategy: class {}
		}
	} ),
	{ virtual: true }
);

let strategyCall;
let formatterCall;
let formatter;

beforeAll( () => {
	require( '../../../modules/wikibase.wbui2025/score.wbui2025.entityViewInit.js' );
	strategyCall = mockRegisterStrategyForDatatype.mock.calls[ 0 ];
	formatterCall = mockRegisterErrorPopoverFormatter.mock.calls[ 0 ];
	formatter = formatterCall[ 1 ];
} );

describe( 'entityViewInit', () => {
	it( 'loads the score value strategy', () => {
		expect( strategyCall[ 0 ] ).toBe( 'musical-notation' );
		expect( typeof strategyCall[ 1 ] ).toBe( 'function' );
	} );

	it( 'registers an error popover formatter for musical-notation', () => {
		expect( formatterCall[ 0 ] ).toBe( 'musical-notation' );
		expect( typeof formatterCall[ 1 ] ).toBe( 'function' );
	} );
} );

describe( 'error popover formatter', () => {
	it( 'extracts title and body from a Score error with <pre> content', async () => {
		const errorHtml = await renderToString( createSSRApp( {
			template: `<cdx-message type="error" class="mw-ext-score-error">
<p>Unable to compile LilyPond input file:</p>
<pre lang="en" dir="ltr">line 1 – column 5:
not a note name: cm</pre>
</cdx-message>`,
			components: { CdxMessage }
		} ) );

		const result = formatter( errorHtml );

		expect( result.title ).toBe( 'Unable to compile LilyPond input file' );
		expect( result.iconClass ).toBe( 'wikibase-wbui2025-indicator-icon--error' );
		expect( result.bodyHtml ).toBe( '<pre lang="en" dir="ltr">line 1 – column 5:\nnot a note name: cm</pre>' );
	} );

	it( 'combines multiple title parts as plain text', async () => {
		const errorHtml = await renderToString( createSSRApp( {
			template: `<cdx-message type="error" class="mw-ext-score-error">
<p>title part <strong>1</strong></p>
<p>title &lt; part 2:</p>
<pre></pre>
</cdx-message>
`,
			components: { CdxMessage }
		} ) );

		const result = formatter( errorHtml );

		expect( result.title ).toBe( 'title part 1 title < part 2' );
	} );

	it( 'handles error content without <pre> element', async () => {
		const errorHtml = await renderToString( createSSRApp( {
			template: `<cdx-message type="error" class="mw-ext-score-error">
Musical scores are temporarily <strong>disabled</strong>.
</cdx-message>`,
			components: { CdxMessage }
		} ) );

		const result = formatter( errorHtml );

		expect( result.bodyHtml ).toContain( 'Musical scores are temporarily <strong>disabled</strong>.' );
		expect( result ).not.toHaveProperty( 'iconClass' );
		expect( result ).not.toHaveProperty( 'title' );
	} );

	it( 'falls back to raw HTML when no cdx-message__content found', () => {
		const rawHtml = '<div>Some unknown error format</div>';

		const result = formatter( rawHtml );

		expect( result ).toEqual( { bodyHtml: rawHtml } );
	} );
} );
