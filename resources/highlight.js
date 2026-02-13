const anchorUtil = require( './anchor.js' );

function clearHighlights() {
	const marks = document.querySelectorAll( '.pagecomments-highlight' );
	for ( const mark of marks ) {
		const textNode = document.createTextNode( mark.textContent || '' );
		mark.replaceWith( textNode );
	}
}

function resolveAnchorOffset( text, anchor ) {
	const range = anchorUtil.resolveAnchorRange( anchor, text );
	return range ? range.start : -1;
}

function buildTextMap( root ) {
	const nodes = [];
	let text = '';
	let offset = 0;
	const walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT );
	let node = walker.nextNode();
	while ( node ) {
		const value = node.nodeValue || '';
		if ( value.length > 0 ) {
			nodes.push( {
				node,
				start: offset,
				end: offset + value.length
			} );
			text += value;
			offset += value.length;
		}
		node = walker.nextNode();
	}
	return { nodes, text };
}

function applyMatchesToDom( map, matches, onThreadClick, onThreadHover, onThreadLeave ) {
	const normalized = [];
	const sorted = matches.slice().sort( ( a, b ) => a.start - b.start );
	let lastEnd = -1;
	for ( const match of sorted ) {
		if ( match.start < 0 || match.end <= match.start ) {
			continue;
		}
		if ( match.start < lastEnd ) {
			continue;
		}
		normalized.push( match );
		lastEnd = match.end;
	}

	const segmentsByNode = new Map();
	for ( const match of normalized ) {
		for ( const entry of map.nodes ) {
			if ( match.end <= entry.start ) {
				break;
			}
			if ( match.start >= entry.end ) {
				continue;
			}
			const localStart = Math.max( 0, match.start - entry.start );
			const localEnd = Math.min( entry.end - entry.start, match.end - entry.start );
			if ( localEnd <= localStart ) {
				continue;
			}
			const list = segmentsByNode.get( entry.node ) || [];
			list.push( {
				threadId: match.threadId,
				state: match.state || 'open',
				start: localStart,
				end: localEnd
			} );
			segmentsByNode.set( entry.node, list );
		}
	}

	for ( const [ node, segments ] of segmentsByNode.entries() ) {
		if ( !node.parentNode ) {
			continue;
		}
		const value = node.nodeValue || '';
		const ordered = segments.sort( ( a, b ) => a.start - b.start );
		let cursor = 0;
		const frag = document.createDocumentFragment();
		for ( const segment of ordered ) {
			if ( segment.start > cursor ) {
				frag.appendChild( document.createTextNode( value.slice( cursor, segment.start ) ) );
			}
			const wrapper = document.createElement( 'span' );
			const stateClass = segment.state === 'resolved' ?
				'pagecomments-highlight-resolved' :
				'pagecomments-highlight-open';
			wrapper.className = 'pagecomments-highlight ' + stateClass;
			wrapper.dataset.threadId = String( segment.threadId );
			wrapper.dataset.state = segment.state;
			wrapper.textContent = value.slice( segment.start, segment.end );
			wrapper.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				onThreadClick( segment.threadId );
			} );
			if ( typeof onThreadHover === 'function' ) {
				wrapper.addEventListener( 'mouseenter', ( event ) => {
					onThreadHover( segment.threadId, wrapper, event );
				} );
			}
			if ( typeof onThreadLeave === 'function' ) {
				wrapper.addEventListener( 'mouseleave', ( event ) => {
					onThreadLeave( segment.threadId, event );
				} );
			}
			frag.appendChild( wrapper );
			cursor = segment.end;
		}
		if ( cursor < value.length ) {
			frag.appendChild( document.createTextNode( value.slice( cursor ) ) );
		}
		node.parentNode.replaceChild( frag, node );
	}

	return normalized;
}

function updateSelectedHighlightClasses( selectedThreadId ) {
	const marks = document.querySelectorAll( '.pagecomments-highlight' );
	for ( const mark of marks ) {
		const selected = selectedThreadId !== null && mark.dataset.threadId === String( selectedThreadId );
		mark.classList.toggle( 'pagecomments-highlight-selected', selected );
	}
}

function updateUnseenHighlightClasses( isThreadUnseen ) {
	const marks = document.querySelectorAll( '.pagecomments-highlight' );
	const taggedThreads = new Set();
	for ( const mark of marks ) {
		const threadId = mark.dataset.threadId || '';
		const unseen = typeof isThreadUnseen === 'function' && isThreadUnseen( threadId );
		if ( !unseen ) {
			mark.classList.remove( 'pagecomments-highlight-unseen' );
			continue;
		}
		if ( taggedThreads.has( threadId ) ) {
			mark.classList.remove( 'pagecomments-highlight-unseen' );
			continue;
		}
		taggedThreads.add( threadId );
		mark.classList.add( 'pagecomments-highlight-unseen' );
	}
}

module.exports = {
	clearHighlights,
	resolveAnchorOffset,
	buildTextMap,
	applyMatchesToDom,
	updateSelectedHighlightClasses,
	updateUnseenHighlightClasses
};
