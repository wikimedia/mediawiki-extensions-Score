// Register nodes
ve.dm.modelRegistry.register( require( './ScoreDmNode.js' ) );
ve.ce.nodeFactory.register( require( './ScoreCeNode.js' ) );

// Register UI
ve.ui.windowFactory.register( require( './ScoreInspector.js' ) );
ve.ui.toolFactory.register( require( './ScoreInspectorTool.js' ) );

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
