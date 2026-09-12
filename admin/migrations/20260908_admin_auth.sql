-- Käivita phpMyAdminis olemasolevas andmebaasis enne PHP failide asendamist.
-- admin_users tabelit, paroole ja rolle EI muudeta.
CREATE TABLE IF NOT EXISTS admin_auth_limits (
    bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    failures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_failure BIGINT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
    KEY auth_limits_cleanup (last_failure)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_auth_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) CHARACTER SET ascii NOT NULL,
    username VARBINARY(255) NOT NULL,
    outcome VARCHAR(20) CHARACTER SET ascii NOT NULL,
    occurred_at DATETIME NOT NULL,
    KEY auth_attempts_time (occurred_at),
    KEY auth_attempts_ip_time (ip_address, occurred_at)
) ENGINE=InnoDB;
