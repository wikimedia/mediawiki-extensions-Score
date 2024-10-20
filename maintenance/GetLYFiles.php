<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Maintenance
 */

use MediaWiki\Extension\Score\Score;
use MediaWiki\Maintenance\Maintenance;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/**
 * @ingroup Maintenance
 */
class GetLYFiles extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			"Gets a count of the number of ly files and optionally writes them to disk"
		);
		$this->addOption(
			'date',
			'Get ly files that were created after this date (e.g. 20170101000000)',
			true,
			true
		);

		$this->addOption(
			'outputdir',
			'Saves ly files matching date to this directory',
			false,
			true
		);
		$this->requireExtension( "Score" );
	}

	public function execute() {
		$backend = Score::getBackend();
		$baseStoragePath = $backend->getRootStoragePath() . '/score-render';

		$files = $backend->getFileList( [ 'dir' => $baseStoragePath, 'adviseStat' => true ] );
		$count = iterator_count( $files );
		$this->output( "Total files (all extensions): $count\n" );

		$date = $this->getOption( 'date' );

		$targetFiles = [];

		foreach ( $files as $file ) {
			$fullPath = $baseStoragePath . '/' . $file;

			if (
				pathinfo( $file, PATHINFO_EXTENSION ) === 'ly' &&
				$backend->getFileTimestamp( [ 'src' => $fullPath ] ) >= $date
			) {
				$targetFiles[] = $fullPath;
			}
		}

		$targetFileCount = count( $targetFiles );

		$this->output( "{$targetFileCount} ly files created on or after {$date}\n" );

		if ( $this->hasOption( 'outputdir' ) ) {
			$outputDir = $this->getOption( 'outputdir' );
			$this->output( "Outputting ly files to {$outputDir}:\n" );

			$count = 0;
			foreach ( array_chunk( $targetFiles, 1000 ) as $chunk ) {
				$fileContents = $backend->getFileContentsMulti(
					[
						'srcs' => $chunk,
						'parallelize' => true,
					]
				);

				foreach ( $fileContents as $path => $contents ) {
					$pathNoPrefix = str_replace( $baseStoragePath . '/', '', $path );
					wfMkdirParents( $outputDir . '/' . dirname( $pathNoPrefix ) );
					file_put_contents( $outputDir . '/' . $pathNoPrefix, $contents );
				}
				$count += count( $chunk );
				$this->output( "$count...\n" );
			}

			$this->output( "Done!\n" );
		}
	}

}

$maintClass = GetLYFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
