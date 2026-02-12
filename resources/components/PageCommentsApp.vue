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

		<aside
			class="pagecomments-panel"
			:class="{ 'is-open': isPanelOpen }"
		>
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
					<div v-if="pendingAnchor && canWrite" class="pagecomments-composer">
						<h3>{{ msg( 'pagecomments-ui-new-comment' ) }}</h3>
						<blockquote class="pagecomments-anchor-preview">
							{{ pendingAnchor.exact }}
						</blockquote>
						<textarea
							v-model="newThreadBody"
							class="pagecomments-textarea"
							rows="3"
						></textarea>
						<div class="pagecomments-actions">
							<button class="pagecomments-btn" @click="submitNewThread">
								{{ msg( 'pagecomments-ui-submit' ) }}
							</button>
							<button class="pagecomments-btn pagecomments-btn-quiet" @click="cancelNewThread">
								{{ msg( 'pagecomments-ui-cancel' ) }}
							</button>
						</div>
					</div>

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
							:class="{
								'is-selected': selectedThreadId === thread.id,
								'is-open': thread.state === 'open',
								'is-resolved': thread.state === 'resolved'
							}"
							@click="selectThread( thread.id )"
						>
							<div class="pagecomments-thread-head">
								<div class="pagecomments-thread-head-main">
									<strong>{{ thread.actorName }}</strong>
									<span class="pagecomments-thread-state">{{ thread.state }}</span>
								</div>
								<button
									class="pagecomments-thread-toggle"
									@click.stop="toggleThreadCollapsed( thread.id )"
								>
									{{ isThreadCollapsed( thread.id ) ? msg( 'pagecomments-ui-show-thread' ) : msg( 'pagecomments-ui-hide-thread' ) }}
								</button>
							</div>
							<div v-if="!isThreadCollapsed( thread.id )" class="pagecomments-thread-body">
								<blockquote class="pagecomments-anchor-preview">
									{{ thread.excerpt }}
								</blockquote>
								<p v-if="thread.orphaned" class="pagecomments-note">
									{{ msg( 'pagecomments-ui-orphaned' ) }}
								</p>
								<ul class="pagecomments-comments">
									<li v-for="comment in thread.comments" :key="comment.id">
										<div class="pagecomments-comment-meta">
											<strong>{{ comment.actorName }}</strong>
											<span>{{ formatTimestamp( comment.createdAt ) }}</span>
										</div>
										<p class="pagecomments-comment-body">{{ comment.body }}</p>
									</li>
								</ul>
								<div class="pagecomments-actions">
									<button
										v-if="canWrite"
										class="pagecomments-btn pagecomments-btn-quiet"
										@click.stop="toggleReply( thread.id )"
									>
										{{ msg( 'pagecomments-ui-reply' ) }}
									</button>
									<button
										v-if="canWrite && thread.state === 'open'"
										class="pagecomments-btn pagecomments-btn-quiet"
										@click.stop="setThreadState( thread.id, 'resolved' )"
									>
										{{ msg( 'pagecomments-ui-resolve' ) }}
									</button>
									<button
										v-if="canWrite && thread.state === 'resolved'"
										class="pagecomments-btn pagecomments-btn-quiet"
										@click.stop="setThreadState( thread.id, 'open' )"
									>
										{{ msg( 'pagecomments-ui-reopen' ) }}
									</button>
								</div>
								<div v-if="replyOpen[thread.id]" class="pagecomments-reply">
									<textarea
										v-model="replyBody[thread.id]"
										class="pagecomments-textarea"
										rows="2"
									></textarea>
									<div class="pagecomments-actions">
										<button class="pagecomments-btn" @click.stop="submitReply( thread.id )">
											{{ msg( 'pagecomments-ui-submit' ) }}
										</button>
										<button class="pagecomments-btn pagecomments-btn-quiet" @click.stop="toggleReply( thread.id )">
											{{ msg( 'pagecomments-ui-cancel' ) }}
										</button>
									</div>
								</div>
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

