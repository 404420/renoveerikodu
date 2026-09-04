CREATE TABLE IF NOT EXISTS workers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    phone VARCHAR(100) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    profession VARCHAR(190) NOT NULL,
    experience_years SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workers_name (last_name, first_name),
    KEY idx_workers_profession (profession),
    KEY idx_workers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS worker_skills (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    worker_id INT UNSIGNED NOT NULL,
    skill_name VARCHAR(190) NOT NULL,
    skill_level VARCHAR(40) NOT NULL DEFAULT 'good',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_worker_skills_worker (worker_id),
    CONSTRAINT fk_worker_skills_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workdays (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    worker_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    calculated_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_workdays_worker_date (worker_id, work_date),
    KEY idx_workdays_project (project_id),
    KEY idx_workdays_date (work_date),
    CONSTRAINT fk_workdays_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_workdays_project FOREIGN KEY (project_id) REFERENCES admin_objects(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
