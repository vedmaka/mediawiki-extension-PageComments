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
	const selectedText = range.toString();
	const exact = selectedText.trim();
	if ( !exact ) {
		return null;
	}
	const offsets = getRangeOffsets( range, root );
	if ( !offsets ) {
		return null;
	}
	const leadingWhitespace = selectedText.match( /^\s*/ );
	const trailingWhitespace = selectedText.match( /\s*$/ );
	const trimStart = leadingWhitespace ? leadingWhitespace[0].length : 0;
	const trimEnd = trailingWhitespace ? trailingWhitespace[0].length : 0;
	const start = offsets.start + trimStart;
	const end = Math.max( start + exact.length, offsets.end - trimEnd );
	const allText = root.textContent || '';
	return {
		exact,
		start,
		end,
		prefix: allText.slice( Math.max( 0, start - 24 ), start ),
		suffix: allText.slice( end, end + 24 )
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

function getAnchorExact( anchor ) {
	if ( !anchor || typeof anchor.exact !== 'string' ) {
		return '';
	}
	return anchor.exact.trim();
}

function findNearestOccurrence( text, needle, nearStart, windowSize ) {
	if ( !needle ) {
		return -1;
	}
	const scanStart = Math.max( 0, nearStart - windowSize );
	const scanEnd = Math.min( text.length, nearStart + needle.length + windowSize );
	const segment = text.slice( scanStart, scanEnd );
	let from = 0;
	let bestIndex = -1;
	let bestDistance = Number.MAX_SAFE_INTEGER;
	while ( true ) {
		const idx = segment.indexOf( needle, from );
		if ( idx < 0 ) {
			break;
		}
		const absolute = scanStart + idx;
		const distance = Math.abs( absolute - nearStart );
		if ( distance < bestDistance ) {
			bestDistance = distance;
			bestIndex = absolute;
		}
		from = idx + 1;
	}
	return bestIndex;
}

function resolveAnchorRange( anchor, articleText ) {
	const exact = getAnchorExact( anchor );
	if ( !exact ) {
		return null;
	}
	const start = Number( anchor.start );
	const end = Number( anchor.end );
	if ( Number.isInteger( start ) && start >= 0 ) {
		let resolvedStart = start;
		if ( articleText ) {
			if ( articleText.slice( resolvedStart, resolvedStart + exact.length ) !== exact ) {
				const nearby = findNearestOccurrence( articleText, exact, resolvedStart, 32 );
				if ( nearby >= 0 ) {
					resolvedStart = nearby;
				} else if (
					Number.isInteger( end ) &&
					end > start &&
					articleText.slice( end - exact.length, end ) === exact
				) {
					resolvedStart = end - exact.length;
				} else {
					return null;
				}
			}
		}
		return { start: resolvedStart, end: resolvedStart + exact.length };
	}
	if ( Number.isInteger( end ) && end > 0 ) {
		const recoveredStart = end - exact.length;
		if ( recoveredStart >= 0 ) {
			return { start: recoveredStart, end: recoveredStart + exact.length };
		}
	}
	return null;
}

function hasOverlappingAnchor( anchor, threads, root ) {
	const articleText = root && root.textContent ? root.textContent : '';
	const current = resolveAnchorRange( anchor, articleText );
	if ( !current ) {
		return false;
	}
	for ( const thread of threads || [] ) {
		const existing = thread && thread.anchor ? thread.anchor : null;
		const existingRange = resolveAnchorRange( existing, articleText );
		if ( !existingRange ) {
			continue;
		}
		if ( rangesOverlap( current.start, current.end, existingRange.start, existingRange.end ) ) {
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
