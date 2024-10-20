<?php
/**
 * Deletes score files from storage
 *
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
use MediaWiki\Status\Status;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script that deletes old score files from storage
 *
 * @ingroup Maintenance
 */
class DeleteOldScoreFiles extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Deletes old score files from storage" );
		$this->addOption(
			"date",
			'Delete score files that were created before this date (e.g. 20170101000000)',
			true,
			true
		);
		$this->requireExtension( "Score" );
	}

	public function execute() {
		$backend = Score::getBackend();
		$dir = $backend->getRootStoragePath() . '/score-render';

		$filesToDelete = [];
		$deleteDate = $this->getOption( 'date' );
		foreach (
			$backend->getFileList( [ 'dir' => $dir, 'adviseStat' => true ] ) as $file
		) {
			$fullPath = $dir . '/' . $file;
			$timestamp = $backend->getFileTimestamp( [ 'src' => $fullPath ] );
			if ( $timestamp < $deleteDate ) {
				$filesToDelete[] = [ 'op' => 'delete', 'src' => $fullPath, ];
			}
		}

		$count = count( $filesToDelete );

		if ( !$count ) {
			$this->output( "No old score files to delete!\n" );
			return;
		}

		$this->output( "$count old score files to be deleted.\n" );

		$deletedCount = 0;
		foreach ( array_chunk( $filesToDelete, 1000 ) as $chunk ) {
			$ret = $backend->doQuickOperations( $chunk );

			if ( $ret->isOK() ) {
				$deletedCount += count( $chunk );
				$this->output( "$deletedCount...\n" );
			} else {
				$status = Status::wrap( $ret );
				$this->output( "Deleting old score files errored.\n" );
				$this->error( $status->getWikiText( false, false, 'en' ) );
			}
		}

		$this->output( "$deletedCount old score files deleted.\n" );
	}
}

$maintClass = DeleteOldScoreFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
