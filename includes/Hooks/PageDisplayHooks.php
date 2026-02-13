<?php

namespace MediaWiki\Extension\PageComments\Hooks;

use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use Skin;

class PageDisplayHooks {

	/**
	 * Load PageComments client app on configured namespace page view.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		$title = $out->getTitle();
		if ( !$title || !$title->exists() || $title->isSpecialPage() ) {
			return;
		}

		$action = $out->getRequest()->getVal( 'action', 'view' );
		if ( $action !== 'view' ) {
			return;
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$enabledNamespaces = $config->get( 'PageCommentsEnabledNamespaces' );
		if ( !in_array( $title->getNamespace(), $enabledNamespaces, true ) ) {
			return;
		}

		$user = $out->getUser();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$permissionManager->userHasRight( $user, 'pagecomments-read' ) ) {
			return;
		}
		$canWrite = $user->isNamed() && $permissionManager->userHasRight( $user, 'pagecomments-write' );

		$out->addModules( 'ext.pagecomments.app' );
		$out->addJsConfigVars( 'wgPageComments', [
			'pageId' => (int)$title->getArticleID(),
			'revId' => (int)$title->getLatestRevID(),
			'namespace' => $title->getNamespace(),
			'userActorId' => $user->getActorId(),
			'hideResolvedHighlights' => (bool)$config->get( 'PageCommentsHideResolvedHighlights' ),
			'canWrite' => $canWrite,
		] );
	}
}
