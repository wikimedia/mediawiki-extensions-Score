/*!
 * VisualEditor DataModel MWScoreNode class.
 *
 * @copyright 2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki score node.
 *
 * @class
 * @extends ve.dm.MWInlineExtensionNode
 *
 * @constructor
 * @param {Object} [element]
 */
ve.dm.MWScoreNode = function VeDmMWScoreNode() {
	// Parent constructor
	ve.dm.MWScoreNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWScoreNode, ve.dm.MWInlineExtensionNode );

/* Static members */

ve.dm.MWScoreNode.static.name = 'mwScore';

ve.dm.MWScoreNode.static.tagName = 'img';

ve.dm.MWScoreNode.static.extensionName = 'score';

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWScoreNode );
