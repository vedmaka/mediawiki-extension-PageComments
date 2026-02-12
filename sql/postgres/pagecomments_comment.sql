CREATE TABLE /*_*/pagecomments_comment (
  pcc_id BIGSERIAL PRIMARY KEY,
  pcc_thread_id BIGINT NOT NULL,
  pcc_parent_comment_id BIGINT NULL,
  pcc_actor_id BIGINT NOT NULL,
  pcc_body TEXT NOT NULL,
  pcc_created_at CHAR(14) NOT NULL,
  pcc_deleted_at CHAR(14) NULL
);

CREATE INDEX /*i*/pcc_thread_created ON /*_*/pagecomments_comment (pcc_thread_id, pcc_created_at);
CREATE INDEX /*i*/pcc_parent ON /*_*/pagecomments_comment (pcc_parent_comment_id);
