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

	wbui2025.store.snakValueStrategyFactory.registerErrorPopoverFormatter(
		'musical-notation',
		( rawHtml ) => {
			const doc = new DOMParser().parseFromString( rawHtml, 'text/html' );
			const errorContent = doc.querySelector( '.cdx-message__content' );
			if ( !errorContent ) {
				return { bodyHtml: rawHtml };
			}

			const preElement = errorContent.querySelector( 'pre' );
			let title = '';
			let bodyHtml = '';

			if ( preElement ) {
				const titleParts = [];
				for ( const node of errorContent.childNodes ) {
					if ( node === preElement ) {
						break;
					}
					if ( node.nodeType === Node.TEXT_NODE || node.nodeType === Node.ELEMENT_NODE ) {
						const text = node.textContent.trim();
						if ( text ) {
							titleParts.push( text );
						}
					}
				}
				title = titleParts.join( ' ' ).replace( /:$/, '' ).trim();
				bodyHtml = preElement.outerHTML;
			} else {
				return { bodyHtml: rawHtml };
			}

			return {
				title: title,
				iconClass: 'wikibase-wbui2025-indicator-icon--error',
				bodyHtml: bodyHtml || errorContent.innerHTML
			};
		}
	);

}(
	wikibase
) );
