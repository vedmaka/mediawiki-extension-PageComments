<?php

namespace MediaWiki\Extension\PageComments\Hooks;

use DatabaseUpdater;

class SchemaHooks {

	/**
	 * Create PageComments tables.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
		$base = dirname( __DIR__, 2 );
		$dbType = $updater->getDB()->getType();

		if ( $dbType === 'postgres' ) {
			$threadFile = "{$base}/sql/postgres/pagecomments_thread.sql";
			$commentFile = "{$base}/sql/postgres/pagecomments_comment.sql";
		} elseif ( $dbType === 'sqlite' ) {
			$threadFile = "{$base}/sql/sqlite/pagecomments_thread.sql";
			$commentFile = "{$base}/sql/sqlite/pagecomments_comment.sql";
		} else {
			// mysql
			$threadFile = "{$base}/sql/pagecomments_thread.sql";
			$commentFile = "{$base}/sql/pagecomments_comment.sql";
		}

		$updater->addExtensionTable( 'pagecomments_thread', $threadFile );
		$updater->addExtensionTable( 'pagecomments_comment', $commentFile );
	}
}
