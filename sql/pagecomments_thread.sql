CREATE TABLE /*_*/pagecomments_thread (
  pct_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pct_page_id INT UNSIGNED NOT NULL,
  pct_namespace INT NOT NULL,
  pct_rev_id INT UNSIGNED NOT NULL DEFAULT 0,
  pct_anchor_json MEDIUMTEXT NOT NULL,
  pct_anchor_excerpt TEXT NOT NULL,
  pct_state VARCHAR(16) NOT NULL DEFAULT 'open',
  pct_actor_id BIGINT UNSIGNED NOT NULL,
  pct_created_at BINARY(14) NOT NULL,
  pct_updated_at BINARY(14) NOT NULL,
  PRIMARY KEY (pct_id),
  KEY pct_page_state (pct_page_id, pct_state),
  KEY pct_updated_at (pct_updated_at)
) /*$wgDBTableOptions*/;
