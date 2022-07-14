/*!
 * VisualEditor UserInterface MWScoreDialog class.
 *
 * @copyright 2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki score dialog.
 *
 * @class
 * @extends ve.ui.MWExtensionPreviewDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWScoreDialog = function VeUiMWScoreDialog( config ) {
	// Parent constructor
	ve.ui.MWScoreDialog.super.call( this, ve.extendObject( { padded: false }, config ) );

	// Use a slower debounce (T312319)
	this.updatePreviewDebounced = ve.debounce( this.updatePreview.bind( this ), 2000 );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWScoreDialog, ve.ui.MWExtensionPreviewDialog );

/* Static properties */

ve.ui.MWScoreDialog.static.size = 'larger';

ve.ui.MWScoreDialog.static.name = 'score';

ve.ui.MWScoreDialog.static.title = OO.ui.deferMsg( 'score-visualeditor-mwscoredialog-title' );

ve.ui.MWScoreDialog.static.modelClasses = [ ve.dm.MWScoreNode ];

ve.ui.MWScoreDialog.static.dir = 'ltr';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWScoreDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWScoreDialog.super.prototype.initialize.call( this );

	// Index layout
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		scrollable: false,
		expanded: false
	} );

	// Language
	this.langSelect = new OO.ui.ButtonSelectWidget( {
		items: [
			new OO.ui.ButtonOptionWidget( {
				data: 'lilypond',
				label: ve.msg( 'score-visualeditor-mwscoredialog-lang-lilypond' )
			} ),
			new OO.ui.ButtonOptionWidget( {
				data: 'ABC',
				label: ve.msg( 'score-visualeditor-mwscoredialog-lang-abc' )
			} )
		]
	} );

	var languageItems = [];
	// Note Language
	this.noteLanguageDropdown = new OO.ui.DropdownWidget();
	languageItems.push(
		new OO.ui.MenuOptionWidget( {
			data: null,
			label: '\u00a0'
		} )
	);

	var languages = mw.config.get( 'wgScoreNoteLanguages' );
	for ( var language in languages ) {
		languageItems.push( new OO.ui.MenuOptionWidget( {
			data: language,
			label: languages[ language ]
		} ) );
	}
	this.noteLanguageDropdown.getMenu().addItems( languageItems );

	// Checkboxes
	this.midiCheckbox = new OO.ui.CheckboxInputWidget();
	this.audioCheckbox = new OO.ui.CheckboxInputWidget();
	this.rawCheckbox = new OO.ui.CheckboxInputWidget();

	// Text inputs
	this.overrideMidiInput = new OO.ui.TextInputWidget( {
		placeholder: ve.msg( 'score-visualeditor-mwscoredialog-override-midi-placeholder' )
	} );
	this.overrideAudioInput = new OO.ui.TextInputWidget( {
		placeholder: ve.msg( 'score-visualeditor-mwscoredialog-override-audio-placeholder' )
	} );

	this.input = new ve.ui.MWAceEditorWidget( {
		rows: 8
	} );

	// Field layouts
	var basicFieldset = new OO.ui.FieldsetLayout();
	var inputField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'score-visualeditor-mwscoredialog-title' )
	} );
	var langField = new OO.ui.FieldLayout( this.langSelect, {
		align: 'left',
		label: ve.msg( 'score-visualeditor-mwscoredialog-lang' )
	} );
	basicFieldset.addItems( [ inputField, langField ] );

	var advancedFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'score-visualeditor-mwscoredialog-card-advanced' )
	} );
	var rawField = new OO.ui.FieldLayout( this.rawCheckbox, {
		align: 'left',
		label: ve.msg( 'score-visualeditor-mwscoredialog-raw' )
	} );
	var noteLanguageField = new OO.ui.FieldLayout( this.noteLanguageDropdown, {
		align: 'left',
		label: ve.msg( 'score-visualeditor-mwscoredialog-notelanguage' )
	} );
	advancedFieldset.addItems( [ rawField, noteLanguageField ] );

	var audioFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'score-visualeditor-mwscoredialog-card-audio' )
	} );
	var audioField = new OO.ui.FieldLayout( this.audioCheckbox, {
		align: 'left',
		label: ve.msg( 'score-visualeditor-mwscoredialog-audio' )
	} );
	var overrideAudioField = new OO.ui.FieldLayout( this.overrideAudioInput, {
		align: 'left',
		label: ve.msg( 'score-visualeditor-mwscoredialog-override-audio' )
	} );
	var overrideMidiField = new OO.ui.FieldLayout( this.overrideMidiInput, {
		align: 'left',
		label: ve.msg( 'score-visualeditor-mwscoredialog-override-midi' )
	} );
	audioFieldset.addItems( [ audioField, overrideAudioField, overrideMidiField ] );

	// Initialization
	this.$content.addClass( 've-ui-mwScoreDialog-content' );

	this.panel.$element.append(
		this.previewElement.$element,
		basicFieldset.$element,
		advancedFieldset.$element,
		audioFieldset.$element
	);
	this.$body.append(
		this.panel.$element
	);
};

/**
 * @inheritdoc
 */
