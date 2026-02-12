function getCurrentUserName() {
	return mw.config.get( 'wgUserName' ) || 'User';
}

function getNowTimestamp() {
	const now = new Date();
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	return `${now.getFullYear()}${pad( now.getMonth() + 1 )}${pad( now.getDate() )}${pad( now.getHours() )}${pad( now.getMinutes() )}${pad( now.getSeconds() )}`;
}

function findThreadIndex( threads, threadId ) {
	return threads.findIndex( ( thread ) => thread.id === threadId );
}

function moveThreadToTop( threads, threadId ) {
	const index = findThreadIndex( threads, threadId );
	if ( index <= 0 ) {
		return;
	}
	const thread = threads.splice( index, 1 )[0];
	threads.unshift( thread );
}

function buildThreadFromCreateResult( options ) {
	const {
		threadId,
		commentId,
		pageId,
		revisionId,
		pendingAnchor,
		body
	} = options;

	const timestamp = getNowTimestamp();
	const actorName = getCurrentUserName();
	return {
		id: threadId,
		pageId,
		revId: revisionId || 0,
		state: 'open',
		createdAt: timestamp,
		updatedAt: timestamp,
		actorName,
		anchor: pendingAnchor,
		excerpt: pendingAnchor.exact,
		orphaned: false,
		comments: [
			{
				id: commentId,
				threadId,
				parentCommentId: null,
				body,
				createdAt: timestamp,
				actorName
			}
		]
	};
}

function appendReply( threads, threadId, commentId, body ) {
	const index = findThreadIndex( threads, threadId );
	if ( index < 0 ) {
		return false;
	}
	const timestamp = getNowTimestamp();
	const actorName = getCurrentUserName();
	threads[index].comments.push( {
		id: commentId,
		threadId,
		parentCommentId: null,
		body,
		createdAt: timestamp,
		actorName
	} );
	threads[index].updatedAt = timestamp;
	moveThreadToTop( threads, threadId );
	return true;
}

function setThreadState( threads, threadId, state ) {
	const index = findThreadIndex( threads, threadId );
	if ( index < 0 ) {
		return false;
	}
	threads[index].state = state;
	threads[index].updatedAt = getNowTimestamp();
	moveThreadToTop( threads, threadId );
	return true;
}

module.exports = {
	buildThreadFromCreateResult,
	appendReply,
	setThreadState
};
