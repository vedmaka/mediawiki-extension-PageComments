CREATE TABLE /*_*/pagecomments_comment (
  pcc_id INTEGER PRIMARY KEY AUTOINCREMENT,
  pcc_thread_id INTEGER NOT NULL,
  pcc_parent_comment_id INTEGER NULL,
  pcc_actor_id INTEGER NOT NULL,
  pcc_body TEXT NOT NULL,
  pcc_created_at TEXT NOT NULL,
  pcc_deleted_at TEXT NULL
);

CREATE INDEX /*i*/pcc_thread_created ON /*_*/pagecomments_comment (pcc_thread_id, pcc_created_at);
CREATE INDEX /*i*/pcc_parent ON /*_*/pagecomments_comment (pcc_parent_comment_id);
