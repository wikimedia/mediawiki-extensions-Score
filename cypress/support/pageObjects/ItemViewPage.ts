import Chainable = Cypress.Chainable;

export class ItemViewPage {

	public static get SELECTORS(): object {
		return {
			ADD_STATEMENT_BUTTON: '.wikibase-wbui2025-add-statement-button>.cdx-button',
			STATEMENTS: '#wikibase-wbui2025-statementgrouplistview',
			VUE_CLIENTSIDE_RENDERED: '[data-v-app]',
			EDIT_LINKS: '.wikibase-wbui2025-edit-link',
			MAIN_SNAK_VALUES: '.wikibase-wbui2025-main-snak .wikibase-wbui2025-snak-value',
		};
	}

	private itemId: string;

	public constructor( itemId: string ) {
		this.itemId = itemId;
	}

	public open( lang: string = 'en' ): this {
		// We force tests to be in English be default, to be able to make assertions
		// about texts without introducing translation support to Cypress.
		cy.visitTitleMobile( { title: 'Item:' + this.itemId, qs: { uselang: lang } } );
		return this;
	}

	public statementsSection(): Chainable {
		return cy.get( ItemViewPage.SELECTORS.STATEMENTS_SECTION );
	}

	public editLinks(): Chainable {
		return cy.get(
			ItemViewPage.SELECTORS.VUE_CLIENTSIDE_RENDERED + ' ' + ItemViewPage.SELECTORS.EDIT_LINKS,
		);
	}

	public mainSnakValues(): Chainable {
		return cy.get( ItemViewPage.SELECTORS.MAIN_SNAK_VALUES );
	}

	public addStatementButton(): Chainable {
		return cy.get(
			ItemViewPage.SELECTORS.VUE_CLIENTSIDE_RENDERED + ' ' + ItemViewPage.SELECTORS.ADD_STATEMENT_BUTTON,
		);
	}

}
