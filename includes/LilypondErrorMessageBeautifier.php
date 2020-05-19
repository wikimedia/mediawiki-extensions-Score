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

*/

/**
 * Convert the std error output of lilypond into a concise html form.
 */
class LilypondErrorMessageBeautifier {
	/**
	 * Lilyponds error reporting line regex, only the line with the error line, column and message.
	 */
	private const LILYPOND_ERR_REGEX = '/\.ly:(?<line>\d+):(?<column>\d+): error: (?<message>.+)$/m';

	private const BEAUTIFIED_ERR_FORMAT = "line %d - column %d:\n%s";
	private const BEAUTIFIED_ERR_SEPARATOR = "\n--------\n";

	/**
	 * @var int $scoreFirstLineOffset
	 *
	 * The line number where user's score input is inserted, within the final
	 * lilypond file that is passed to lilypond executable.
	 *
	 * The first line is assumed to start at the first column (no column offset).
	 */
	private $scoreFirstLineOffset;

	/**
	 * @param int $scoreFirstLineOffset
	 */
	public function __construct( $scoreFirstLineOffset = 0 ) {
		$this->scoreFirstLineOffset = $scoreFirstLineOffset;
	}

	/**
	 * Beautifies lilypond executale error messages by:
	 * - adjusting line numbers fit the user's input
	 * - stripping out all echoed erroneous code
	 * - stripping out unnecessary keywords
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	public function beautifyMessage( $message ) {
		if ( !preg_match_all(
			self::LILYPOND_ERR_REGEX,
			$message,
			$errorMatches,
			PREG_SET_ORDER
		) ) {
			return '';
		}

		$beautifiedMessages = [];

		foreach ( $errorMatches as $errorMatch ) {
			$beautifiedMessages[] = $this->formatErrorMatchLine( $errorMatch );
		}

		return implode( self::BEAUTIFIED_ERR_SEPARATOR, $beautifiedMessages );
	}

	/**
	 * @param array $errorMatch
	 * @return string
	 */
	private function formatErrorMatchLine( array $errorMatch ) {
		return sprintf(
			self::BEAUTIFIED_ERR_FORMAT,
			intval( $errorMatch[ 'line' ] ) - $this->scoreFirstLineOffset,
			intval( $errorMatch[ 'column' ] ),
			$errorMatch[ 'message' ]
		);
	}
}
