CREATE TABLE /*_*/pagecomments_thread (
  pct_id INTEGER PRIMARY KEY AUTOINCREMENT,
  pct_page_id INTEGER NOT NULL,
  pct_namespace INTEGER NOT NULL,
  pct_rev_id INTEGER NOT NULL DEFAULT 0,
  pct_anchor_json TEXT NOT NULL,
  pct_anchor_excerpt TEXT NOT NULL,
  pct_state TEXT NOT NULL DEFAULT 'open',
  pct_actor_id INTEGER NOT NULL,
  pct_created_at TEXT NOT NULL,
  pct_updated_at TEXT NOT NULL
);

CREATE INDEX /*i*/pct_page_state ON /*_*/pagecomments_thread (pct_page_id, pct_state);
CREATE INDEX /*i*/pct_updated_at ON /*_*/pagecomments_thread (pct_updated_at);
