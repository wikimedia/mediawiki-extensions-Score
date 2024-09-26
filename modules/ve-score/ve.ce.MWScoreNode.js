/*!
 * VisualEditor ContentEditable MWScoreNode class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki score node.
 *
 * @class
 * @extends ve.ce.MWInlineExtensionNode
 *
 * @constructor
 * @param {ve.dm.MWScoreNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWScoreNode = function VeCeMWScoreNode() {
	// Parent constructor
	ve.ce.MWScoreNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWScoreNode, ve.ce.MWInlineExtensionNode );

/* Static properties */

ve.ce.MWScoreNode.static.name = 'mwScore';

ve.ce.MWScoreNode.static.primaryCommandName = 'score';

ve.ce.MWScoreNode.static.iconWhenInvisible = 'score';

/* Methods */

/**
 * @inheritdoc
 */
ve.ce.MWScoreNode.prototype.onSetup = function () {
	// Parent method
	ve.ce.MWScoreNode.super.prototype.onSetup.call( this );

	// DOM changes
	this.$element.addClass( 've-ce-mwScoreNode' );
};

/**
 * @inheritdoc ve.ce.GeneratedContentNode
 */
ve.ce.MWScoreNode.prototype.validateGeneratedContents = function ( $element ) {
	// eslint-disable-next-line no-jquery/no-class-state
	if ( $element.is( 'div' ) && $element.hasClass( 'mw-ext-score-error' ) ) {
		return false;
	}
	return true;
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWScoreNode );
