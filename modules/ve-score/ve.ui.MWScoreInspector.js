/*!
 * VisualEditor UserInterface MWScoreInspector class.
 *
 * @copyright 2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki score inspector.
 *
 * @class
 * @extends ve.ui.MWLiveExtensionInspector
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWScoreInspector = function VeUiMWScoreInspector( config ) {
	// Parent constructor
	ve.ui.MWScoreInspector.super.call( this, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWScoreInspector, ve.ui.MWLiveExtensionInspector );

/* Static properties */

ve.ui.MWScoreInspector.static.name = 'score';

ve.ui.MWScoreInspector.static.icon = 'score';

ve.ui.MWScoreInspector.static.title = OO.ui.deferMsg( 'score-visualeditor-mwscoreinspector-title' );

ve.ui.MWScoreInspector.static.modelClasses = [ ve.dm.MWScoreNode ];

ve.ui.MWScoreInspector.static.dir = 'ltr';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWScoreInspector.prototype.initialize = function () {
	var langDropdown, inputField, langField, midiField, vorbisField, rawField, overrideMidiField, overrideOggField,
		notationCard, audioCard,
		overlay = this.manager.getOverlay();

	// Parent method
	ve.ui.MWScoreInspector.super.prototype.initialize.call( this );

	// Index layout
	this.indexLayout = new OO.ui.IndexLayout( {
		scrollable: false,
		expanded: false
	} );

	// Cards
	notationCard =  new OO.ui.CardLayout( 'notation', {
		label: ve.msg( 'score-visualeditor-mwscoreinspector-notation' ),
		expanded: false,
		scrollable: false,
		padded: true
	} );
	audioCard = new OO.ui.CardLayout( 'audio', {
		label: ve.msg( 'score-visualeditor-mwscoreinspector-audio' ),
		expanded: false,
		scrollable: false,
		padded: true
	} );

	this.indexLayout.addCards( [
		notationCard,
		audioCard
	] );

	// Dropdown
	langDropdown = new OO.ui.DropdownWidget( {
		$overlay: overlay.$element,
		menu: {
			items: [
				new OO.ui.MenuOptionWidget( {
					data: 'lilypond',
					label: ve.msg( 'score-visualeditor-mwscoreinspector-lang-lilypond' )
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'ABC',
					label: ve.msg( 'score-visualeditor-mwscoreinspector-lang-abc' )
				} )
			]
		}
	} );
	this.langMenu = langDropdown.getMenu();

	// Checkboxes
	this.midiCheckbox = new OO.ui.CheckboxInputWidget( {
		value: '0'
	} );
	this.vorbisCheckbox = new OO.ui.CheckboxInputWidget( {
		value: '0'
	} );
	this.rawCheckbox = new OO.ui.CheckboxInputWidget( {
		value: '0'
	} );

	// Text inputs
	this.overrideMidiInput = new OO.ui.TextInputWidget( {
		placeholder: ve.msg( 'score-visualeditor-mwscoreinspector-override-midi-placeholder' )
	} );
	this.overrideOggInput = new OO.ui.TextInputWidget( {
		placeholder: ve.msg( 'score-visualeditor-mwscoreinspector-override-ogg-placeholder' )
	} );

	// Field layouts
	inputField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-title' )
	} );
	langField = new OO.ui.FieldLayout( langDropdown, {
		align: 'top',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-lang' )
	} );
	midiField = new OO.ui.FieldLayout( this.midiCheckbox, {
		align: 'inline',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-midi' )
	} );
	vorbisField = new OO.ui.FieldLayout( this.vorbisCheckbox, {
		align: 'inline',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-vorbis' )
	} );
	rawField = new OO.ui.FieldLayout( this.rawCheckbox, {
		align: 'inline',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-raw' )
	} );
	overrideMidiField = new OO.ui.FieldLayout( this.overrideMidiInput, {
		align: 'top',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-override-midi' )
	} );
	overrideOggField = new OO.ui.FieldLayout( this.overrideOggInput, {
		align: 'top',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-override-ogg' )
	} );

	// Initialization
	this.$content.addClass( 've-ui-mwScoreInspector-content' );
	notationCard.$element.append(
		inputField.$element,
		langField.$element,
		rawField.$element
	);
	audioCard.$element.append(
		midiField.$element,
		overrideMidiField.$element,
		vorbisField.$element,
		overrideOggField.$element
	);
	this.form.$element.append(
		this.indexLayout.$element
	);
};

/**
 * @inheritdoc
 */