ve.ui.MWScoreDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWScoreDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var attributes = this.selectedNode ? this.selectedNode.getAttribute( 'mw' ).attrs : {},
				lang = attributes.lang || 'lilypond',
				noteLanguage = attributes[ 'note-language' ] || null,
				raw = attributes.raw !== undefined,
				audio = attributes.audio === '1' || attributes.vorbis === '1',
				overrideMidi = attributes.override_midi || '',
				overrideAudio = attributes.override_audio || attributes.override_ogg || '',
				isReadOnly = this.isReadOnly();

			// Populate form
			this.langSelect.selectItemByData( lang ).setDisabled( isReadOnly );
			this.noteLanguageDropdown.getMenu().selectItemByData( noteLanguage )
				.setDisabled( isReadOnly );
			this.rawCheckbox.setSelected( raw ).setDisabled( isReadOnly );
			// 'audio' is only set to 1 if an audio file is being auto-generated, but
			// the checkbox should be checked if an audio file is being auto-generated
			// OR if an existing file has been specified.
			this.audioCheckbox.setSelected( audio || overrideAudio ).setDisabled( isReadOnly );
			this.overrideMidiInput.setValue( overrideMidi ).setReadOnly( isReadOnly );
			this.overrideAudioInput.setValue( overrideAudio ).setReadOnly( isReadOnly );

			// Disable any fields that should be disabled
			this.onLangSelectChoose();
			this.toggleDisableNoteLanguageDropdown();
			this.toggleDisableOverrideAudioInput();

			// Add event handlers
			this.langSelect.on( 'choose', this.onChangeHandler );
			this.noteLanguageDropdown.on( 'labelChange', this.onChangeHandler );
			this.rawCheckbox.on( 'change', this.onChangeHandler );
			this.audioCheckbox.on( 'change', this.onChangeHandler );
			this.overrideMidiInput.on( 'change', this.onChangeHandler );
			this.overrideAudioInput.on( 'change', this.onChangeHandler );

			this.previewElement.connect( this, { render: 'updateSize' } );

			this.rawCheckbox.connect( this, { change: 'toggleDisableNoteLanguageDropdown' } );
			this.langSelect.connect( this, { choose: 'onLangSelectChoose' } );
			this.audioCheckbox.connect( this, { change: 'toggleDisableOverrideAudioInput' } );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWScoreDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWScoreDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			this.langSelect.off( 'choose', this.onChangeHandler );
			this.noteLanguageDropdown.off( 'labelChange', this.onChangeHandler );
			this.audioCheckbox.off( 'change', this.onChangeHandler );
			this.overrideMidiInput.off( 'change', this.onChangeHandler );
			this.overrideAudioInput.off( 'change', this.onChangeHandler );

			this.previewElement.disconnect( this );

			this.langSelect.disconnect( this );
			this.audioCheckbox.disconnect( this );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWScoreDialog.prototype.updateMwData = function ( mwData ) {
	// Parent method
	ve.ui.MWScoreDialog.super.prototype.updateMwData.call( this, mwData );

	// Get data from dialog
	var lang = this.langSelect.findSelectedItem().getData();
	var noteLanguage =
		this.noteLanguageDropdown.getMenu().findSelectedItem().getData() || undefined;
	var raw = !this.rawCheckbox.isDisabled() && this.rawCheckbox.isSelected();
	// audioCheckbox is selected if an audio file is being included, whether that file
	// is being auto-generated or whether an existing file is being used; but the "audio"
	// attribute is only set to 1 if an audio file is being included AND that file is
	// being auto-generated.
	var audio = this.audioCheckbox.isSelected() && this.overrideAudioInput.getValue() === '';
	var overrideAudio = !this.overrideAudioInput.isDisabled() && this.overrideAudioInput.getValue();
	var overrideMidi = !this.overrideMidiInput.isDisabled() && this.overrideMidiInput.getValue();

	// Update attributes
	mwData.attrs.lang = lang;
	mwData.attrs[ 'note-language' ] = raw ? undefined : noteLanguage;
	mwData.attrs.raw = raw ? '1' : undefined;
	mwData.attrs.audio = audio ? '1' : undefined;
	/* eslint-disable camelcase */
	mwData.attrs.override_midi = overrideMidi || undefined;
	mwData.attrs.override_audio = overrideAudio || undefined;
	/* eslint-enable camelcase */

	// Deprecated
	delete mwData.attrs.override_ogg;
	delete mwData.attrs.vorbis;
};

/**
 * Handle choose events from the language select widget
 */
ve.ui.MWScoreDialog.prototype.onLangSelectChoose = function () {
	var lang = this.langSelect.findSelectedItem().getData();
	// Disable the checkbox if the language is not LilyPond
	this.rawCheckbox.setDisabled( this.isReadOnly() || lang !== 'lilypond' );

	var langMap = {
		lilypond: 'latex',
		ABC: 'abc'
	};

	this.input.setLanguage( langMap[ lang ] || 'text' );
};

/**
 * Set the disabled status of this.noteLanguage based on the raw attribute
 */
ve.ui.MWScoreDialog.prototype.toggleDisableNoteLanguageDropdown = function () {
	// Disable the dropdown if raw mode is used
	this.noteLanguageDropdown.setDisabled( this.isReadOnly() || this.rawCheckbox.isSelected() );
};

/**
 * Set the disabled status of this.overrideAudioInput based on the audio attribute
 */
ve.ui.MWScoreDialog.prototype.toggleDisableOverrideAudioInput = function () {
	// Disable the input if we ARE generating an audio file
	this.overrideAudioInput.setDisabled( !this.audioCheckbox.isSelected() );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWScoreDialog );
