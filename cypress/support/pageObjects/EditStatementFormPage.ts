import Chainable = Cypress.Chainable;

export class EditStatementFormPage {

	public static get SELECTORS(): object {
		return {
			MODAL_ROOT: '.wikibase-wbui2025-edit-statement-group-modal',
			FORM: '.wikibase-wbui2025-edit-statement',
			FORM_HEADING: '.wikibase-wbui2025-edit-statement-heading',
			PROPERTY_NAME: '.wikibase-wbui2025-property-name > a',
			SUBMIT_BUTTONS: '.wikibase-wbui2025-edit-form-actions > .cdx-button, ' +
				'.wikibase-wbui2025-modal-overlay__footer__actions > .cdx-button',
			TEXT_INPUT: '.wikibase-wbui2025-edit-statement-value-input .cdx-text-input input, .wikibase-wbui2025-edit-statement-value-input textarea',
			LOOKUP_INPUT: '.wikibase-wbui2025-edit-statement-value-input .cdx-lookup input',
		};
	}

	public propertyName(): Chainable {
		return this.root().find( EditStatementFormPage.SELECTORS.PROPERTY_NAME );
	}

	public textInput(): Chainable {
		return this.root().find( EditStatementFormPage.SELECTORS.TEXT_INPUT ).first();
	}

	public setTextInputValue( newInputValue: string ): Chainable {
		return this.textInput().clear().type( newInputValue.replace( /{/g, '{{}' ) );
	}

	public publishButton(): Chainable {
		return this.root().find( EditStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).last();
	}

	public cancelButton(): Chainable {
		return this.root().find( EditStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).first();
	}

	public form(): Chainable {
		return this.root().find( EditStatementFormPage.SELECTORS.FORM );
	}

	public root(): Chainable {
		return cy.get( EditStatementFormPage.SELECTORS.MODAL_ROOT );
	}
}
