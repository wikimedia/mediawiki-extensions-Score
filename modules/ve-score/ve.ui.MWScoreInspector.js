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

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWScoreInspector );
