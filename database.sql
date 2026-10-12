CREATE TABLE IF NOT EXISTS contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(80) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  message TEXT NOT NULL,
  language VARCHAR(2) NOT NULL DEFAULT 'et',
  message_et TEXT DEFAULT NULL,
  translation_source_hash CHAR(64) CHARACTER SET ascii DEFAULT NULL,
  translated_at DATETIME DEFAULT NULL,
  translation_provider VARCHAR(30) DEFAULT NULL,
  file_path JSON DEFAULT NULL,
  source VARCHAR(500) DEFAULT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contacts_created_at (created_at),
  KEY idx_contacts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(40) NOT NULL DEFAULT 'admin',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
