import { Util } from 'cypress-wikibase-api';
// eslint-disable-next-line n/no-missing-import
import { ItemViewPage } from '../../support/pageObjects/ItemViewPage';
// eslint-disable-next-line n/no-missing-import
import { EditStatementFormPage } from '../../support/pageObjects/EditStatementFormPage';
// eslint-disable-next-line n/no-missing-import
import { AddStatementFormPage } from '../../support/pageObjects/AddStatementFormPage';
// eslint-disable-next-line n/no-missing-import
import { LoginPage } from '../../support/pageObjects/LoginPage';

describe( 'add score statement', () => {
	context( 'mobile view', () => {
		const propertyName = Util.getTestString( 'score-property' );
		const initialValue = '\\relative c\' { c g c g }';
		beforeEach( () => {
			const loginPage = new LoginPage();
			cy.task(
				'MwApi:CreateUser',
				{ usernamePrefix: 'mextest' },
			).then( ( { username, password } ) => {
				loginPage.login( username, password );
			} );

			cy.task( 'MwApi:CreateProperty', { datatype: 'musical-notation', label: propertyName } )
				.as( 'propertyId' )
				.then( ( propertyId: string ) => cy.task( 'MwApi:CreateItem', {
					label: Util.getTestString( 'item' ),
					data: {
						claims: {
							[ propertyId ]: [ {
								type: 'statement',
								rank: 'normal',
								mainsnak: {
									snaktype: 'value',
									property: propertyId,
									datavalue: {
										type: 'string',
										value: '\\relative c\' { c g c g }',
									},
								},
							} ],
						},
					},
				} ) )
				.as( 'itemId' )
				.then( ( itemId ) => {
					cy.task( 'MwApi:GetEntityData', { entityId: itemId } )
						.as( 'item' );
				} );
		} );

		it( 'loads the item view, allows statements to be edited ' +
			'and new statements to be added', function () {
			const itemViewPage = new ItemViewPage( this.itemId );
			itemViewPage.open();

			itemViewPage.editLinks().first().should( 'exist' ).should( 'be.visible' );
			itemViewPage.editLinks().first().click();

			const editFormPage = new EditStatementFormPage();
			editFormPage.formHeading().should( 'exist' );
			editFormPage.propertyName().should( 'have.text', propertyName );

			editFormPage.textInput().should( 'have.value', initialValue );
			editFormPage.setTextInputValue( '\\relative c\' { a b a b }' );

			editFormPage.publishButton().click();

			/* Wait for the form to close, and check the value is changed */
			editFormPage.formHeading().should( 'not.exist' );
			/* CI has no support for lilypond, so what we actually see here is an error */
			itemViewPage.mainSnakValues().first().should( 'contain.text', 'Unable to obtain LilyPond version' );

			/* Now try adding a new statement */
			itemViewPage.addStatementButton().click();

			const addStatementFormPage = new AddStatementFormPage();
			addStatementFormPage.propertyLookup().should( 'exist' );
			cy.get<string>( '@propertyId' ).then( ( propertyId ) => {
				addStatementFormPage.setProperty( propertyId );
			} );
			addStatementFormPage.publishButton().should( 'be.disabled' );
			addStatementFormPage.snakValueInput().should( 'exist' );
			addStatementFormPage.setSnakValue( '\\relative c\' { e f e f }' );
			addStatementFormPage.publishButton().click();
			addStatementFormPage.form().should( 'not.exist' );
			itemViewPage.mainSnakValues().eq( 1 ).should( 'contain.text', 'Unable to obtain LilyPond version' );
		} );
	} );
} );
