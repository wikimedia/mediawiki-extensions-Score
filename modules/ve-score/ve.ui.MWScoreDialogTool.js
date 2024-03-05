/*!
 * VisualEditor UserInterface MWScoreDialogTool class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki UserInterface score tool.
 *
 * @class
 * @extends ve.ui.FragmentDialogTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */

ve.ui.MWScoreDialogTool = function VeUiMWScoreDialogTool( toolGroup, config ) {
	ve.ui.MWScoreDialogTool.super.call( this, toolGroup, config );
};
OO.inheritClass( ve.ui.MWScoreDialogTool, ve.ui.FragmentWindowTool );
ve.ui.MWScoreDialogTool.static.name = 'score';
ve.ui.MWScoreDialogTool.static.group = 'object';
ve.ui.MWScoreDialogTool.static.icon = 'score';
ve.ui.MWScoreDialogTool.static.title = OO.ui.deferMsg(
	'score-visualeditor-mwscoredialog-title'
);
ve.ui.MWScoreDialogTool.static.modelClasses = [ ve.dm.MWScoreNode ];
ve.ui.MWScoreDialogTool.static.commandName = 'score';
ve.ui.toolFactory.register( ve.ui.MWScoreDialogTool );

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
	label: OO.ui.deferMsg( 'score-visualeditor-mwscoredialog-title' )
} );
