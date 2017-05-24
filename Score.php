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
 * Score extension
 *
 * @file
 * @ingroup Extensions
 *
 * @author Alexander Klauer <Graf.Zahl@gmx.net>
 * @license GPL v3 or later
 * @version 0.2
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Score' );
	$wgMessagesDirs['Score'] = __DIR__ . '/i18n';
} else {
	die( 'This version of the Score extension requires MediaWiki 1.25+' );
}
