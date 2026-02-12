<?php

namespace MediaWiki\Extension\PageComments;

use MediaWiki\MediaWikiServices;

trait ApiPageCommentsCommentMutationTrait {

	private function runEditComment( int $commentId, string $body ): void {
		if ( $commentId <= 0 ) {
			$this->dieWithError( 'pagecomments-api-error-missing-commentid', 'pagecomments-missing-commentid' );
		}
		$normalizedBody = $this->normalizeBody( $body );
		$user = $this->getUser();
		if ( !$user->isNamed() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'notloggedin' );
		}
		$userActorId = (int)$user->getActorId();
		$isModerator = MediaWikiServices::getInstance()
			->getPermissionManager()
			->userHasRight( $user, 'pagecomments-moderate' );
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$row = $dbw->newSelectQueryBuilder()
			->select( [ 'pcc_id', 'pcc_thread_id', 'pcc_actor_id', 'pct_namespace' ] )
			->from( 'pagecomments_comment' )
			->join( 'pagecomments_thread', null, 'pct_id = pcc_thread_id' )
			->where( [
				'pcc_id' => $commentId,
				'pcc_deleted_at' => null
			] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$row ) {
			$this->dieWithError( 'pagecomments-api-error-comment-not-found', 'pagecomments-comment-not-found' );
		}
		if ( (int)$row->pct_namespace !== NS_MAIN ) {
			$this->dieWithError( 'pagecomments-api-error-main-namespace-only', 'pagecomments-main-namespace-only' );
		}
		if ( !$isModerator && ( $userActorId <= 0 || $userActorId !== (int)$row->pcc_actor_id ) ) {
			$this->dieWithError(
				'pagecomments-api-error-permission-denied',
				'pagecomments-permission-denied'
			);
		}
		$threadId = (int)$row->pcc_thread_id;
		$dbw->startAtomic( __METHOD__ );
		$dbw->update(
			'pagecomments_comment',
			[ 'pcc_body' => $normalizedBody ],
			[
				'pcc_id' => $commentId,
				'pcc_deleted_at' => null
			],
			__METHOD__
		);
		// Keep thread ordering stable: editing comment text should not bump thread recency.
		$dbw->endAtomic( __METHOD__ );
		$this->getResult()->addValue( null, 'pagecomments', [
			'action' => self::ACTION_EDIT_COMMENT,
			'commentId' => $commentId,
			'threadId' => $threadId,
		] );
	}

	private function runDeleteComment( int $commentId ): void {
		if ( $commentId <= 0 ) {
			$this->dieWithError( 'pagecomments-api-error-missing-commentid', 'pagecomments-missing-commentid' );
		}
		$user = $this->getUser();
		if ( !$user->isNamed() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'notloggedin' );
		}
		$userActorId = (int)$user->getActorId();
		$isModerator = MediaWikiServices::getInstance()
			->getPermissionManager()
			->userHasRight( $user, 'pagecomments-moderate' );
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$row = $dbw->newSelectQueryBuilder()
			->select( [ 'pcc_id', 'pcc_thread_id', 'pcc_actor_id', 'pct_namespace' ] )
			->from( 'pagecomments_comment' )
			->join( 'pagecomments_thread', null, 'pct_id = pcc_thread_id' )
			->where( [
				'pcc_id' => $commentId,
				'pcc_deleted_at' => null
			] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$row ) {
			$this->dieWithError( 'pagecomments-api-error-comment-not-found', 'pagecomments-comment-not-found' );
		}
		if ( (int)$row->pct_namespace !== NS_MAIN ) {
			$this->dieWithError( 'pagecomments-api-error-main-namespace-only', 'pagecomments-main-namespace-only' );
		}
		if ( !$isModerator && ( $userActorId <= 0 || $userActorId !== (int)$row->pcc_actor_id ) ) {
			$this->dieWithError(
				'pagecomments-api-error-permission-denied',
				'pagecomments-permission-denied'
			);
		}
		$threadId = (int)$row->pcc_thread_id;
		$timestamp = $dbw->timestamp();
		$dbw->startAtomic( __METHOD__ );
		$dbw->update(
			'pagecomments_comment',
			[ 'pcc_deleted_at' => $timestamp ],
			[
				'pcc_id' => $commentId,
				'pcc_deleted_at' => null
			],
			__METHOD__
		);
		$remaining = (int)$dbw->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'pagecomments_comment' )
			->where( [
				'pcc_thread_id' => $threadId,
				'pcc_deleted_at' => null
			] )
			->caller( __METHOD__ )
			->fetchField();
		$threadDeleted = false;
		if ( $remaining <= 0 ) {
			// Drop empty thread so anchor highlight and overlap checks are cleared immediately.
			$dbw->delete( 'pagecomments_thread', [ 'pct_id' => $threadId ], __METHOD__ );
			$threadDeleted = true;
		} else {
			$dbw->update(
				'pagecomments_thread',
				[ 'pct_updated_at' => $timestamp ],
				[ 'pct_id' => $threadId ],
				__METHOD__
			);
		}
		$dbw->endAtomic( __METHOD__ );
		$this->getResult()->addValue( null, 'pagecomments', [
			'action' => self::ACTION_DELETE_COMMENT,
			'commentId' => $commentId,
			'threadId' => $threadId,
			'threadDeleted' => $threadDeleted,
		] );
	}
}
