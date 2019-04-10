/*!
 * @copyright 2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

var ScoreDmNode = require( './ScoreDmNode.js' );

/**
 * Button ("Tool") in a toolbar or context menu for opening a ScoreInspector.
 *
 * @class
 * @extends ve.ui.FragmentInspectorTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
function ScoreInspectorTool( toolGroup, config ) {
	ScoreInspectorTool.super.call( this, toolGroup, config );
}

OO.inheritClass( ScoreInspectorTool, ve.ui.FragmentInspectorTool );

/* Static properties */

ScoreInspectorTool.static.name = 'score';
ScoreInspectorTool.static.group = 'object';
ScoreInspectorTool.static.icon = 'score';
ScoreInspectorTool.static.title = OO.ui.deferMsg(
	'score-visualeditor-mwscoreinspector-title'
);
ScoreInspectorTool.static.modelClasses = [ ScoreDmNode ];
ScoreInspectorTool.static.commandName = 'score';

module.exports = ScoreInspectorTool;
