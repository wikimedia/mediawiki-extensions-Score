import Chainable = Cypress.Chainable;

export class EditStatementFormPage {

	public static get SELECTORS(): object {
		return {
			FORM: '.wikibase-wbui2025-edit-statement',
			FORM_HEADING: '.wikibase-wbui2025-edit-statement-heading',
			PROPERTY_NAME: '.wikibase-wbui2025-property-name > a',
			SUBMIT_BUTTONS: '.wikibase-wbui2025-edit-form-actions > .cdx-button',
			TEXT_INPUT: '.wikibase-wbui2025-edit-statement-value-input .cdx-text-input input',
			LOOKUP_INPUT: '.wikibase-wbui2025-edit-statement-value-input .cdx-lookup input',
		};
	}

	public propertyName(): Chainable {
		return cy.get( EditStatementFormPage.SELECTORS.PROPERTY_NAME );
	}

	public formHeading(): Chainable {
		return cy.get( EditStatementFormPage.SELECTORS.FORM_HEADING );
	}

	public textInput(): Chainable {
		return cy.get( EditStatementFormPage.SELECTORS.TEXT_INPUT ).first();
	}

	public setTextInputValue( newInputValue: string ): Chainable {
		return this.textInput().clear().type( newInputValue.replace( /{/g, '{{}' ) );
	}

	public publishButton(): Chainable {
		return cy.get( EditStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).last();
	}

	public cancelButton(): Chainable {
		return cy.get( EditStatementFormPage.SELECTORS.SUBMIT_BUTTONS ).first();
	}

	public form(): Chainable {
		return cy.get( EditStatementFormPage.SELECTORS.FORM );
	}
}
