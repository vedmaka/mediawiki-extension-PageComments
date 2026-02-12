# PageComments

Inline page comments for MediaWiki main namespace pages.

MVP features:
- Logged-in users can create comment threads from selected text.
- Users can reply inside threads.
- Users can delete their own comments (or any comment with `pagecomments-moderate`).
- Users can resolve/reopen threads.
- Client UI shown on `NS_MAIN` view pages.

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

## API module

Action API: `action=pagecomments`

- `pcaction=list&pageid=<id>`
- `pcaction=create&pageid=<id>&anchor=<json>&body=<text>&token=<csrf>`
- `pcaction=reply&threadid=<id>&body=<text>&token=<csrf>`
- `pcaction=deletecomment&commentid=<id>&token=<csrf>`
- `pcaction=resolve&threadid=<id>&token=<csrf>`
- `pcaction=reopen&threadid=<id>&token=<csrf>`

## Known limits

- No realtime syncing between users.
- Panel uses optimistic updates with a short background re-sync.
- Overlapping highlighted ranges are skipped to keep markup stable.
- If page content changes, some threads can become orphaned.