module.exports = exports = {
	name: 'PageCommentsApp',
	data() {
		const config = mw.config.get( 'wgPageComments' ) || {};
		return {
			pageId: Number( config.pageId ) || 0,
			canWrite: !!config.canWrite,
			threads: [],
			loading: true,
			errorMessage: '',
			selectedThreadId: null,
			showAnchorButton: false,
			anchorButtonStyle: { top: '0px', left: '0px' },
			capturedAnchor: null,
			pendingAnchor: null,
			newThreadBody: '',
			replyOpen: {},
			replyBody: {},
			isPanelOpen: false,
			syncTimer: null,
			collapsedThreads: {}
		};
	},
	mounted() {
		this.fetchThreads();
		document.addEventListener( 'mouseup', this.onMouseUp, true );
		window.addEventListener( 'scroll', this.onScroll, true );
		window.addEventListener( 'resize', this.onScroll, true );
	},
	beforeUnmount() {
		document.removeEventListener( 'mouseup', this.onMouseUp, true );
		window.removeEventListener( 'scroll', this.onScroll, true );
		window.removeEventListener( 'resize', this.onScroll, true );
		if ( this.syncTimer ) {
			clearTimeout( this.syncTimer );
			this.syncTimer = null;
		}
	},
	methods: {
		msg( key ) {
			return mw.message( key ).text();
		},
		onScroll() {
			this.showAnchorButton = false;
		},
		onMouseUp( event ) {
			if ( !this.canWrite ) {
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
			this.isPanelOpen = true;
			const selection = window.getSelection();
			if ( selection ) {
				selection.removeAllRanges();
			}
		},
		closePanel() {
			this.isPanelOpen = false;
		},
		cancelNewThread() {
			this.pendingAnchor = null;
			this.newThreadBody = '';
		},
		scheduleBackgroundSync() {
			if ( this.syncTimer ) {
				clearTimeout( this.syncTimer );
			}
			this.syncTimer = setTimeout( () => {
				this.syncTimer = null;
				const api = new mw.Api();
				api.get( {
					action: 'pagecomments',
					pcaction: 'list',
					pageid: this.pageId,
					format: 'json'
				} ).then( ( data ) => {
					this.threads = ( data.pagecomments && data.pagecomments.threads ) || [];
					this.syncCollapsedThreads();
					this.$nextTick( () => this.applyHighlights() );
				} ).catch( () => {} );
			}, 900 );
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
		async fetchThreads() {
			this.loading = true;
			this.errorMessage = '';
			try {
				const api = new mw.Api();
				const data = await api.get( {
					action: 'pagecomments',
					pcaction: 'list',
					pageid: this.pageId,
					format: 'json'
				} );
				this.threads = ( data.pagecomments && data.pagecomments.threads ) || [];
				this.syncCollapsedThreads();
				this.$nextTick( () => this.applyHighlights() );
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			} finally {
				this.loading = false;
			}
		},
		async submitNewThread() {
			if ( !this.pendingAnchor || !this.newThreadBody.trim() ) {
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
					await this.fetchThreads();
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
				this.$nextTick( () => this.applyHighlights() );
				this.scheduleBackgroundSync();
				this.pendingAnchor = null;
				this.newThreadBody = '';
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			}
		},
		toggleReply( threadId ) {
			this.replyOpen[threadId] = !this.replyOpen[threadId];
			if ( this.replyOpen[threadId] ) {
				this.collapsedThreads[threadId] = false;
			}
			if ( !this.replyOpen[threadId] ) {
				this.replyBody[threadId] = '';
			}
		},
		async submitReply( threadId ) {
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
					this.scheduleBackgroundSync();
				} else {
					await this.fetchThreads();
				}
				this.replyBody[threadId] = '';
				this.replyOpen[threadId] = false;
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			}
		},
		async setThreadState( threadId, state ) {
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
					this.scheduleBackgroundSync();
				} else {
					await this.fetchThreads();
				}
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			}
		},
		selectThread( threadId ) {
			this.selectedThreadId = threadId;
			this.isPanelOpen = true;
			this.collapsedThreads[threadId] = false;
			highlight.updateSelectedHighlightClasses( this.selectedThreadId );
			this.scrollToHighlight( threadId );
		},
		scrollToHighlight( threadId ) {
			const marker = document.querySelector( `.pagecomments-highlight[data-thread-id="${threadId}"]` );
			if ( marker ) {
				marker.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
		},
		applyHighlights() {
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
			for ( const thread of this.threads ) {
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

			const applied = highlight.applyMatchesToDom(
				map,
				matches,
				( threadId ) => this.selectThread( threadId )
			);
			const appliedIds = new Set( applied.map( ( item ) => item.threadId ) );
			for ( const thread of this.threads ) {
				if ( !appliedIds.has( thread.id ) ) {
					thread.orphaned = true;
				}
			}
			highlight.updateSelectedHighlightClasses( this.selectedThreadId );
		},
		formatTimestamp( mwTimestamp ) {
			if ( !mwTimestamp || mwTimestamp.length < 12 ) {
				return '';
			}
			const y = mwTimestamp.slice( 0, 4 );
			const m = mwTimestamp.slice( 4, 6 );
			const d = mwTimestamp.slice( 6, 8 );
			const hh = mwTimestamp.slice( 8, 10 );
			const mm = mwTimestamp.slice( 10, 12 );
			return `${y}-${m}-${d} ${hh}:${mm}`;
		}
	}
};
</script>
