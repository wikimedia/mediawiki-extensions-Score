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
	var inputField, langField,
		midiField, overrideMidiField,
		vorbisField, overrideOggField,
		rawField,
		notationCard, audioCard, midiCard, advancedCard;

	// Parent method
	ve.ui.MWScoreInspector.super.prototype.initialize.call( this );

	// Index layout
	this.indexLayout = new OO.ui.IndexLayout( {
		scrollable: false,
		expanded: false
	} );

	// Cards
	notationCard =  new OO.ui.CardLayout( 'notation', {
		label: ve.msg( 'score-visualeditor-mwscoreinspector-card-notation' ),
		expanded: false,
		scrollable: false,
		padded: true
	} );
	audioCard = new OO.ui.CardLayout( 'audio', {
		label: ve.msg( 'score-visualeditor-mwscoreinspector-card-audio' ),
		expanded: false,
		scrollable: false,
		padded: true
	} );
	midiCard = new OO.ui.CardLayout( 'midi', {
		label: ve.msg( 'score-visualeditor-mwscoreinspector-card-midi' ),
		expanded: false,
		scrollable: false,
		padded: true
	} );
	advancedCard = new OO.ui.CardLayout( 'advanced', {
		label: ve.msg( 'score-visualeditor-mwscoreinspector-card-advanced' ),
		expanded: false,
		scrollable: false,
		padded: true
	} );

	this.indexLayout.addCards( [
		notationCard,
		audioCard,
		midiCard,
		advancedCard
	] );

	// Language
	this.langSelect = new OO.ui.ButtonSelectWidget( {
		items: [
			new OO.ui.ButtonOptionWidget( {
				data: 'lilypond',
				label: ve.msg( 'score-visualeditor-mwscoreinspector-lang-lilypond' )
			} ),
			new OO.ui.ButtonOptionWidget( {
				data: 'ABC',
				label: ve.msg( 'score-visualeditor-mwscoreinspector-lang-abc' )
			} )
		]
	} );

	// Checkboxes
	this.midiCheckbox = new OO.ui.CheckboxInputWidget();
	this.audioCheckbox = new OO.ui.CheckboxInputWidget();
	this.rawCheckbox = new OO.ui.CheckboxInputWidget();

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
	langField = new OO.ui.FieldLayout( this.langSelect, {
		align: 'left',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-lang' )
	} );
	vorbisField = new OO.ui.FieldLayout( this.audioCheckbox, {
		align: 'inline',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-vorbis' )
	} );
	overrideOggField = new OO.ui.FieldLayout( this.overrideOggInput, {
		align: 'top',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-override-ogg' )
	} );
	midiField = new OO.ui.FieldLayout( this.midiCheckbox, {
		align: 'inline',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-midi' )
	} );
	overrideMidiField = new OO.ui.FieldLayout( this.overrideMidiInput, {
		align: 'top',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-override-midi' )
	} );
	rawField = new OO.ui.FieldLayout( this.rawCheckbox, {
		align: 'inline',
		label: ve.msg( 'score-visualeditor-mwscoreinspector-raw' )
	} );

	// Initialization
	this.$content.addClass( 've-ui-mwScoreInspector-content' );

	notationCard.$element.append(
		inputField.$element,
		langField.$element,
		this.generatedContentsError.$element
	);
	audioCard.$element.append(
		vorbisField.$element,
		overrideOggField.$element
	);
	midiCard.$element.append(
		midiField.$element,
		overrideMidiField.$element
	);
	advancedCard.$element.append(
		rawField.$element
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
			this.langSelect.selectItemByData( lang );
			this.rawCheckbox.setSelected( raw );
			this.midiCheckbox.setSelected( midi );
			// vorbis is only set to 1 if an audio file is being auto-generated, but
			// the checkbox should be checked if an audio file is being auto-generated
			// OR if an existing file has been specified.
			this.audioCheckbox.setSelected( vorbis || overrideOgg );
			this.overrideMidiInput.setValue( overrideMidi );
			this.overrideOggInput.setValue( overrideOgg );

			// Disable any fields that should be disabled
			this.toggleDisableRawCheckbox();
			this.toggleDisableOverrideMidiInput();
			this.toggleDisableOverrideOggInput();

			// Add event handlers
			this.langSelect.on( 'choose', this.onChangeHandler );
			this.rawCheckbox.on( 'change', this.onChangeHandler );
			this.midiCheckbox.on( 'change', this.onChangeHandler );
			this.audioCheckbox.on( 'change', this.onChangeHandler );
			this.overrideMidiInput.on( 'change', this.onChangeHandler );
			this.overrideOggInput.on( 'change', this.onChangeHandler );

			this.indexLayout.connect( this, { set: 'onCardSet' } );
			this.indexLayout.connect( this, { set: 'updateSize' } );
			this.langSelect.connect( this, { choose: 'toggleDisableRawCheckbox' } );
			this.midiCheckbox.connect( this, { change: 'toggleDisableOverrideMidiInput' } );
			this.audioCheckbox.connect( this, { change: 'toggleDisableOverrideOggInput' } );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWScoreInspector.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWScoreInspector.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			this.langSelect.off( 'choose', this.onChangeHandler );
			this.midiCheckbox.off( 'change', this.onChangeHandler );
			this.audioCheckbox.off( 'change', this.onChangeHandler );
			this.overrideMidiInput.off( 'change', this.onChangeHandler );
			this.overrideOggInput.off( 'change', this.onChangeHandler );

			this.indexLayout.disconnect( this );
			this.langSelect.disconnect( this );
			this.midiCheckbox.disconnect( this );
			this.audioCheckbox.disconnect( this );
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
	lang = this.langSelect.getSelectedItem().getData();
	raw = !this.rawCheckbox.isDisabled() && this.rawCheckbox.isSelected();
	// audioCheckbox is selected if an audio file is being included, whether that file
	// is being auto-generated or whether an existing file is being used; but the "vorbis"
	// attribute is only set to 1 if an audio file is being included AND that file is
	// being auto-generated.
	vorbis = this.audioCheckbox.isSelected() && this.overrideOggInput.getValue() === '';
	overrideOgg = !this.overrideOggInput.isDisabled() && this.overrideOggInput.getValue();
	// The "midi" attribute is set to 1 if a MIDI file is being linked to, whether or not
	// this file is being auto-generated.
	midi = this.midiCheckbox.isSelected();
	overrideMidi = !this.overrideMidiInput.isDisabled() && this.overrideMidiInput.getValue();

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
	this.rawCheckbox.setDisabled( this.langSelect.getSelectedItem().getData() !== 'lilypond' );
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
	this.overrideOggInput.setDisabled( !this.audioCheckbox.isSelected() );
};

/**
 * @inheritdoc
 */
ve.ui.MWScoreInspector.prototype.formatGeneratedContentsError = function ( $element ) {
	return $element.text().trim();
};

/**
 * Append the error to the current card.
 */
ve.ui.MWScoreInspector.prototype.onCardSet = function () {
	this.indexLayout.getCurrentCard().$element.append( this.generatedContentsError.$element );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWScoreInspector );
