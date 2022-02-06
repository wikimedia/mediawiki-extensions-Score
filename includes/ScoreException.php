<?php
/*
	Score, a MediaWiki extension for rendering musical scores with LilyPond.
	Copyright © 2011 Alexander Klauer

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.

	To contact the author:
	<Graf.Zahl@gmx.net>
	http://en.wikisource.org/wiki/User_talk:GrafZahl
	https://github.com/TheCount/score

 */

namespace MediaWiki\Extension\Score;

use Exception;
use Html;
use Title;

/**
 * Score exception
 */
class ScoreException extends Exception {

	/** @var array */
	private $args;

	/**
	 * @param string $message Message key of error message Should have one $1 parameter.
	 * @param array $args Parameters to the message
	 */
	public function __construct( $message, array $args = [] ) {
		parent::__construct( $message );
		$this->args = $args;
	}

	/**
	 * Auto-renders exception as HTML error message in the wiki's content
	 * language.
	 *
	 * @return string Error message HTML.
	 */
	public function getHtml() {
		return Html::rawElement(
			'div',
			[ 'class' => $this->getCSSClasses() ],
			wfMessage( $this->getMessage(), ...$this->args )
				->inContentLanguage()
				->title( Title::makeTitle( NS_SPECIAL, 'Badtitle' ) )
				->parse()
		);
	}

	/**
	 * Get CSS classes that should apply to this error
	 *
	 * @return array
	 */
	protected function getCSSClasses(): array {
		return [ 'errorbox', 'mw-ext-score-error' ];
	}

	/**
	 * Whether to add a tracking category
	 *
	 * @return bool
	 */
	public function isTracked() {
		return true;
	}

	/**
	 * Key for use in statsd metrics
	 *
	 * @return string|bool false if it shouldn't be recorded
	 */
	public function getStatsdKey() {
		// Normalize message key into _ for statsd
		return str_replace( '-', '_', $this->getMessage() );
	}
}
