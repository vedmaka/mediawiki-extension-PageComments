<?php

// Prefer extension-local vendor (standalone extension repo),
// fallback to MediaWiki root vendor (core checkout).
$localConfig = __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';
$rootConfig = __DIR__ . '/../../../vendor/mediawiki/mediawiki-phan-config/src/config.php';

if ( file_exists( $localConfig ) ) {
	return require $localConfig;
}

return require $rootConfig;
