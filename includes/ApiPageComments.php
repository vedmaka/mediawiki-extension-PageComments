<?php

namespace MediaWiki\Extension\PageComments;

use MediaWiki\Api\ApiBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiPageComments extends ApiBase {
	use ApiPageCommentsListTrait;
	use ApiPageCommentsCommentMutationTrait;

	private const ACTION_LIST = 'list';
	private const ACTION_CREATE = 'create';
	private const ACTION_REPLY = 'reply';
	private const ACTION_EDIT_COMMENT = 'editcomment';
	private const ACTION_DELETE_COMMENT = 'deletecomment';
	private const ACTION_RESOLVE = 'resolve';
	private const ACTION_REOPEN = 'reopen';

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$action = $params['pcaction'];

		if ( $action === self::ACTION_LIST ) {
			$this->runList( (int)$params['pageid'] );
			return;
		}

		if ( $action === self::ACTION_DELETE_COMMENT ) {
			$this->runDeleteComment( (int)$params['commentid'] );
			return;
		}

		if ( $action === self::ACTION_EDIT_COMMENT ) {
			$this->runEditComment( (int)$params['commentid'], (string)$params['body'] );
			return;
		}

		$this->assertCanWrite();

		if ( $action === self::ACTION_CREATE ) {
			$this->runCreate(
				(int)$params['pageid'],
				(string)$params['anchor'],
				(string)$params['body']
			);
			return;
		}

		if ( $action === self::ACTION_REPLY ) {
			$this->runReply(
				(int)$params['threadid'],
				(string)$params['body'],
				$params['parentcommentid'] !== null ? (int)$params['parentcommentid'] : null
			);
			return;
		}

		if ( $action === self::ACTION_RESOLVE ) {
			$this->runSetThreadState( (int)$params['threadid'], 'resolved' );
			return;
		}

		if ( $action === self::ACTION_REOPEN ) {
			$this->runSetThreadState( (int)$params['threadid'], 'open' );
			return;
		}

		$this->dieWithError( 'pagecomments-api-error-unknown-action', 'pagecomments-unknown-action' );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'pcaction' => [
				ParamValidator::PARAM_TYPE => [
					self::ACTION_LIST,
					self::ACTION_CREATE,
					self::ACTION_REPLY,
					self::ACTION_EDIT_COMMENT,
					self::ACTION_DELETE_COMMENT,
					self::ACTION_RESOLVE,
					self::ACTION_REOPEN
				],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'pageid' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'threadid' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'parentcommentid' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'commentid' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'anchor' => [
				ParamValidator::PARAM_TYPE => 'text',
			],
			'body' => [
				ParamValidator::PARAM_TYPE => 'text',
			],
			'token' => null,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		$action = $this->getMain()->getRequest()->getVal( 'pcaction', self::ACTION_LIST );
		return $action === self::ACTION_LIST ? false : 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		$action = $this->getMain()->getRequest()->getVal( 'pcaction', self::ACTION_LIST );
		return $action !== self::ACTION_LIST;
	}

	private function runCreate( int $pageId, string $anchorJson, string $body ): void {
		if ( $pageId <= 0 ) {
			$this->dieWithError( 'pagecomments-api-error-missing-pageid', 'pagecomments-missing-pageid' );
		}

		$title = $this->getTitleFromPageId( $pageId );
		$this->assertNamespaceEnabledForTitle( $title );

		$anchor = $this->normalizeAnchor( $anchorJson );
		$this->assertNoAnchorOverlap( $pageId, (int)$title->getNamespace(), $anchor );
		$normalizedBody = $this->normalizeBody( $body );
		$user = $this->getUser();
		$actorId = $user->getActorId();
		if ( !$actorId ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'notloggedin' );
		}

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$timestamp = $dbw->timestamp();
		$anchorStored = json_encode( $anchor );
		if ( $anchorStored === false ) {
			$this->dieWithError( 'pagecomments-api-error-invalid-anchor', 'pagecomments-invalid-anchor' );
		}

		$dbw->startAtomic( __METHOD__ );
		$dbw->insert(
			'pagecomments_thread',
			[
				'pct_page_id' => $pageId,
				'pct_namespace' => (int)$title->getNamespace(),
				'pct_rev_id' => (int)$title->getLatestRevID(),
				'pct_anchor_json' => $anchorStored,
				'pct_anchor_excerpt' => $anchor['exact'],
				'pct_state' => 'open',
				'pct_actor_id' => $actorId,
				'pct_created_at' => $timestamp,
				'pct_updated_at' => $timestamp,
			],
			__METHOD__
		);
		$threadId = (int)$dbw->insertId();

		$dbw->insert(
			'pagecomments_comment',
			[
				'pcc_thread_id' => $threadId,
				'pcc_parent_comment_id' => null,
				'pcc_actor_id' => $actorId,
				'pcc_body' => $normalizedBody,
				'pcc_created_at' => $timestamp,
				'pcc_deleted_at' => null,
			],
			__METHOD__
		);
		$commentId = (int)$dbw->insertId();
		$dbw->endAtomic( __METHOD__ );

		$this->getResult()->addValue( null, 'pagecomments', [
			'action' => self::ACTION_CREATE,
			'threadId' => $threadId,
			'commentId' => $commentId,
		] );
	}

	private function runReply( int $threadId, string $body, ?int $parentCommentId ): void {
		if ( $threadId <= 0 ) {
			$this->dieWithError( 'pagecomments-api-error-missing-threadid', 'pagecomments-missing-threadid' );
		}

		$normalizedBody = $this->normalizeBody( $body );
		$user = $this->getUser();
		$actorId = $user->getActorId();
		if ( !$actorId ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'notloggedin' );
		}

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$thread = $dbw->newSelectQueryBuilder()
			->select( [ 'pct_id', 'pct_page_id', 'pct_namespace' ] )
			->from( 'pagecomments_thread' )
			->where( [ 'pct_id' => $threadId ] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$thread ) {
			$this->dieWithError( 'pagecomments-api-error-thread-not-found', 'pagecomments-thread-not-found' );
		}
		$this->assertNamespaceEnabled( (int)$thread->pct_namespace );

		if ( $parentCommentId !== null ) {
			$parentRow = $dbw->newSelectQueryBuilder()
				->select( [ 'pcc_id' ] )
				->from( 'pagecomments_comment' )
				->where( [
					'pcc_id' => $parentCommentId,
					'pcc_thread_id' => $threadId
				] )
				->caller( __METHOD__ )
				->fetchRow();
			if ( !$parentRow ) {
				$this->dieWithError(
					'pagecomments-api-error-parent-comment-not-found',
					'pagecomments-parent-comment-not-found'
				);
			}
		}

		$timestamp = $dbw->timestamp();
		$dbw->startAtomic( __METHOD__ );
		$dbw->insert(
			'pagecomments_comment',
			[
				'pcc_thread_id' => $threadId,
				'pcc_parent_comment_id' => $parentCommentId,
				'pcc_actor_id' => $actorId,
				'pcc_body' => $normalizedBody,
				'pcc_created_at' => $timestamp,
				'pcc_deleted_at' => null,
			],
			__METHOD__
		);
		$commentId = (int)$dbw->insertId();

		$dbw->update(
			'pagecomments_thread',
			[ 'pct_updated_at' => $timestamp ],
			[ 'pct_id' => $threadId ],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );

		$this->getResult()->addValue( null, 'pagecomments', [
			'action' => self::ACTION_REPLY,
			'threadId' => $threadId,
			'commentId' => $commentId,
		] );
	}

	private function runSetThreadState( int $threadId, string $state ): void {
		if ( $threadId <= 0 ) {
			$this->dieWithError( 'pagecomments-api-error-missing-threadid', 'pagecomments-missing-threadid' );
		}

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$thread = $dbw->newSelectQueryBuilder()
			->select( [ 'pct_id', 'pct_namespace' ] )
			->from( 'pagecomments_thread' )
			->where( [ 'pct_id' => $threadId ] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$thread ) {
			$this->dieWithError( 'pagecomments-api-error-thread-not-found', 'pagecomments-thread-not-found' );
		}
		$this->assertNamespaceEnabled( (int)$thread->pct_namespace );

		// Preserve list ordering: resolving/reopening should not bump thread recency.
		$dbw->update(
			'pagecomments_thread',
			[ 'pct_state' => $state ],
			[ 'pct_id' => $threadId ],
			__METHOD__
		);

		$this->getResult()->addValue( null, 'pagecomments', [
			'action' => $state === 'open' ? self::ACTION_REOPEN : self::ACTION_RESOLVE,
			'threadId' => $threadId,
			'state' => $state,
		] );
	}

	private function assertCanWrite(): void {
		$user = $this->getUser();
		if ( !$user->isNamed() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'notloggedin' );
		}

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$permissionManager->userHasRight( $user, 'pagecomments-write' ) ) {
			$this->dieWithError(
				'pagecomments-api-error-permission-denied',
				'pagecomments-permission-denied'
			);
		}
	}

	private function getTitleFromPageId( int $pageId ): Title {
		$title = Title::newFromID( $pageId );
		if ( !$title || !$title->exists() ) {
			$this->dieWithError( 'pagecomments-api-error-invalid-page', 'pagecomments-invalid-page' );
		}

		return $title;
	}

	private function assertNamespaceEnabledForTitle( Title $title ): void {
		$this->assertNamespaceEnabled( (int)$title->getNamespace() );
	}

	private function assertNamespaceEnabled( int $namespace ): void {
		if ( !$this->isNamespaceEnabled( $namespace ) ) {
			$this->dieWithError(
				'pagecomments-api-error-main-namespace-only',
				'pagecomments-main-namespace-only'
			);
		}
	}

	private function isNamespaceEnabled( int $namespace ): bool {
		$enabledNamespaces = $this->getEnabledNamespaces();
		return in_array( $namespace, $enabledNamespaces, true );
	}

	private function getEnabledNamespaces(): array {
		$raw = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'PageCommentsEnabledNamespaces' );
		if ( !is_array( $raw ) ) {
			return [];
		}
		$enabled = [];
		foreach ( $raw as $value ) {
			$namespace = (int)$value;
			$enabled[$namespace] = true;
		}
		return array_keys( $enabled );
	}

	private function normalizeBody( string $body ): string {
		$normalized = trim( $body );
		if ( $normalized === '' ) {
			$this->dieWithError( 'pagecomments-api-error-empty-body', 'pagecomments-empty-body' );
		}

		$maxLength = (int)MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'PageCommentsMaxCommentLength' );
		if ( mb_strlen( $normalized ) > $maxLength ) {
			$this->dieWithError(
				'pagecomments-api-error-body-too-long',
				'pagecomments-body-too-long'
			);
		}

		return $normalized;
	}

	/**
	 * @param string $anchorJson
	 * @return array
	 */
	private function normalizeAnchor( string $anchorJson ): array {
		$anchor = json_decode( $anchorJson, true );
		if ( !is_array( $anchor ) ) {
			$this->dieWithError( 'pagecomments-api-error-invalid-anchor', 'pagecomments-invalid-anchor' );
		}

		$exact = isset( $anchor['exact'] ) ? trim( (string)$anchor['exact'] ) : '';
		if ( $exact === '' ) {
			$this->dieWithError( 'pagecomments-api-error-invalid-anchor', 'pagecomments-invalid-anchor' );
		}

		$maxAnchorLength = (int)MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'PageCommentsMaxAnchorLength' );
		if ( mb_strlen( $exact ) > $maxAnchorLength ) {
			$this->dieWithError( 'pagecomments-api-error-anchor-too-long', 'pagecomments-anchor-too-long' );
		}

		$start = isset( $anchor['start'] ) ? (int)$anchor['start'] : -1;
		$end = isset( $anchor['end'] ) ? (int)$anchor['end'] : -1;
		if ( $start < 0 || $end <= $start ) {
			$this->dieWithError( 'pagecomments-api-error-invalid-anchor', 'pagecomments-invalid-anchor' );
		}

		$normalized = [
			'exact' => $exact,
			'prefix' => isset( $anchor['prefix'] ) ? (string)$anchor['prefix'] : '',
			'suffix' => isset( $anchor['suffix'] ) ? (string)$anchor['suffix'] : '',
			'start' => $start,
			'end' => $end,
		];

		return $normalized;
	}

	private function assertNoAnchorOverlap( int $pageId, int $namespace, array $anchor ): void {
		$start = (int)$anchor['start'];
		$end = (int)$anchor['end'];
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$rows = $dbw->newSelectQueryBuilder()
			->select( [ 'pct_anchor_json' ] )
			->from( 'pagecomments_thread' )
			->where( [
				'pct_page_id' => $pageId,
				'pct_namespace' => $namespace
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $rows as $row ) {
			$existing = $this->decodeAnchor( (string)$row->pct_anchor_json );
			if ( !$existing || !isset( $existing['start'] ) || !isset( $existing['end'] ) ) {
				continue;
			}
			$existingStart = (int)$existing['start'];
			$existingEnd = (int)$existing['end'];
			if ( $existingStart < 0 || $existingEnd <= $existingStart ) {
				continue;
			}
			if ( $start < $existingEnd && $existingStart < $end ) {
				$this->dieWithError( 'pagecomments-api-error-anchor-overlap', 'pagecomments-anchor-overlap' );
			}
		}
	}

}
