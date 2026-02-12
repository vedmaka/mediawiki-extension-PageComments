const Vue = require( 'vue' );
const PageCommentsApp = require( './components/PageCommentsApp.vue' );

const config = mw.config.get( 'wgPageComments' );
if ( !config || !config.pageId ) {
	return;
}

const existing = document.getElementById( 'pagecomments-root' );
if ( existing ) {
	return;
}

const mount = document.createElement( 'div' );
mount.id = 'pagecomments-root';
document.body.appendChild( mount );

Vue.createMwApp( PageCommentsApp ).mount( mount );
