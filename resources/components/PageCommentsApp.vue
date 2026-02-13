<template>
	<div class="pagecomments-shell">
		<button
			v-if="showAnchorButton && canWrite"
			class="pagecomments-anchor-button"
			:style="anchorButtonStyle"
			@click="openNewCommentComposer"
		>
			{{ msg( 'pagecomments-ui-add-comment' ) }}
		</button>
		<div
			v-if="pendingAnchor && canWrite"
			ref="floatingComposer"
			class="pagecomments-floating-composer"
			:style="composerStyle"
		>
			<h3>{{ msg( 'pagecomments-ui-new-comment' ) }}</h3>
			<p class="pagecomments-anchor-label">{{ msg( 'pagecomments-ui-selected-text' ) }}</p>
			<blockquote class="pagecomments-anchor-preview">
				{{ pendingAnchor.exact }}
			</blockquote>
			<textarea v-model="newThreadBody" class="pagecomments-textarea" rows="3"></textarea>
			<div class="pagecomments-actions">
				<button class="pagecomments-btn" @click="submitNewThread">{{ msg( 'pagecomments-ui-submit' ) }}</button>
				<button class="pagecomments-btn pagecomments-btn-quiet" @click="cancelNewThread">{{ msg( 'pagecomments-ui-cancel' ) }}</button>
			</div>
		</div>
		<div
			v-if="highlightPreview.visible"
			ref="highlightPreviewCard"
			class="pagecomments-highlight-preview"
			:style="highlightPreview.style"
		>
			<p class="pagecomments-highlight-preview-count">
				{{ commentCountLabel( highlightPreview.commentCount ) }}
			</p>
			<p class="pagecomments-highlight-preview-body">
				{{ highlightPreview.firstCommentBody }}
			</p>
		</div>
		<aside class="pagecomments-panel" :class="{ 'is-open': isPanelOpen }">
			<header class="pagecomments-panel-header">
				<h2>{{ msg( 'pagecomments-ui-title' ) }}</h2>
				<button class="pagecomments-panel-close" @click="closePanel">
					{{ msg( 'pagecomments-ui-close' ) }}
				</button>
			</header>
			<section class="pagecomments-panel-body">
				<p v-if="!canWrite" class="pagecomments-note">
					{{ msg( 'pagecomments-ui-write-required' ) }}
				</p>
				<div v-if="loading" class="pagecomments-loading">
					{{ msg( 'pagecomments-ui-loading' ) }}
				</div>
				<div v-else>
					<p v-if="errorMessage" class="pagecomments-error">
						{{ errorMessage }}
					</p>
					<p v-if="!threads.length" class="pagecomments-empty">
						{{ msg( 'pagecomments-ui-empty' ) }}
					</p>
					<p v-if="!threads.length && canWrite" class="pagecomments-note">
						{{ msg( 'pagecomments-ui-select-hint' ) }}
					</p>
					<div
						v-for="thread in threads"
						:key="thread.id"
						class="pagecomments-thread"
						:data-thread-id="thread.id"
						:class="{
							'is-selected': selectedThreadId === thread.id,
							'is-open': thread.state === 'open',
							'is-resolved': thread.state === 'resolved'
						}"
						@click.capture="markThreadSeen( thread.id )"
						@click="selectThread( thread.id )"
					>
						<div class="pagecomments-thread-head">
							<div class="pagecomments-thread-head-main">
								<button
									v-if="canWrite"
									class="pagecomments-thread-state-toggle"
									:title="thread.state === 'open' ? msg( 'pagecomments-ui-resolve' ) : msg( 'pagecomments-ui-reopen' )"
									@click.stop="setThreadState( thread.id, thread.state === 'open' ? 'resolved' : 'open' )"
								>
									<span class="pagecomments-thread-state-label">{{ thread.state }}</span>
									<span class="pagecomments-thread-action-label">
										{{ thread.state === 'open' ? msg( 'pagecomments-ui-resolve' ) : msg( 'pagecomments-ui-reopen' ) }}
									</span>
								</button>
								<span v-else class="pagecomments-thread-state">{{ thread.state }}</span>
								<span v-if="isThreadUnseen( thread.id )" class="pagecomments-unseen-dot" aria-hidden="true"></span>
							</div>
							<button
								class="pagecomments-thread-toggle"
								:title="isThreadCollapsed( thread.id ) ? msg( 'pagecomments-ui-show-thread' ) : msg( 'pagecomments-ui-hide-thread' )"
								:aria-label="isThreadCollapsed( thread.id ) ? msg( 'pagecomments-ui-show-thread' ) : msg( 'pagecomments-ui-hide-thread' )"
								@click.stop="toggleThreadCollapsed( thread.id )"
							>
								{{ isThreadCollapsed( thread.id ) ? '▸' : '▾' }}
							</button>
						</div>
						<div class="pagecomments-thread-body">
							<p class="pagecomments-anchor-label">{{ msg( 'pagecomments-ui-selected-text' ) }}</p>
							<blockquote class="pagecomments-anchor-preview">
								{{ thread.excerpt }}
							</blockquote>
							<p v-if="thread.orphaned && thread.state !== 'resolved'" class="pagecomments-note pagecomments-note-orphaned">
								{{ msg( 'pagecomments-ui-orphaned' ) }}
							</p>
							<template v-if="!isThreadCollapsed( thread.id )">
								<ul class="pagecomments-comments">
									<page-comments-comment-item
										v-for="comment in thread.comments"
										:key="comment.id"
										:thread-id="thread.id"
										:comment="comment"
										@edit-comment="saveEditedComment"
										@delete-comment="deleteComment"
									></page-comments-comment-item>
								</ul>
								<div class="pagecomments-actions">
									<button
										v-if="canWrite"
										class="pagecomments-btn pagecomments-btn-quiet"
										@click.stop="toggleReply( thread.id )"
									>
										{{ msg( 'pagecomments-ui-reply' ) }}
									</button>
								</div>
								<div v-if="replyOpen[thread.id]" class="pagecomments-reply" @click.stop>
									<textarea v-model="replyBody[thread.id]" class="pagecomments-textarea" rows="2" @click.stop></textarea>
									<div class="pagecomments-actions">
										<button class="pagecomments-btn" @click.stop="submitReply( thread.id )">{{ msg( 'pagecomments-ui-submit' ) }}</button>
										<button class="pagecomments-btn pagecomments-btn-quiet" @click.stop="toggleReply( thread.id )">{{ msg( 'pagecomments-ui-cancel' ) }}</button>
									</div>
								</div>
							</template>
						</div>
					</div>
				</div>
			</section>
		</aside>
	</div>
