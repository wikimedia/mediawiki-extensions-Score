/*!
 * @copyright 2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * The ve.ce.Node for Score (ContentEditable).
 *
 * @class
 * @extends ve.ce.MWInlineExtensionNode
 *
 * @constructor
 * @param {ScoreDmNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
function ScoreCeNode() {
	ScoreCeNode.super.apply( this, arguments );
}

OO.inheritClass( ScoreCeNode, ve.ce.MWInlineExtensionNode );

/* Static properties */

ScoreCeNode.static.name = 'mwScore';
ScoreCeNode.static.primaryCommandName = 'score';
ScoreCeNode.static.iconWhenInvisible = 'score';

/* Methods */

/**
 * @inheritdoc
 */
ScoreCeNode.prototype.onSetup = function () {
	// Parent method
	ScoreCeNode.super.prototype.onSetup.call( this );

	// DOM changes
	this.$element.addClass( 've-ce-mwScoreNode' );
};

/**
 * @inheritdoc ve.ce.GeneratedContentNode
 */
ScoreCeNode.prototype.validateGeneratedContents = function ( $element ) {
	if ( $element.is( 'div' ) && $element.hasClass( 'errorbox' ) ) {
		return false;
	}
	return true;
};

module.exports = ScoreCeNode;
