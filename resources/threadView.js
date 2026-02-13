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
	const date = parseMwTimestamp( mwTimestamp );
	if ( !date ) {
		return '';
	}
	return formatRelativeTime( date );
}

function parseMwTimestamp( mwTimestamp ) {
	if ( !mwTimestamp || mwTimestamp.length < 12 ) {
		return null;
	}
	const year = Number( mwTimestamp.slice( 0, 4 ) );
	const month = Number( mwTimestamp.slice( 4, 6 ) );
	const day = Number( mwTimestamp.slice( 6, 8 ) );
	const hour = Number( mwTimestamp.slice( 8, 10 ) );
	const minute = Number( mwTimestamp.slice( 10, 12 ) );
	const second = mwTimestamp.length >= 14 ? Number( mwTimestamp.slice( 12, 14 ) ) : 0;
	if ( [ year, month, day, hour, minute, second ].some( ( n ) => Number.isNaN( n ) ) ) {
		return null;
	}
	return new Date( Date.UTC( year, month - 1, day, hour, minute, second ) );
}

function formatRelativeTime( date ) {
	const diffSeconds = Math.round( ( date.getTime() - Date.now() ) / 1000 );
	const absSeconds = Math.abs( diffSeconds );
	if ( absSeconds < 30 ) {
		return 'just now';
	}
	const units = [
		[ 'year', 31536000 ],
		[ 'month', 2592000 ],
		[ 'day', 86400 ],
		[ 'hour', 3600 ],
		[ 'minute', 60 ]
	];
	for ( const [ unit, secondsPerUnit ] of units ) {
		if ( absSeconds >= secondsPerUnit ) {
			const value = Math.round( diffSeconds / secondsPerUnit );
			return formatRelativeUnit( value, unit );
		}
	}
	return formatRelativeUnit( diffSeconds, 'second' );
}

function formatRelativeUnit( value, unit ) {
	if ( typeof Intl !== 'undefined' && Intl.RelativeTimeFormat ) {
		const language = ( mw.config && mw.config.get( 'wgUserLanguage' ) ) || 'en';
		const rtf = new Intl.RelativeTimeFormat( language, { numeric: 'always' } );
		return rtf.format( value, unit );
	}
	const absolute = Math.abs( value );
	const plural = absolute === 1 ? unit : `${unit}s`;
	return value < 0 ? `${absolute} ${plural} ago` : `in ${absolute} ${plural}`;
}

module.exports = {
	applyHighlights,
	formatTimestamp
};
