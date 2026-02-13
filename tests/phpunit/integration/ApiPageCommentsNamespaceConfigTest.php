<?php

use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\PageComments\ApiPageComments
 * @group Database
 * @group medium
 * @group API
 * @group extension-PageComments
 */
class ApiPageCommentsNamespaceConfigTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'PageCommentsEnabledNamespaces', [ NS_PROJECT ] );
	}

	/**
	 * Create and list operations succeed when the page namespace is enabled.
	 */
	public function testCreateAndListInEnabledNamespace(): void {
		$pageId = $this->getPageIdInNamespace( NS_PROJECT );
		$author = $this->getTestUser()->getAuthority();

		[ $createResult ] = $this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'create',
			'pageid' => $pageId,
			'anchor' => $this->buildAnchorJson(),
			'body' => 'Thread in project namespace',
		], null, $author, 'csrf' );

		$payload = $createResult['pagecomments'];
		$this->assertSame( 'create', $payload['action'] );
		$this->assertGreaterThan( 0, (int)$payload['threadId'] );
		$this->assertGreaterThan( 0, (int)$payload['commentId'] );

		$threadId = (int)$payload['threadId'];

		$db = $this->getDb();
		$storedNamespace = (int)$db->newSelectQueryBuilder()
			->select( 'pct_namespace' )
			->from( 'pagecomments_thread' )
			->where( [ 'pct_id' => $threadId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertSame( NS_PROJECT, $storedNamespace );

		[ $listResult ] = $this->doApiRequest( [
			'action' => 'pagecomments',
			'pcaction' => 'list',
			'pageid' => $pageId,
		], null, false, $author );

		$threads = $listResult['pagecomments']['threads'];
		$this->assertCount( 1, $threads );
		$this->assertSame( $threadId, (int)$threads[0]['id'] );
		$this->assertCount( 1, $threads[0]['comments'] );
	}

	/**
	 * Create operation fails with namespace error when the page namespace is disabled.
	 */
	public function testCreateRejectedInDisabledNamespace(): void {
		$pageId = $this->getPageIdInNamespace( NS_MAIN );
		$author = $this->getTestUser()->getAuthority();

		$this->expectApiErrorCode( 'pagecomments-main-namespace-only' );
		$this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'create',
			'pageid' => $pageId,
			'anchor' => $this->buildAnchorJson(),
			'body' => 'Should fail on disabled namespace',
		], null, $author, 'csrf' );
	}

	/**
	 * List operation fails with namespace error when the page namespace is disabled.
	 */
	public function testListRejectedInDisabledNamespace(): void {
		$pageId = $this->getPageIdInNamespace( NS_MAIN );
		$author = $this->getTestUser()->getAuthority();

		$this->expectApiErrorCode( 'pagecomments-main-namespace-only' );
		$this->doApiRequest( [
			'action' => 'pagecomments',
			'pcaction' => 'list',
			'pageid' => $pageId,
		], null, false, $author );
	}

	/**
	 * Reply/edit/resolve/delete mutations work end-to-end in an enabled namespace.
	 */
	public function testMutationsWorkInEnabledNamespace(): void {
		$pageId = $this->getPageIdInNamespace( NS_PROJECT );
		$author = $this->getTestUser()->getAuthority();

		[ $createResult ] = $this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'create',
			'pageid' => $pageId,
			'anchor' => $this->buildAnchorJson(),
			'body' => 'Root comment',
		], null, $author, 'csrf' );

		$threadId = (int)$createResult['pagecomments']['threadId'];
		$rootCommentId = (int)$createResult['pagecomments']['commentId'];

		[ $replyResult ] = $this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'reply',
			'threadid' => $threadId,
			'body' => 'Reply comment',
		], null, $author, 'csrf' );
		$replyCommentId = (int)$replyResult['pagecomments']['commentId'];
		$this->assertGreaterThan( $rootCommentId, $replyCommentId );

		[ $editResult ] = $this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'editcomment',
			'commentid' => $replyCommentId,
			'body' => 'Edited reply comment',
		], null, $author, 'csrf' );
		$this->assertSame( 'editcomment', $editResult['pagecomments']['action'] );

		[ $resolveResult ] = $this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'resolve',
			'threadid' => $threadId,
		], null, $author, 'csrf' );
		$this->assertSame( 'resolved', $resolveResult['pagecomments']['state'] );

		[ $deleteResult ] = $this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'deletecomment',
			'commentid' => $replyCommentId,
		], null, $author, 'csrf' );
		$this->assertSame( 'deletecomment', $deleteResult['pagecomments']['action'] );
		$this->assertFalse( (bool)$deleteResult['pagecomments']['threadDeleted'] );

		[ $listResult ] = $this->doApiRequest( [
			'action' => 'pagecomments',
			'pcaction' => 'list',
			'pageid' => $pageId,
		], null, false, $author );
		$threads = $listResult['pagecomments']['threads'];
		$this->assertCount( 1, $threads );
		$this->assertSame( 'resolved', $threads[0]['state'] );
		$this->assertCount( 1, $threads[0]['comments'] );
	}

	/**
	 * Reopen mutation switches a resolved thread back to open in an enabled namespace.
	 */
	public function testReopenWorksInEnabledNamespace(): void {
		$pageId = $this->getPageIdInNamespace( NS_PROJECT );
		$author = $this->getTestUser()->getAuthority();

		[ $createResult ] = $this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'create',
			'pageid' => $pageId,
			'anchor' => $this->buildAnchorJson(),
			'body' => 'Root comment',
		], null, $author, 'csrf' );
		$threadId = (int)$createResult['pagecomments']['threadId'];

		[ $resolveResult ] = $this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'resolve',
			'threadid' => $threadId,
		], null, $author, 'csrf' );
		$this->assertSame( 'resolved', $resolveResult['pagecomments']['state'] );

		[ $reopenResult ] = $this->doApiRequestWithToken( [
			'action' => 'pagecomments',
			'pcaction' => 'reopen',
			'threadid' => $threadId,
		], null, $author, 'csrf' );
		$this->assertSame( 'reopen', $reopenResult['pagecomments']['action'] );
		$this->assertSame( 'open', $reopenResult['pagecomments']['state'] );

		[ $listResult ] = $this->doApiRequest( [
			'action' => 'pagecomments',
			'pcaction' => 'list',
			'pageid' => $pageId,
		], null, false, $author );
		$threads = $listResult['pagecomments']['threads'];
		$this->assertCount( 1, $threads );
		$this->assertSame( $threadId, (int)$threads[0]['id'] );
		$this->assertSame( 'open', $threads[0]['state'] );
	}

	private function getPageIdInNamespace( int $namespace ): int {
		$title = Title::newFromText(
			'PageComments Namespace ' . wfRandomString(),
			$namespace
		);
		$page = $this->getExistingTestPage( $title );
		return (int)$page->getTitle()->getArticleID();
	}

	private function buildAnchorJson(): string {
		return json_encode( [
			'exact' => 'Alpha',
			'prefix' => '',
			'suffix' => ' beta',
			'start' => 0,
			'end' => 5,
		] );
	}
}
