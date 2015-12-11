/*!
 * VisualEditor UserInterface MWScoreInspectorTool class.
 *
 * @copyright 2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki UserInterface score tool.
 *
 * @class
 * @extends ve.ui.FragmentInspectorTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */

ve.ui.MWScoreInspectorTool = function VeUiMWScoreInspectorTool( toolGroup, config ) {
	ve.ui.MWScoreInspectorTool.super.call( this, toolGroup, config );
};
OO.inheritClass( ve.ui.MWScoreInspectorTool, ve.ui.FragmentInspectorTool );
ve.ui.MWScoreInspectorTool.static.name = 'score';
ve.ui.MWScoreInspectorTool.static.group = 'object';
ve.ui.MWScoreInspectorTool.static.icon = 'score';
ve.ui.MWScoreInspectorTool.static.title = OO.ui.deferMsg(
	'score-visualeditor-mwscoreinspector-title'
);
ve.ui.MWScoreInspectorTool.static.modelClasses = [ ve.dm.MWScoreNode ];
ve.ui.MWScoreInspectorTool.static.commandName = 'score';
ve.ui.toolFactory.register( ve.ui.MWScoreInspectorTool );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'score', 'window', 'open',
		{ args: [ 'score' ], supportedSelections: [ 'linear' ] }
	)
);

ve.ui.sequenceRegistry.register(
	new ve.ui.Sequence( 'wikitextScore', 'score', '<score', 6 )
);

ve.ui.commandHelpRegistry.register( 'insert', 'score', {
	sequences: [ 'wikitextScore' ],
	label: OO.ui.deferMsg( 'score-visualeditor-mwscoreinspector-title' )
} );
