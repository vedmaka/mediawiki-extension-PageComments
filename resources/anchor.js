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

module.exports = {
	getArticleRoot,
	isInArticle,
	getEventTargetElement,
	buildAnchorFromRange
};
