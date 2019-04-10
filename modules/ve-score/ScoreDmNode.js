/*!
 * @copyright 2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * The ve.dm.Node for Score (DataModel).
 *
 * @class
 * @extends ve.dm.MWInlineExtensionNode
 *
 * @constructor
 * @param {Object} [element]
 */
function ScoreDmNode() {
	ScoreDmNode.super.apply( this, arguments );
}

OO.inheritClass( ScoreDmNode, ve.dm.MWInlineExtensionNode );

/* Static members */

ScoreDmNode.static.name = 'mwScore';
ScoreDmNode.static.tagName = 'img';
ScoreDmNode.static.extensionName = 'score';

module.exports = ScoreDmNode;
