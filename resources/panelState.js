function getCurrentUserName() {
	return mw.config.get( 'wgUserName' ) || 'User';
}

function getCurrentUserActorId() {
	const config = mw.config.get( 'wgPageComments' ) || {};
	const actorId = Number( config.userActorId );
	return Number.isInteger( actorId ) && actorId > 0 ? actorId : 0;
}

function getNowTimestamp() {
	const now = new Date();
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	return `${now.getFullYear()}${pad( now.getMonth() + 1 )}${pad( now.getDate() )}${pad( now.getHours() )}${pad( now.getMinutes() )}${pad( now.getSeconds() )}`;
}

function findThreadIndex( threads, threadId ) {
	return threads.findIndex(
		( thread ) => Number( thread.id ) === Number( threadId )
	);
}

function buildOptimisticComment( threadId, commentId, body, timestamp, actorName, actorId ) {
	return {
		id: commentId,
		threadId,
		parentCommentId: null,
		actorId,
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
	const actorId = getCurrentUserActorId();
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
		comments: [ buildOptimisticComment( threadId, commentId, body, timestamp, actorName, actorId ) ]
	};
}

function appendReply( threads, threadId, commentId, body ) {
	const index = findThreadIndex( threads, threadId );
	if ( index < 0 ) {
		return false;
	}
	const timestamp = getNowTimestamp();
	const actorName = getCurrentUserName();
	const actorId = getCurrentUserActorId();
	threads[index].comments.push( buildOptimisticComment(
		threadId,
		commentId,
		body,
		timestamp,
		actorName,
		actorId
	) );
	threads[index].updatedAt = timestamp;
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
	const comment = thread.comments.find(
		( item ) => Number( item.id ) === Number( commentId )
	);
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
	thread.comments = thread.comments.filter(
		( comment ) => Number( comment.id ) !== Number( commentId )
	);
	if ( thread.comments.length === oldCount ) {
		return { removed: false, threadDeleted: false };
	}
	if ( thread.comments.length === 0 ) {
		threads.splice( index, 1 );
		return { removed: true, threadDeleted: true };
	}
	thread.updatedAt = getNowTimestamp();
	return { removed: true, threadDeleted: false };
}

module.exports = {
	buildThreadFromCreateResult,
	appendReply,
	setThreadState,
	updateCommentBody,
	removeComment
};