</template>

<script>
const highlight = require( '../highlight.js' );
const panelState = require( '../panelState.js' );
const anchorUtil = require( '../anchor.js' );
const threadView = require( '../threadView.js' );
const PageCommentsCommentItem = require( './PageCommentsCommentItem.vue' );

module.exports = exports = {
	name: 'PageCommentsApp',
	components: {
		PageCommentsCommentItem,
		'page-comments-comment-item': PageCommentsCommentItem
	},
	data() {
		const config = mw.config.get( 'wgPageComments' ) || {};
		return {
			pageId: Number( config.pageId ) || 0,
			currentUserActorId: Number( config.userActorId ) || 0,
			currentUserName: String( mw.config.get( 'wgUserName' ) || '' ),
			hideResolvedHighlights: !!config.hideResolvedHighlights,
			canWrite: !!config.canWrite,
			threads: [],
			loading: true,
			errorMessage: '',
			selectedThreadId: null,
			showAnchorButton: false,
			anchorButtonStyle: { top: '0px', left: '0px' },
			composerStyle: { top: '0px', left: '0px' },
			capturedAnchor: null,
			pendingAnchor: null,
			newThreadBody: '',
			replyOpen: {},
			replyBody: {},
			isPanelOpen: false,
			newThreadPollTimer: null,
			newThreadPollInFlight: false,
			collapsedThreads: {},
			lastHighlightSignature: '',
			seenThreadComments: {},
			highlightPreview: {
				visible: false,
				threadId: null,
				firstCommentBody: '',
				commentCount: 0,
				style: { top: '0px', left: '0px' }
			},
			hidePreviewTimer: null
		};
	},
	mounted() {
		this.loadSeenThreads();
		this.fetchThreads();
		this.startNewThreadPolling();
		document.addEventListener( 'mouseup', this.onMouseUp, true );
		window.addEventListener( 'scroll', this.onScroll, true );
		window.addEventListener( 'resize', this.onScroll, true );
	},
	beforeUnmount() {
		document.removeEventListener( 'mouseup', this.onMouseUp, true );
		window.removeEventListener( 'scroll', this.onScroll, true );
		window.removeEventListener( 'resize', this.onScroll, true );
		this.stopNewThreadPolling();
		if ( this.hidePreviewTimer ) {
			clearTimeout( this.hidePreviewTimer );
			this.hidePreviewTimer = null;
		}
	},
	methods: {
		msg( key ) {
			return mw.message( key ).text();
		},
		getSeenStorageKey() {
			const userId = Number( mw.config.get( 'wgUserId' ) ) || 0;
			// Storage shape: threadId -> highest seen commentId for this user/page.
			return `pagecomments-seen-v1:${userId}:${this.pageId}`;
		},
		loadSeenThreads() {
			this.seenThreadComments = {};
			if ( !this.pageId || typeof window === 'undefined' || !window.localStorage ) {
				return;
			}
			try {
				const raw = window.localStorage.getItem( this.getSeenStorageKey() );
				if ( !raw ) {
					return;
				}
				const parsed = JSON.parse( raw );
				if ( !parsed || typeof parsed !== 'object' ) {
					return;
				}
				const next = {};
				for ( const key of Object.keys( parsed ) ) {
					const threadId = Number( key );
					const commentId = Number( parsed[key] );
					if (
						Number.isInteger( threadId ) && threadId > 0 &&
						Number.isInteger( commentId ) && commentId >= 0
					) {
						next[String( threadId )] = commentId;
					}
				}
				this.seenThreadComments = next;
			} catch ( e ) {}
		},
		saveSeenThreads() {
			if ( !this.pageId || typeof window === 'undefined' || !window.localStorage ) {
				return;
			}
			try {
				window.localStorage.setItem(
					this.getSeenStorageKey(),
					JSON.stringify( this.seenThreadComments )
				);
			} catch ( e ) {}
		},
		getLatestThreadCommentId( thread ) {
			if ( !thread || !thread.comments || !thread.comments.length ) {
				return 0;
			}
			let maxId = 0;
			for ( const comment of thread.comments ) {
				const id = Number( comment.id );
				if ( Number.isInteger( id ) && id > maxId ) {
					maxId = id;
				}
			}
			return maxId;
		},
		isOwnComment( comment ) {
			if ( !comment ) {
				return false;
			}
			const actorId = Number( comment.actorId );
			if ( this.currentUserActorId > 0 && Number.isInteger( actorId ) && actorId > 0 ) {
				return actorId === this.currentUserActorId;
			}
			const actorName = String( comment.actorName || '' );
			if ( !actorName || !this.currentUserName ) {
				return false;
			}
			return actorName === this.currentUserName;
		},
		getLatestThreadExternalCommentId( thread ) {
			if ( !thread || !thread.comments || !thread.comments.length ) {
				return 0;
			}
			let maxId = 0;
			for ( const comment of thread.comments ) {
				if ( this.isOwnComment( comment ) ) {
					continue;
				}
				const id = Number( comment.id );
				if ( Number.isInteger( id ) && id > maxId ) {
					maxId = id;
				}
			}
			return maxId;
		},
		isThreadUnseen( threadId ) {
			const thread = this.threads.find( ( item ) => item.id === threadId );
			if ( !thread ) {
				return false;
			}
			const latestCommentId = this.getLatestThreadExternalCommentId( thread );
			if ( latestCommentId <= 0 ) {
				return false;
			}
			const seenCommentId = Number( this.seenThreadComments[String( threadId )] ) || 0;
			return latestCommentId > seenCommentId;
		},
		refreshUnseenHighlightDots() {
			highlight.updateUnseenHighlightClasses(
				( threadId ) => this.isThreadUnseen( Number( threadId ) )
			);
		},
		markThreadSeen( threadId ) {
			const thread = this.threads.find( ( item ) => item.id === threadId );
			if ( !thread ) {
				return;
			}
			const latestCommentId = this.getLatestThreadCommentId( thread );
			if ( latestCommentId <= 0 ) {
				return;
			}
			const key = String( threadId );
			const seenCommentId = Number( this.seenThreadComments[key] ) || 0;
			if ( latestCommentId <= seenCommentId ) {
				return;
			}
			this.seenThreadComments[key] = latestCommentId;
			this.saveSeenThreads();
			this.refreshUnseenHighlightDots();
		},
		commentCountLabel( count ) {
			return mw.message( 'pagecomments-ui-comment-count', Number( count ) || 0 ).text();
		},
		onScroll() {
			this.showAnchorButton = false;
			this.hideThreadPreview();
		},
		onMouseUp( event ) {
			if ( !this.canWrite ) {
				return;
			}
			if ( this.pendingAnchor ) {
				this.showAnchorButton = false;
				return;
			}
			const targetElement = anchorUtil.getEventTargetElement( event );
			if ( targetElement && targetElement.closest( '#pagecomments-root' ) ) {
				return;
			}
			const selection = window.getSelection();
			if ( !selection || selection.rangeCount === 0 || selection.isCollapsed ) {
				this.showAnchorButton = false;
				return;
			}
			const range = selection.getRangeAt( 0 );
			const articleRoot = anchorUtil.getArticleRoot();
			if (
				!anchorUtil.isInArticle( range.commonAncestorContainer, articleRoot ) ||
				!anchorUtil.isInArticle( range.startContainer, articleRoot ) ||
				!anchorUtil.isInArticle( range.endContainer, articleRoot )
			) {
				this.showAnchorButton = false;
				return;
			}
			const anchor = anchorUtil.buildAnchorFromRange( range, articleRoot );
			if ( !anchor || !anchor.exact ) {
				this.showAnchorButton = false;
				return;
			}
			if ( anchorUtil.hasOverlappingAnchor( anchor, this.threads, articleRoot ) ) {
				this.showAnchorButton = false;
				this.errorMessage = this.msg( 'pagecomments-ui-overlap-selection' );
				return;
			}
			const rect = range.getBoundingClientRect();
			const buttonWidth = 120;
			const buttonHeight = 32;
			let left = rect.right - buttonWidth;
			left = Math.min( Math.max( 8, left ), window.innerWidth - buttonWidth - 8 );
			let top = rect.bottom + 6;
			if ( top + buttonHeight > window.innerHeight - 8 ) {
				top = Math.max( rect.top - buttonHeight - 6, 8 );
			}
			this.anchorButtonStyle = {
				top: `${top}px`,
				left: `${left}px`
			};
			this.capturedAnchor = anchor;
			this.showAnchorButton = true;
		},
		openNewCommentComposer() {
			if ( !this.capturedAnchor ) {
				return;
			}
			this.pendingAnchor = this.capturedAnchor;
			this.newThreadBody = '';
			this.showAnchorButton = false;
			this.selectedThreadId = null;
			highlight.updateSelectedHighlightClasses( null );
			this.isPanelOpen = false;
			const anchorLeft = parseFloat( this.anchorButtonStyle.left ) || 8;
			const anchorTop = parseFloat( this.anchorButtonStyle.top ) || 8;
			this.composerStyle = {
				top: `${anchorTop + 34}px`,
				left: `${anchorLeft}px`
			};
			this.$nextTick( () => this.positionFloatingComposer() );
			const selection = window.getSelection();
			if ( selection ) {
				selection.removeAllRanges();
			}
		},
		closePanel() {
			this.isPanelOpen = false;
			this.selectedThreadId = null;
			highlight.updateSelectedHighlightClasses( null );
		},
		cancelNewThread() {
			this.pendingAnchor = null;
			this.newThreadBody = '';
			this.capturedAnchor = null;
		},
		positionFloatingComposer() {
			const node = this.$refs.floatingComposer;
			if ( !node ) {
				return;
			}
			// Keep the floating composer inside viewport bounds near the current selection.
			const margin = 8;
			const width = node.offsetWidth || 350;
			const height = node.offsetHeight || 240;
			let top = parseFloat( this.composerStyle.top ) || margin;
			let left = parseFloat( this.composerStyle.left ) || margin;
			left = Math.min( Math.max( margin, left ), window.innerWidth - width - margin );
			top = Math.min( Math.max( margin, top ), window.innerHeight - height - margin );
			this.composerStyle = {
				top: `${top}px`,
				left: `${left}px`
			};
		},
		startNewThreadPolling() {
			this.stopNewThreadPolling();
			if ( !this.pageId ) {
				return;
			}
			// Keep local state stable; pull full list only when a new thread appears.
			this.newThreadPollTimer = setInterval( () => {
				this.refreshThreadsIfNewThreadAdded();
			}, 15000 );
		},
		stopNewThreadPolling() {
			if ( !this.newThreadPollTimer ) {
				return;
			}
			clearInterval( this.newThreadPollTimer );
			this.newThreadPollTimer = null;
		},
		hasNewThread( nextThreads ) {
			if ( !Array.isArray( nextThreads ) || !nextThreads.length ) {
				return false;
			}
			const currentThreadIds = new Set(
				this.threads.map( ( thread ) => Number( thread.id ) )
			);
			for ( const thread of nextThreads ) {
				const threadId = Number( thread.id );
				if (
					Number.isInteger( threadId ) &&
					threadId > 0 &&
					!currentThreadIds.has( threadId )
				) {
					return true;
				}
			}
			return false;
		},
		async refreshThreadsIfNewThreadAdded() {
			if ( this.newThreadPollInFlight || !this.pageId ) {
				return;
			}
			this.newThreadPollInFlight = true;
			try {
				const nextThreads = await this.fetchThreadsFromApi();
				if ( !this.hasNewThread( nextThreads ) ) {
					return;
				}
				this.threads = nextThreads;
				this.syncCollapsedThreads();
				this.$nextTick( () => this.applyHighlights() );
			} catch ( e ) {
				// Ignore polling errors; user actions continue to use local state.
			} finally {
				this.newThreadPollInFlight = false;
			}
		},
		async fetchThreadsFromApi() {
			const api = new mw.Api();
			const data = await api.get( {
				action: 'pagecomments',
				pcaction: 'list',
				pageid: this.pageId,
				format: 'json'
			} );
			return ( data.pagecomments && data.pagecomments.threads ) || [];
		},
		isThreadCollapsed( threadId ) {
			return !!this.collapsedThreads[threadId];
		},
		toggleThreadCollapsed( threadId ) {
			this.collapsedThreads[threadId] = !this.isThreadCollapsed( threadId );
		},
		syncCollapsedThreads() {
			const next = {};
			for ( const thread of this.threads ) {
				if ( Object.prototype.hasOwnProperty.call( this.collapsedThreads, thread.id ) ) {
					next[thread.id] = !!this.collapsedThreads[thread.id];
					continue;
				}
				next[thread.id] = thread.state === 'resolved';
			}
			this.collapsedThreads = next;
		},
		async fetchThreads( showLoading = true ) {
			if ( showLoading ) {
				this.loading = true;
			}
			this.errorMessage = '';
			try {
				this.threads = await this.fetchThreadsFromApi();
				this.syncCollapsedThreads();
				this.$nextTick( () => this.applyHighlights() );
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			} finally {
				if ( showLoading ) {
					this.loading = false;
				}
			}
		},
		async submitNewThread() {
			if ( !this.pendingAnchor || !this.newThreadBody.trim() ) {
				return;
			}
			const articleRoot = anchorUtil.getArticleRoot();
			if ( anchorUtil.hasOverlappingAnchor( this.pendingAnchor, this.threads, articleRoot ) ) {
				this.errorMessage = this.msg( 'pagecomments-ui-overlap-selection' );
				this.pendingAnchor = null;
				this.newThreadBody = '';
				return;
			}
			this.errorMessage = '';
			const pendingAnchor = this.pendingAnchor;
			const body = this.newThreadBody.trim();
			try {
				const api = new mw.Api();
				const data = await api.postWithToken( 'csrf', {
					action: 'pagecomments',
					pcaction: 'create',
					pageid: this.pageId,
					anchor: JSON.stringify( pendingAnchor ),
					body,
					format: 'json'
				} );
				const payload = data && data.pagecomments ? data.pagecomments : {};
				const threadId = Number( payload.threadId );
				const commentId = Number( payload.commentId );
				if ( !threadId || !commentId ) {
					this.pendingAnchor = null;
					this.newThreadBody = '';
					this.capturedAnchor = null;
					await this.fetchThreads( false );
					this.hideThreadPreview();
					this.isPanelOpen = true;
					return;
				}
				const newThread = panelState.buildThreadFromCreateResult( {
					threadId,
					commentId,
					pageId: this.pageId,
					revisionId: Number( mw.config.get( 'wgRevisionId' ) ) || 0,
					pendingAnchor,
					body
				} );
					this.threads.unshift( newThread );
					this.collapsedThreads[threadId] = false;
					this.selectedThreadId = threadId;
					this.markThreadSeen( threadId );
					this.hideThreadPreview();
					this.isPanelOpen = true;
					this.$nextTick( () => {
						this.applyHighlights();
						this.scrollToThreadInPanel( threadId, false );
					} );
					this.pendingAnchor = null;
					this.newThreadBody = '';
					this.capturedAnchor = null;
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			}
		},
		toggleReply( threadId ) {
			this.markThreadSeen( threadId );
			this.replyOpen[threadId] = !this.replyOpen[threadId];
			if ( this.replyOpen[threadId] ) {
				this.collapsedThreads[threadId] = false;
			}
			if ( !this.replyOpen[threadId] ) {
				this.replyBody[threadId] = '';
			}
		},
		async submitReply( threadId ) {
			this.markThreadSeen( threadId );
			const body = this.replyBody[threadId] || '';
			if ( !body.trim() ) {
				return;
			}
			this.errorMessage = '';
			try {
				const api = new mw.Api();
				const data = await api.postWithToken( 'csrf', {
					action: 'pagecomments',
					pcaction: 'reply',
					threadid: threadId,
					body,
					format: 'json'
				} );
				const payload = data && data.pagecomments ? data.pagecomments : {};
				const commentId = Number( payload.commentId );
				const updated = commentId &&
					panelState.appendReply( this.threads, threadId, commentId, body.trim() );
				if ( updated ) {
					this.markThreadSeen( threadId );
				} else {
					await this.fetchThreads( false );
					this.markThreadSeen( threadId );
				}
				this.replyBody[threadId] = '';
				this.replyOpen[threadId] = false;
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			}
		},
		saveEditedComment( payload ) {
			const threadId = Number( payload.threadId );
			const commentId = Number( payload.commentId );
			const body = String( payload.body || '' ).trim();
			if ( !body ) {
				return;
			}
			this.markThreadSeen( threadId );
			this.errorMessage = '';
			const api = new mw.Api();
			api.postWithToken( 'csrf', {
				action: 'pagecomments',
				pcaction: 'editcomment',
				commentid: commentId,
				body,
				format: 'json'
			} ).then( () => {
				const updated = panelState.updateCommentBody( this.threads, threadId, commentId, body );
				if ( !updated ) {
					return this.fetchThreads( false );
				}
				if ( typeof payload.onDone === 'function' ) {
					payload.onDone();
				}
				return null;
			} ).catch( () => {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			} );
		},
		deleteComment( payload ) {
			const threadId = Number( payload.threadId );
			const commentId = Number( payload.commentId );
			this.markThreadSeen( threadId );
			this.errorMessage = '';
			if ( typeof payload.onDone === 'function' ) {
				payload.onDone();
			}
			const api = new mw.Api();
			api.postWithToken( 'csrf', {
				action: 'pagecomments',
				pcaction: 'deletecomment',
				commentid: commentId,
				format: 'json'
			} ).then( ( data ) => {
				const resultPayload = data && data.pagecomments ? data.pagecomments : {};
				const updated = panelState.removeComment( this.threads, threadId, commentId );
				if ( updated.removed ) {
					const threadDeleted = updated.threadDeleted || !!resultPayload.threadDeleted;
					if ( threadDeleted ) {
						delete this.collapsedThreads[threadId];
						delete this.replyOpen[threadId];
						delete this.replyBody[threadId];
						if ( this.selectedThreadId === threadId ) {
							this.selectedThreadId = null;
						}
					}
					this.$nextTick( () => this.applyHighlights() );
					// Delete response already includes authoritative thread removal state.
					// Keep panel stable: no full-list background refresh after delete.
				} else if ( resultPayload.threadId ) {
					return this.fetchThreads( false );
				}
				return null;
			} ).catch( () => {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			} );
		},
		async setThreadState( threadId, state ) {
			this.markThreadSeen( threadId );
			this.errorMessage = '';
			try {
				const api = new mw.Api();
				await api.postWithToken( 'csrf', {
					action: 'pagecomments',
					pcaction: state === 'resolved' ? 'resolve' : 'reopen',
					threadid: threadId,
					format: 'json'
				} );
				const updated = panelState.setThreadState( this.threads, threadId, state );
				if ( updated ) {
					this.collapsedThreads[threadId] = state === 'resolved';
				} else {
					await this.fetchThreads( false );
				}
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			}
		},
		selectThread( threadId ) {
			this.hideThreadPreview();
			this.markThreadSeen( threadId );
			const panelWasOpen = this.isPanelOpen;
			this.pendingAnchor = null;
			this.newThreadBody = '';
			this.capturedAnchor = null;
			this.selectedThreadId = threadId;
			this.isPanelOpen = true;
			this.collapsedThreads[threadId] = false;
			highlight.updateSelectedHighlightClasses( this.selectedThreadId );
			this.scrollToHighlight( threadId );
			this.$nextTick( () => this.scrollToThreadInPanel( threadId, panelWasOpen ) );
		},
		scrollToHighlight( threadId ) {
			const marker = document.querySelector( `.pagecomments-highlight[data-thread-id="${threadId}"]` );
			if ( marker ) {
				marker.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
		},
		scrollToThreadInPanel( threadId, panelWasOpen ) {
			const doScroll = () => {
				if ( !this.$el ) {
					return;
				}
				const panelBody = this.$el.querySelector( '.pagecomments-panel-body' );
				const threadNode = this.$el.querySelector(
					`.pagecomments-thread[data-thread-id="${threadId}"]`
				);
				if ( !panelBody || !threadNode ) {
					return;
				}
				const top = Math.max( 0, threadNode.offsetTop - panelBody.offsetTop - 8 );
				panelBody.scrollTo( {
					top,
					behavior: 'smooth'
				} );
			};
			if ( panelWasOpen ) {
				doScroll();
				return;
			}
			setTimeout( doScroll, 200 );
		},
		onThreadHighlightHover( threadId, sourceElement ) {
			if ( this.isPanelOpen ) {
				this.hideThreadPreview();
				return;
			}
			if ( this.hidePreviewTimer ) {
				clearTimeout( this.hidePreviewTimer );
				this.hidePreviewTimer = null;
			}
			const thread = this.threads.find( ( item ) => item.id === threadId );
			if ( !thread || !thread.comments || !thread.comments.length ) {
				this.hideThreadPreview();
				return;
			}
			const firstComment = thread.comments[0];
			const anchorRect = sourceElement.getBoundingClientRect();
			this.highlightPreview = {
				visible: true,
				threadId,
				firstCommentBody: String( firstComment.body || '' ),
				commentCount: thread.comments.length,
				style: {
					top: `${anchorRect.bottom + 8}px`,
					left: `${Math.max( 8, anchorRect.left )}px`
				}
			};
			this.$nextTick( () => this.positionThreadPreview( anchorRect ) );
		},
		onThreadHighlightLeave() {
			if ( this.hidePreviewTimer ) {
				clearTimeout( this.hidePreviewTimer );
			}
			this.hidePreviewTimer = setTimeout( () => {
				this.hidePreviewTimer = null;
				this.hideThreadPreview();
			}, 90 );
		},
		positionThreadPreview( anchorRect ) {
			const card = this.$refs.highlightPreviewCard;
			if ( !card || !this.highlightPreview.visible ) {
				return;
			}
			const margin = 8;
			const viewportWidth = document.documentElement.clientWidth || window.innerWidth;
			const viewportHeight = document.documentElement.clientHeight || window.innerHeight;
			const cardWidth = card.offsetWidth || 320;
			const cardHeight = card.offsetHeight || 96;
			let left = anchorRect.left + ( ( anchorRect.width || 0 ) - cardWidth ) / 2;
			const maxLeft = Math.max( margin, viewportWidth - cardWidth - margin );
			left = Math.min( Math.max( margin, left ), maxLeft );
			let top = anchorRect.top - cardHeight - 8;
			if ( top < margin ) {
				top = anchorRect.bottom + 8;
			}
			const maxTop = Math.max( margin, viewportHeight - cardHeight - margin );
			top = Math.min( Math.max( margin, top ), maxTop );
			this.highlightPreview.style = {
				top: `${top}px`,
				left: `${left}px`
			};
		},
		hideThreadPreview() {
			this.highlightPreview.visible = false;
			this.highlightPreview.threadId = null;
		},
		buildHighlightSignature() {
			return this.threads.map( ( thread ) => {
				const anchor = thread.anchor || {};
				return [
					thread.id,
					thread.state || 'open',
					anchor.start !== undefined ? anchor.start : '',
					anchor.end !== undefined ? anchor.end : '',
					anchor.exact || ''
				].join( '|' );
			} ).join( '||' );
		},
		applyHighlights() {
			const nextSignature = this.buildHighlightSignature();
			if ( nextSignature === this.lastHighlightSignature ) {
				highlight.updateSelectedHighlightClasses( this.selectedThreadId );
				this.refreshUnseenHighlightDots();
				return;
			}
			threadView.applyHighlights(
				this.threads,
				this.selectedThreadId,
				this.hideResolvedHighlights,
				( threadId ) => this.selectThread( threadId ),
				( threadId, sourceElement ) => this.onThreadHighlightHover( threadId, sourceElement ),
				() => this.onThreadHighlightLeave()
			);
			this.lastHighlightSignature = nextSignature;
			this.refreshUnseenHighlightDots();
		}
	}
};
</script>
