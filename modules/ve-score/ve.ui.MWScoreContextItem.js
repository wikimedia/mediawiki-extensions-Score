/*!
 * VisualEditor MWScoreContextItem class.
 *
 * @copyright 2015 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a score node.
 *
 * @class
 * @extends ve.ui.MWLatexContextItem
 *
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWScoreContextItem = function VeUiMWScoreContextItem() {
	// Parent constructor
	ve.ui.MWScoreContextItem.super.apply( this, arguments );

	this.$element.addClass( 've-ui-mwScoreContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWScoreContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWScoreContextItem.static.name = 'score';

ve.ui.MWScoreContextItem.static.icon = 'score';

ve.ui.MWScoreContextItem.static.label = OO.ui.deferMsg( 'score-visualeditor-mwscoredialog-title' );

ve.ui.MWScoreContextItem.static.modelClasses = [ ve.dm.MWScoreNode ];

ve.ui.MWScoreContextItem.static.embeddable = false;

ve.ui.MWScoreContextItem.static.commandName = 'score';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWScoreContextItem.prototype.getDescription = function () {
	return ve.ce.nodeFactory.getDescription( this.model );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWScoreContextItem );
