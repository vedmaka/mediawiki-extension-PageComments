# PageComments

Inline page comments for MediaWiki.

# Requirements

* MediaWiki 1.43+

# Features

- Logged-in users can create comment threads from selected text.
- Users can reply inside threads.
- Users can delete their own comments (or any comment with `pagecomments-moderate`).
- Users can resolve/reopen threads.
- Per-user unseen tracking in `localStorage` with blue-dot indicators in text highlights and thread cards (own comments are ignored).

## Install

1. Add to `LocalSettings.php`:

```php
wfLoadExtension( 'PageComments' );
```

2. Run DB update:

```bash
php maintenance/run.php update
```

## Rights

- `pagecomments-write`: create comments and replies (granted to `user` by default).
- `pagecomments-moderate`: reserved moderation right (granted to `sysop` by default).

## Config

- `$wgPageCommentsEnabledNamespaces` default `[ 0 ]`
- `$wgPageCommentsMaxCommentLength` default `2000`
- `$wgPageCommentsMaxAnchorLength` default `600`
- `$wgPageCommentsHideResolvedHighlights` default `false` (when `true`, resolved threads are not highlighted in page text)

## API module

Action API: `action=pagecomments`

- `pcaction=list&pageid=<id>`
- `pcaction=create&pageid=<id>&anchor=<json>&body=<text>&token=<csrf>`
- `pcaction=reply&threadid=<id>&body=<text>&token=<csrf>`
- `pcaction=editcomment&commentid=<id>&body=<text>&token=<csrf>`
- `pcaction=deletecomment&commentid=<id>&token=<csrf>`
- `pcaction=resolve&threadid=<id>&token=<csrf>`
- `pcaction=reopen&threadid=<id>&token=<csrf>`

## Known limits

- No realtime syncing between users.
- Panel is state-first: comment/reply/edit/delete/resolve actions mutate local state without full-list refresh.
- Background polling reconciles panel state when remote thread/comment data changes.
- Overlapping highlighted ranges are skipped to keep markup stable.
- If page content changes, some threads can become orphaned (displayed at the Comments panel as orphaned).
