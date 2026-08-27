CREATE DATABASE IF NOT EXISTS hrcanvas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hrcanvas;

CREATE TABLE IF NOT EXISTS employees (
    id VARCHAR(100) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL DEFAULT '',
    email VARCHAR(320) NOT NULL DEFAULT '',
    dob VARCHAR(32) NOT NULL DEFAULT '',
    doj VARCHAR(32) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_employees_name (full_name),
    INDEX idx_employees_location (location)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS photos (
    employee_id VARCHAR(100) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    relative_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id),
    CONSTRAINT fk_photos_employee FOREIGN KEY (employee_id)
        REFERENCES employees (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS birthday_cards (
    employee_id VARCHAR(100) NOT NULL,
    template_number SMALLINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    relative_path VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id),
    CONSTRAINT fk_cards_employee FOREIGN KEY (employee_id)
        REFERENCES employees (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS certificates (
    id CHAR(36) NOT NULL,
    employee_id VARCHAR(100) NOT NULL,
    unit_head_name VARCHAR(255) NOT NULL,
    matter TEXT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    pdf_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_certificates_employee (employee_id),
    CONSTRAINT fk_certificates_employee FOREIGN KEY (employee_id)
        REFERENCES employees (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    relative_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT '',
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_files_stored_name (stored_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value LONGTEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