ve.ui.MWScoreInspector.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWScoreInspector.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			// jscs:disable requireCamelCaseOrUpperCaseIdentifiers
			var attributes = this.selectedNode.getAttribute( 'mw' ).attrs,
				lang = attributes.lang || 'lilypond',
				raw = attributes.raw !== undefined,
				midi = attributes.midi === '1',
				vorbis = attributes.vorbis === '1',
				overrideMidi = attributes.override_midi || '',
				overrideOgg = attributes.override_ogg || '';
			// jscs:enable requireCamelCaseOrUpperCaseIdentifiers

			// Populate form
			this.langMenu.selectItemByData( lang );
			this.rawCheckbox.setSelected( raw );
			this.midiCheckbox.setSelected( midi );
			this.vorbisCheckbox.setSelected( vorbis );
			this.overrideMidiInput.setValue( overrideMidi );
			this.overrideOggInput.setValue( overrideOgg );

			// Disable any fields that should be disabled
			this.toggleDisableRawCheckbox();
			this.toggleDisableOverrideMidiInput();
			this.toggleDisableOverrideOggInput();

			// Add event handlers
			this.langMenu.on( 'choose', this.onChangeHandler );
			this.rawCheckbox.on( 'change', this.onChangeHandler );
			this.midiCheckbox.on( 'change', this.onChangeHandler );
			this.vorbisCheckbox.on( 'change', this.onChangeHandler );
			this.overrideMidiInput.on( 'change', this.onChangeHandler );
			this.overrideOggInput.on( 'change', this.onChangeHandler );

			this.indexLayout.connect( this, { set: 'updateSize' } );
			this.langMenu.connect( this, { choose: 'toggleDisableRawCheckbox' } );
			this.midiCheckbox.connect( this, { change: 'toggleDisableOverrideMidiInput' } );
			this.vorbisCheckbox.connect( this, { change: 'toggleDisableOverrideOggInput' } );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWScoreInspector.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWScoreInspector.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			this.langMenu.off( 'choose', this.onChangeHandler );
			this.rawCheckbox.off( 'change', this.onChangeHandler );
			this.midiCheckbox.off( 'change', this.onChangeHandler );
			this.vorbisCheckbox.off( 'change', this.onChangeHandler );
			this.overrideMidiInput.off( 'change', this.onChangeHandler );
			this.overrideOggInput.off( 'change', this.onChangeHandler );

			this.langMenu.disconnect( this );
			this.midiCheckbox.disconnect( this );
			this.vorbisCheckbox.disconnect( this );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWScoreInspector.prototype.updateMwData = function ( mwData ) {
	var lang, raw, midi, vorbis, overrideMidi, overrideOgg;

	// Parent method
	ve.ui.MWScoreInspector.super.prototype.updateMwData.call( this, mwData );

	// Get data from inspector
	lang = this.langMenu.getSelectedItem().getData();
	raw = !this.rawCheckbox.isDisabled() && this.rawCheckbox.isSelected();
	midi = this.midiCheckbox.isSelected();
	vorbis = this.vorbisCheckbox.isSelected();
	overrideMidi = !this.overrideMidiInput.isDisabled() && this.overrideMidiInput.getValue();
	overrideOgg = !this.overrideOggInput.isDisabled() && this.overrideOggInput.getValue();

	// Update attributes
	// jscs:disable requireCamelCaseOrUpperCaseIdentifiers
	mwData.attrs.lang = lang;
	mwData.attrs.raw = raw ? '1' : undefined;
	mwData.attrs.midi = midi ? '1' : undefined;
	mwData.attrs.vorbis = vorbis ? '1' : undefined;
	mwData.attrs.override_midi = overrideMidi || undefined;
	mwData.attrs.override_ogg = overrideOgg || undefined;
	// jscs:enable requireCamelCaseOrUpperCaseIdentifiers
};

/**
 * Set the disabled status of this.rawCheckbox based on the lang attribute
 */
ve.ui.MWScoreInspector.prototype.toggleDisableRawCheckbox = function () {
	// Disable the checkbox if the language is not LilyPond
	this.rawCheckbox.setDisabled( this.langMenu.getSelectedItem().getData() !== 'lilypond' );
};

/**
 * Set the disabled status of this.overrideMidiInput based on the midi attribute
 */
ve.ui.MWScoreInspector.prototype.toggleDisableOverrideMidiInput = function () {
	// Disable the input if we are not linking to a MIDI file
	this.overrideMidiInput.setDisabled( !this.midiCheckbox.isSelected() );
};

/**
 * Set the disabled status of this.overrideOggInput based on the vorbis attribute
 */
ve.ui.MWScoreInspector.prototype.toggleDisableOverrideOggInput = function () {
	// Disable the input if we ARE generating an Ogg/Vorbis file
	this.overrideOggInput.setDisabled( this.vorbisCheckbox.isSelected() );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWScoreInspector );
