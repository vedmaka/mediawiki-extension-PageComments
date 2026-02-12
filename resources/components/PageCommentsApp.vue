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
						:class="{ 'is-selected': selectedThreadId === thread.id }"
						@click="selectThread( thread.id )"
					>
						<div class="pagecomments-thread-head">
							<strong>{{ thread.actorName }}</strong>
							<span class="pagecomments-thread-state">{{ thread.state }}</span>
						</div>
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
			</section>
		</aside>
	</div>
</template>

<script>
const highlight = require( '../highlight.js' );

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
			isPanelOpen: false
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
	},
	methods: {
		msg( key ) {
			return mw.message( key ).text();
		},
		onScroll() {
			this.showAnchorButton = false;
		},
		getArticleRoot() {
			return document.querySelector( '.mw-parser-output' ) || document.querySelector( '#mw-content-text' );
		},
		isInArticle( node ) {
			const root = this.getArticleRoot();
			if ( !root || !node ) {
				return false;
			}
			return root.contains( node );
		},
		getEventTargetElement( event ) {
			const target = event.target;
			if ( target instanceof Element ) {
				return target;
			}
			if ( target && target.parentElement instanceof Element ) {
				return target.parentElement;
			}
			return null;
		},
		onMouseUp( event ) {
			if ( !this.canWrite ) {
				return;
			}
			const targetElement = this.getEventTargetElement( event );
			if ( targetElement && targetElement.closest( '#pagecomments-root' ) ) {
				return;
			}

			const selection = window.getSelection();
			if ( !selection || selection.rangeCount === 0 || selection.isCollapsed ) {
				this.showAnchorButton = false;
				return;
			}

			const range = selection.getRangeAt( 0 );
			if (
				!this.isInArticle( range.commonAncestorContainer ) ||
				!this.isInArticle( range.startContainer ) ||
				!this.isInArticle( range.endContainer )
			) {
				this.showAnchorButton = false;
				return;
			}

			const anchor = this.buildAnchorFromRange( range );
			if ( !anchor || !anchor.exact ) {
				this.showAnchorButton = false;
				return;
			}

			const rect = range.getBoundingClientRect();
			this.anchorButtonStyle = {
				top: `${Math.max( rect.top - 36, 8 )}px`,
				left: `${Math.max( rect.left, 8 )}px`
			};
			this.capturedAnchor = anchor;
			this.showAnchorButton = true;
		},
		buildAnchorFromRange( range ) {
			const root = this.getArticleRoot();
			if ( !root ) {
				return null;
			}

			const exact = range.toString().trim();
			if ( !exact ) {
				return null;
			}

			const offsets = this.getRangeOffsets( range, root );
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
		},
		getRangeOffsets( range, root ) {
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
			try {
				const api = new mw.Api();
				await api.postWithToken( 'csrf', {
					action: 'pagecomments',
					pcaction: 'create',
					pageid: this.pageId,
					anchor: JSON.stringify( this.pendingAnchor ),
					body: this.newThreadBody,
					format: 'json'
				} );
				this.pendingAnchor = null;
				this.newThreadBody = '';
				await this.fetchThreads();
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			}
		},
		toggleReply( threadId ) {
			this.replyOpen[threadId] = !this.replyOpen[threadId];
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
				await api.postWithToken( 'csrf', {
					action: 'pagecomments',
					pcaction: 'reply',
					threadid: threadId,
					body,
					format: 'json'
				} );
				this.replyBody[threadId] = '';
				this.replyOpen[threadId] = false;
				await this.fetchThreads();
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
				await this.fetchThreads();
			} catch ( e ) {
				this.errorMessage = this.msg( 'pagecomments-ui-error-generic' );
			}
		},
		selectThread( threadId ) {
			this.selectedThreadId = threadId;
			this.isPanelOpen = true;
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
			const root = this.getArticleRoot();
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
