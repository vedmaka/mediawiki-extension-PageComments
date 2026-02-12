CREATE TABLE /*_*/pagecomments_comment (
  pcc_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pcc_thread_id BIGINT UNSIGNED NOT NULL,
  pcc_parent_comment_id BIGINT UNSIGNED NULL,
  pcc_actor_id BIGINT UNSIGNED NOT NULL,
  pcc_body MEDIUMTEXT NOT NULL,
  pcc_created_at BINARY(14) NOT NULL,
  pcc_deleted_at BINARY(14) NULL,
  PRIMARY KEY (pcc_id),
  KEY pcc_thread_created (pcc_thread_id, pcc_created_at),
  KEY pcc_parent (pcc_parent_comment_id)
) /*$wgDBTableOptions*/;
