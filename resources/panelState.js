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

function buildOptimisticComment( threadId, commentId, body, timestamp, actorName ) {
	return {
		id: commentId,
		threadId,
		parentCommentId: null,
		body,
		createdAt: timestamp,
		actorName,
		canManage: true,
		canEdit: true,
		canDelete: true
	};
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
		comments: [ buildOptimisticComment( threadId, commentId, body, timestamp, actorName ) ]
	};
}

function appendReply( threads, threadId, commentId, body ) {
	const index = findThreadIndex( threads, threadId );
	if ( index < 0 ) {
		return false;
	}
	const timestamp = getNowTimestamp();
	const actorName = getCurrentUserName();
	threads[index].comments.push( buildOptimisticComment( threadId, commentId, body, timestamp, actorName ) );
	threads[index].updatedAt = timestamp;
	moveThreadToTop( threads, threadId );
	return true;
}

function setThreadState( threads, threadId, state ) {
	const index = findThreadIndex( threads, threadId );
	if ( index < 0 ) {
		return false;
	}
	// Keep position stable: status changes should not reorder thread list.
	threads[index].state = state;
	return true;
}

function updateCommentBody( threads, threadId, commentId, body ) {
	const index = findThreadIndex( threads, threadId );
	if ( index < 0 ) {
		return false;
	}
	const thread = threads[index];
	const comment = thread.comments.find( ( item ) => item.id === commentId );
	if ( !comment ) {
		return false;
	}
	// Text edit only; preserve current thread order in panel.
	comment.body = body;
	return true;
}

function removeComment( threads, threadId, commentId ) {
	const index = findThreadIndex( threads, threadId );
	if ( index < 0 ) {
		return { removed: false, threadDeleted: false };
	}
	const thread = threads[index];
	const oldCount = thread.comments.length;
	thread.comments = thread.comments.filter( ( comment ) => comment.id !== commentId );
	if ( thread.comments.length === oldCount ) {
		return { removed: false, threadDeleted: false };
	}
	if ( thread.comments.length === 0 ) {
		threads.splice( index, 1 );
		return { removed: true, threadDeleted: true };
	}
	thread.updatedAt = getNowTimestamp();
	moveThreadToTop( threads, threadId );
	return { removed: true, threadDeleted: false };
}

module.exports = {
	buildThreadFromCreateResult,
	appendReply,
	setThreadState,
	updateCommentBody,
	removeComment
};
