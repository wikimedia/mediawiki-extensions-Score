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

/**
 * Score exception
 */
class ScoreException extends Exception {

	/**
	 * @param Message $message Message to create error message from. Should have one $1 parameter.
	 * @param int $code optionally, an error code.
	 * @param Exception|null $previous Exception that caused this exception.
	 */
	public function __construct( $message, $code = 0, Exception $previous = null ) {
		parent::__construct( $message->inContentLanguage()->parse(), $code, $previous );
	}

	/**
	 * Auto-renders exception as HTML error message in the wiki's content
	 * language.
	 *
	 * @return string Error message HTML.
	 */
	public function  __toString() {
		return Html::rawElement(
			'div',
			[ 'class' => [ 'errorbox', 'mw-ext-score-error' ] ],
			$this->getMessage()
		);
	}

	/**
	 * Whether to add a tracking category
	 *
	 * @return bool
	 */
	public function isTracked() {
		return true;
	}

}
