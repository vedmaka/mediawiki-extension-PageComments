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

function rangesOverlap( aStart, aEnd, bStart, bEnd ) {
	return aStart < bEnd && bStart < aEnd;
}

function getAnchorExact( anchor ) {
	if ( !anchor || typeof anchor.exact !== 'string' ) {
		return '';
	}
	return anchor.exact.trim();
}

function getContextValue( value ) {
	return typeof value === 'string' ? value : '';
}

function findOccurrences( text, needle, limit ) {
	const matches = [];
	if ( !text || !needle ) {
		return matches;
	}
	let cursor = 0;
	while ( matches.length < limit ) {
		const index = text.indexOf( needle, cursor );
		if ( index < 0 ) {
			break;
		}
		matches.push( index );
		cursor = index + 1;
	}
	return matches;
}

function hasPrefixMatch( text, start, prefix ) {
	if ( !prefix ) {
		return true;
	}
	return text.slice( Math.max( 0, start - prefix.length ), start ) === prefix;
}

function hasSuffixMatch( text, start, exactLength, suffix ) {
	if ( !suffix ) {
		return true;
	}
	return text.slice( start + exactLength, start + exactLength + suffix.length ) === suffix;
}

function pickNearestStart( starts, hintStart ) {
	if ( !starts.length ) {
		return -1;
	}
	if ( !Number.isInteger( hintStart ) || hintStart < 0 ) {
		return starts[0];
	}
	let bestStart = starts[0];
	let bestDistance = Math.abs( starts[0] - hintStart );
	for ( const start of starts ) {
		const distance = Math.abs( start - hintStart );
		if ( distance < bestDistance ) {
			bestStart = start;
			bestDistance = distance;
		}
	}
	return bestStart;
}

function toRange( start, exactLength ) {
	return { start, end: start + exactLength };
}

function resolveAnchorRange( anchor, articleText ) {
	const exact = getAnchorExact( anchor );
	if ( !exact ) {
		return null;
	}
	const exactLength = exact.length;
	const hintStart = Number( anchor.start );
	const hintEnd = Number( anchor.end );
	const hasHintStart = Number.isInteger( hintStart ) && hintStart >= 0;

	if ( typeof articleText !== 'string' || articleText === '' ) {
		if ( hasHintStart ) {
			return toRange( hintStart, exactLength );
		}
		if ( Number.isInteger( hintEnd ) && hintEnd > 0 ) {
			const recoveredStart = hintEnd - exactLength;
			if ( recoveredStart >= 0 ) {
				return toRange( recoveredStart, exactLength );
			}
		}
		return null;
	}

	if ( hasHintStart && articleText.slice( hintStart, hintStart + exactLength ) === exact ) {
		return toRange( hintStart, exactLength );
	}
	if ( Number.isInteger( hintEnd ) && hintEnd > 0 ) {
		const endRecoveredStart = hintEnd - exactLength;
		if (
			endRecoveredStart >= 0 &&
			articleText.slice( endRecoveredStart, hintEnd ) === exact
		) {
			return toRange( endRecoveredStart, exactLength );
		}
	}

	const occurrences = findOccurrences( articleText, exact, 5000 );
	if ( !occurrences.length ) {
		return null;
	}

	const prefix = getContextValue( anchor.prefix );
	const suffix = getContextValue( anchor.suffix );

	const fullContextMatches = occurrences.filter( ( start ) =>
		hasPrefixMatch( articleText, start, prefix ) &&
		hasSuffixMatch( articleText, start, exactLength, suffix )
	);
	if ( fullContextMatches.length ) {
		return toRange( pickNearestStart( fullContextMatches, hintStart ), exactLength );
	}

	const prefixMatches = prefix ?
		occurrences.filter( ( start ) => hasPrefixMatch( articleText, start, prefix ) ) :
		[];
	const suffixMatches = suffix ?
		occurrences.filter( ( start ) => hasSuffixMatch( articleText, start, exactLength, suffix ) ) :
		[];

	if ( prefixMatches.length === 1 ) {
		return toRange( prefixMatches[0], exactLength );
	}
	if ( suffixMatches.length === 1 ) {
		return toRange( suffixMatches[0], exactLength );
	}

	if ( hasHintStart ) {
		const nearby = occurrences.filter( ( start ) => Math.abs( start - hintStart ) <= 256 );
		if ( nearby.length === 1 ) {
			return toRange( nearby[0], exactLength );
		}
		if ( nearby.length > 1 ) {
			const nearbyContext = nearby.filter( ( start ) =>
				hasPrefixMatch( articleText, start, prefix ) ||
				hasSuffixMatch( articleText, start, exactLength, suffix )
			);
			if ( nearbyContext.length ) {
				return toRange( pickNearestStart( nearbyContext, hintStart ), exactLength );
			}
		}
	}

	if ( occurrences.length === 1 ) {
		return toRange( occurrences[0], exactLength );
	}

	// Ambiguous repeated token without reliable context; avoid wrong anchoring.
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
	hasOverlappingAnchor,
	resolveAnchorRange
};
