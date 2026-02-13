<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageComments\Hooks\PageDisplayHooks;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\PageComments\Hooks\PageDisplayHooks
 * @group Database
 * @group medium
 * @group extension-PageComments
 */
class PageDisplayHooksNamespaceConfigTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'PageCommentsEnabledNamespaces', [ NS_PROJECT ] );
	}

	/**
	 * BeforePageDisplay injects PageComments module/config for enabled namespaces.
	 */
	public function testLoadsModuleInEnabledNamespace(): void {
		$title = $this->createExistingTitleInNamespace( NS_PROJECT );
		$out = $this->newOutputPageForTitle( $title, 'view' );
		$skin = $this->createMock( Skin::class );

		PageDisplayHooks::onBeforePageDisplay( $out, $skin );

		$this->assertContains( 'ext.pagecomments.app', $out->getModules() );
		$config = $out->getJsConfigVars()['wgPageComments'] ?? null;
		$this->assertIsArray( $config );
		$this->assertSame( NS_PROJECT, $config['namespace'] );
	}

	/**
	 * BeforePageDisplay does not inject module/config for disabled namespaces.
	 */
	public function testSkipsModuleInDisabledNamespace(): void {
		$title = $this->createExistingTitleInNamespace( NS_MAIN );
		$out = $this->newOutputPageForTitle( $title, 'view' );
		$skin = $this->createMock( Skin::class );

		PageDisplayHooks::onBeforePageDisplay( $out, $skin );

		$this->assertNotContains( 'ext.pagecomments.app', $out->getModules() );
		$this->assertArrayNotHasKey( 'wgPageComments', $out->getJsConfigVars() );
	}

	/**
	 * BeforePageDisplay does not inject module/config when action is not view.
	 */
	public function testSkipsModuleForNonViewAction(): void {
		$title = $this->createExistingTitleInNamespace( NS_PROJECT );
		$out = $this->newOutputPageForTitle( $title, 'edit' );
		$skin = $this->createMock( Skin::class );

		PageDisplayHooks::onBeforePageDisplay( $out, $skin );

		$this->assertNotContains( 'ext.pagecomments.app', $out->getModules() );
		$this->assertArrayNotHasKey( 'wgPageComments', $out->getJsConfigVars() );
	}

	private function createExistingTitleInNamespace( int $namespace ): Title {
		$title = Title::newFromText(
			'PageComments Hook ' . wfRandomString(),
			$namespace
		);
		$page = $this->getExistingTestPage( $title );
		return $page->getTitle();
	}

	private function newOutputPageForTitle( Title $title, string $action ): OutputPage {
		$context = RequestContext::getMain();
		$context->setRequest( new FauxRequest( [ 'action' => $action ] ) );
		$out = new OutputPage( $context );
		$out->setTitle( $title );
		return $out;
	}
}
