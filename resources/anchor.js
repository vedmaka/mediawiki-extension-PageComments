function getArticleRoot() {
	return document.querySelector( '.mw-parser-output' ) || document.querySelector( '#mw-content-text' );
}

function isInArticle( node, root ) {
	if ( !root || !node ) {
		return false;
	}
	return root.contains( node );
}

function getEventTargetElement( event ) {
	const target = event.target;
	if ( target instanceof Element ) {
		return target;
	}
	if ( target && target.parentElement instanceof Element ) {
		return target.parentElement;
	}
	return null;
}

function getRangeOffsets( range, root ) {
	try {
		const before = range.cloneRange();
		before.selectNodeContents( root );
		before.setEnd( range.startContainer, range.startOffset );
		const start = before.toString().length;
		const end = start + range.toString().length;
		return { start, end };
	} catch ( e ) {
		return null;
	}
}

function buildAnchorFromRange( range, root ) {
	if ( !root ) {
		return null;
	}
	const exact = range.toString().trim();
	if ( !exact ) {
		return null;
	}
	const offsets = getRangeOffsets( range, root );
	if ( !offsets ) {
		return null;
	}
	const allText = root.textContent || '';
	return {
		exact,
		start: offsets.start,
		end: offsets.end,
		prefix: allText.slice( Math.max( 0, offsets.start - 24 ), offsets.start ),
		suffix: allText.slice( offsets.end, offsets.end + 24 )
	};
}

function isAnchorRangeValid( anchor ) {
	if ( !anchor ) {
		return false;
	}
	const start = Number( anchor.start );
	const end = Number( anchor.end );
	return Number.isInteger( start ) && Number.isInteger( end ) && start >= 0 && end > start;
}

function rangesOverlap( aStart, aEnd, bStart, bEnd ) {
	return aStart < bEnd && bStart < aEnd;
}

function hasOverlappingAnchor( anchor, threads ) {
	if ( !isAnchorRangeValid( anchor ) ) {
		return false;
	}
	const start = Number( anchor.start );
	const end = Number( anchor.end );
	for ( const thread of threads || [] ) {
		const existing = thread && thread.anchor ? thread.anchor : null;
		if ( !isAnchorRangeValid( existing ) ) {
			continue;
		}
		if ( rangesOverlap( start, end, Number( existing.start ), Number( existing.end ) ) ) {
			return true;
		}
	}
	return false;
}

module.exports = {
	getArticleRoot,
	isInArticle,
	getEventTargetElement,
	buildAnchorFromRange,
	hasOverlappingAnchor
};
