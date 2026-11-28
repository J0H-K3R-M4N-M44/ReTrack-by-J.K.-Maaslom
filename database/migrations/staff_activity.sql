CREATE TABLE IF NOT EXISTS staff_activity_reads (
  user_id BIGINT UNSIGNED NOT NULL,
  event_group ENUM('repairs','payments','pickups') NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, event_group, event_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
