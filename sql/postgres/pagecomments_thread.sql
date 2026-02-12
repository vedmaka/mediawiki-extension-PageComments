CREATE TABLE /*_*/pagecomments_thread (
  pct_id BIGSERIAL PRIMARY KEY,
  pct_page_id INTEGER NOT NULL,
  pct_namespace INTEGER NOT NULL,
  pct_rev_id INTEGER NOT NULL DEFAULT 0,
  pct_anchor_json TEXT NOT NULL,
  pct_anchor_excerpt TEXT NOT NULL,
  pct_state VARCHAR(16) NOT NULL DEFAULT 'open',
  pct_actor_id BIGINT NOT NULL,
  pct_created_at CHAR(14) NOT NULL,
  pct_updated_at CHAR(14) NOT NULL
);

CREATE INDEX /*i*/pct_page_state ON /*_*/pagecomments_thread (pct_page_id, pct_state);
CREATE INDEX /*i*/pct_updated_at ON /*_*/pagecomments_thread (pct_updated_at);
