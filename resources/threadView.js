const anchorUtil = require( './anchor.js' );
const highlight = require( './highlight.js' );

function applyHighlights( threads, selectedThreadId, onThreadClick ) {
	const root = anchorUtil.getArticleRoot();
	if ( !root ) {
		return;
	}
	highlight.clearHighlights();
	const map = highlight.buildTextMap( root );
	if ( !map.text ) {
		return;
	}
	const matches = [];
	for ( const thread of threads ) {
		const exact = thread.anchor && thread.anchor.exact ? thread.anchor.exact : '';
		if ( !exact ) {
			thread.orphaned = true;
			continue;
		}
		const offset = highlight.resolveAnchorOffset( map.text, thread.anchor );
		if ( offset < 0 ) {
			thread.orphaned = true;
			continue;
		}
		matches.push( {
			threadId: thread.id,
			state: thread.state || 'open',
			start: offset,
			end: offset + exact.length
		} );
		thread.orphaned = false;
	}
	const applied = highlight.applyMatchesToDom( map, matches, onThreadClick );
	const appliedIds = new Set( applied.map( ( item ) => item.threadId ) );
	for ( const thread of threads ) {
		if ( !appliedIds.has( thread.id ) ) {
			thread.orphaned = true;
		}
	}
	highlight.updateSelectedHighlightClasses( selectedThreadId );
}

function formatTimestamp( mwTimestamp ) {
	if ( !mwTimestamp || mwTimestamp.length < 12 ) {
		return '';
	}
	const y = mwTimestamp.slice( 0, 4 );
	const m = mwTimestamp.slice( 4, 6 );
	const d = mwTimestamp.slice( 6, 8 );
	const hh = mwTimestamp.slice( 8, 10 );
	const mm = mwTimestamp.slice( 10, 12 );
	return `${y}-${m}-${d} ${hh}:${mm}`;
}

module.exports = {
	applyHighlights,
	formatTimestamp
};
