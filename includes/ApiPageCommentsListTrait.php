<?php

namespace MediaWiki\Extension\PageComments;

use MediaWiki\MediaWikiServices;

trait ApiPageCommentsListTrait {

	private function runList( int $pageId ): void {
		if ( $pageId <= 0 ) {
			$this->dieWithError( 'pagecomments-api-error-missing-pageid', 'pagecomments-missing-pageid' );
		}
		$title = $this->getTitleFromPageId( $pageId );
		$this->assertMainNamespace( $title );
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$user = $this->getUser();
		$userActorId = (int)$user->getActorId();
		$isModerator = MediaWikiServices::getInstance()
			->getPermissionManager()
			->userHasRight( $user, 'pagecomments-moderate' );
		$threadRows = $dbr->newSelectQueryBuilder()
			->select( [
				'pct_id',
				'pct_page_id',
				'pct_rev_id',
				'pct_anchor_json',
				'pct_anchor_excerpt',
				'pct_state',
				'pct_created_at',
				'pct_updated_at',
				'thread_actor_name' => 'thread_actor.actor_name',
			] )
			->from( 'pagecomments_thread' )
			->join( 'actor', 'thread_actor', 'thread_actor.actor_id = pct_actor_id' )
			->where( [
				'pct_page_id' => $pageId,
				'pct_namespace' => NS_MAIN
			] )
			->orderBy( 'pct_updated_at', 'DESC' )
			->caller( __METHOD__ )
			->fetchResultSet();
		$threads = [];
		$threadIds = [];
		foreach ( $threadRows as $row ) {
			$threadId = (int)$row->pct_id;
			$threadIds[] = $threadId;
			$threads[$threadId] = [
				'id' => $threadId,
				'pageId' => (int)$row->pct_page_id,
				'revId' => (int)$row->pct_rev_id,
				'state' => (string)$row->pct_state,
				'createdAt' => (string)$row->pct_created_at,
				'updatedAt' => (string)$row->pct_updated_at,
				'actorName' => (string)$row->thread_actor_name,
				'anchor' => $this->decodeAnchor( (string)$row->pct_anchor_json ),
				'excerpt' => (string)$row->pct_anchor_excerpt,
				'comments' => [],
			];
		}
		if ( $threadIds ) {
			$commentRows = $dbr->newSelectQueryBuilder()
				->select( [
					'pcc_id',
					'pcc_thread_id',
					'pcc_parent_comment_id',
					'pcc_actor_id',
					'pcc_body',
					'pcc_created_at',
					'comment_actor_name' => 'comment_actor.actor_name',
				] )
				->from( 'pagecomments_comment' )
				->join( 'actor', 'comment_actor', 'comment_actor.actor_id = pcc_actor_id' )
				->where( [
					'pcc_thread_id' => $threadIds,
					'pcc_deleted_at' => null
				] )
				->orderBy( 'pcc_created_at', 'ASC' )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $commentRows as $row ) {
				$threadId = (int)$row->pcc_thread_id;
				if ( !isset( $threads[$threadId] ) ) {
					continue;
				}
				$canManage = $isModerator || ( $userActorId > 0 && $userActorId === (int)$row->pcc_actor_id );
				$threads[$threadId]['comments'][] = [
					'id' => (int)$row->pcc_id,
					'threadId' => $threadId,
					'parentCommentId' => $row->pcc_parent_comment_id !== null ?
						(int)$row->pcc_parent_comment_id :
						null,
					'body' => (string)$row->pcc_body,
					'createdAt' => (string)$row->pcc_created_at,
					'actorName' => (string)$row->comment_actor_name,
					'canManage' => $canManage,
					'canEdit' => $canManage,
					'canDelete' => $canManage,
				];
			}
			foreach ( $threads as $threadId => $thread ) {
				if ( !$thread['comments'] ) {
					unset( $threads[$threadId] );
				}
			}
		}
		$this->getResult()->addValue( null, 'pagecomments', [
			'threads' => array_values( $threads ),
		] );
	}

	private function decodeAnchor( string $anchorJson ): ?array {
		$anchor = json_decode( $anchorJson, true );
		return is_array( $anchor ) ? $anchor : null;
	}
}
