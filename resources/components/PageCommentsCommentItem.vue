<template>
	<li @click.stop>
		<div class="pagecomments-comment-meta">
			<div class="pagecomments-comment-meta-main">
				<strong>{{ comment.actorName }}</strong>
				<span>{{ formatTimestamp( comment.createdAt ) }}</span>
			</div>
			<div v-if="canManageComment" class="pagecomments-comment-controls">
				<button
					class="pagecomments-comment-menu-trigger"
					:title="msg( 'pagecomments-ui-more-actions' )"
					@click.stop="toggleCommentMenu"
				>
					&middot;&middot;&middot;
				</button>
				<div v-if="menuOpen" class="pagecomments-comment-menu">
					<button class="pagecomments-comment-menu-item" @click.stop="startEdit">
						{{ msg( 'pagecomments-ui-edit' ) }}
					</button>
					<button class="pagecomments-comment-menu-item is-danger" @click.stop="requestDelete">
						{{ msg( 'pagecomments-ui-delete' ) }}
					</button>
				</div>
			</div>
		</div>
		<p v-if="!editing" class="pagecomments-comment-body">
			{{ comment.body }}
		</p>
		<div v-else class="pagecomments-comment-edit">
			<textarea
				v-model="editBody"
				class="pagecomments-textarea"
				rows="2"
			></textarea>
			<div class="pagecomments-actions">
				<button class="pagecomments-btn" @click.stop="requestEdit">
					{{ msg( 'pagecomments-ui-submit' ) }}
				</button>
				<button class="pagecomments-btn pagecomments-btn-quiet" @click.stop="cancelEdit">
					{{ msg( 'pagecomments-ui-cancel' ) }}
				</button>
			</div>
		</div>
	</li>
</template>

<script>
const threadView = require( '../threadView.js' );
const anchorUtil = require( '../anchor.js' );

module.exports = exports = {
	name: 'PageCommentsCommentItem',
	props: {
		threadId: {
			type: Number,
			required: true
		},
		comment: {
			type: Object,
			required: true
		}
	},
	emits: [ 'edit-comment', 'delete-comment', 'editComment', 'deleteComment' ],
	data() {
		return {
			menuOpen: false,
			editing: false,
			editBody: String( this.comment.body || '' )
		};
	},
	computed: {
		canManageComment() {
			return this.isTruthyPermission( this.comment.canManage ) ||
				this.isTruthyPermission( this.comment.canEdit ) ||
				this.isTruthyPermission( this.comment.canDelete );
		}
	},
	watch: {
		'comment.body'( value ) {
			if ( !this.editing ) {
				this.editBody = String( value || '' );
			}
		}
	},
	mounted() {
		document.addEventListener( 'click', this.onDocumentClick, true );
	},
	beforeUnmount() {
		document.removeEventListener( 'click', this.onDocumentClick, true );
	},
	methods: {
		msg( key ) {
			return mw.message( key ).text();
		},
		formatTimestamp( mwTimestamp ) {
			return threadView.formatTimestamp( mwTimestamp );
		},
		isTruthyPermission( value ) {
			return value === '' || value === true || value === 1 || value === '1';
		},
		onDocumentClick( event ) {
			if ( !this.menuOpen ) {
				return;
			}
			const targetElement = anchorUtil.getEventTargetElement( event );
			if ( targetElement && targetElement.closest( '.pagecomments-comment-controls' ) ) {
				return;
			}
			this.menuOpen = false;
		},
		toggleCommentMenu() {
			this.menuOpen = !this.menuOpen;
		},
		startEdit() {
			this.menuOpen = false;
			this.editing = true;
			this.editBody = String( this.comment.body || '' );
		},
		cancelEdit() {
			this.editing = false;
			this.editBody = String( this.comment.body || '' );
		},
		requestEdit() {
			const body = this.editBody.trim();
			if ( !body ) {
				return;
			}
			const payload = {
				threadId: this.threadId,
				commentId: Number( this.comment.id ),
				body,
				onDone: () => {
					this.editing = false;
				}
			};
			this.$emit( 'edit-comment', payload );
			this.$emit( 'editComment', payload );
		},
		requestDelete() {
			this.menuOpen = false;
			const payload = {
				threadId: this.threadId,
				commentId: Number( this.comment.id ),
				onDone: () => {
					this.editing = false;
				}
			};
			this.$emit( 'delete-comment', payload );
			this.$emit( 'deleteComment', payload );
		}
	}
};
</script>
