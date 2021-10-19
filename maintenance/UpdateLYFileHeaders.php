<?php

/*
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup Maintenance
 */
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/**
 * @ingroup Maintenance
 */
class UpdateLYFileHeaders extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			"Updates the file headers of lilypond files"
		);
		$this->requireExtension( "Score" );
	}

	public function execute() {
		$headers = [ 'Content-Type' => 'text/x-lilypond; charset=utf-8' ];
		$backend = Score::getBackend();
		$baseStoragePath = $backend->getRootStoragePath() . '/score-render';

		$files = $backend->getFileList( [ 'dir' => $baseStoragePath ] );
		$count = iterator_count( $files );
		$this->output( "Total files (all extensions): $count\n" );

		$backendOperations = [];
		foreach ( $files as $file ) {
			$fullPath = $baseStoragePath . '/' . $file;

			if (
				pathinfo( $file, PATHINFO_EXTENSION ) === 'ly'
			) {
				$backendOperations[] = [
					'op' => 'describe', 'src' => $fullPath, 'headers' => $headers
				];
			}
		}
		$count = count( $backendOperations );
		$this->output( "Updating the headers of $count lilypond files\n" );

		foreach ( array_chunk( $backendOperations, 1000 ) as $chunk ) {
			$status = $backend->doQuickOperations( $chunk );

			if ( !$status->isGood() ) {
				$this->error( "Encountered error: " . print_r( $status, true ) );
			}
		}
		$this->output( "Done!\n" );
	}

}

$maintClass = UpdateLYFileHeaders::class;
require_once RUN_MAINTENANCE_IF_MAIN;
