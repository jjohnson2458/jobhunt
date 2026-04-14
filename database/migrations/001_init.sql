-- claude_jobhunt initial schema
-- Database: footraffic (camouflaged)

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) DEFAULT '',
  last_name VARCHAR(100) DEFAULT '',
  role ENUM('admin','user') DEFAULT 'admin',
  is_active TINYINT(1) DEFAULT 1,
  totp_secret VARCHAR(64) DEFAULT NULL,
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_tracks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  is_active TINYINT(1) DEFAULT 1,
  role_keywords TEXT NOT NULL COMMENT 'comma-separated, used for search + scoring',
  exclude_keywords TEXT DEFAULT NULL,
  salary_floor INT UNSIGNED DEFAULT 0,
  locations TEXT DEFAULT NULL COMMENT 'comma-separated; "remote" allowed',
  remote_ok TINYINT(1) DEFAULT 1,
  resume_template VARCHAR(255) DEFAULT NULL COMMENT 'path under docs/ or resumes/',
  cover_letter_tone VARCHAR(100) DEFAULT 'professional',
  notes TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS listings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  track_id INT UNSIGNED DEFAULT NULL,
  source ENUM('indeed','ziprecruiter','monster','linkedin','manual','other') NOT NULL,
  source_url VARCHAR(1024) DEFAULT NULL,
  source_id VARCHAR(255) DEFAULT NULL COMMENT 'board-native id',
  dedupe_hash CHAR(40) NOT NULL COMMENT 'sha1(normalized title+company+location)',
  title VARCHAR(255) NOT NULL,
  company VARCHAR(255) NOT NULL,
  location VARCHAR(255) DEFAULT NULL,
  is_remote TINYINT(1) DEFAULT 0,
  salary_min INT DEFAULT NULL,
  salary_max INT DEFAULT NULL,
  salary_text VARCHAR(255) DEFAULT NULL,
  description LONGTEXT DEFAULT NULL,
  posted_at DATETIME DEFAULT NULL,
  fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  score TINYINT DEFAULT NULL COMMENT '0-100, AI/heuristic relevance',
  score_reason TEXT DEFAULT NULL,
  status ENUM('new','reviewed','starred','hidden','blacklisted','duplicate') DEFAULT 'new',
  UNIQUE KEY uniq_dedupe (dedupe_hash),
  KEY idx_track_status (track_id, status),
  KEY idx_source (source),
  KEY idx_posted (posted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id INT UNSIGNED NOT NULL,
  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('drafted','applied','phone_screen','interview','offer','rejected','ghosted','withdrawn') DEFAULT 'drafted',
  resume_path VARCHAR(512) DEFAULT NULL COMMENT 'tailored resume file',
  cover_letter_path VARCHAR(512) DEFAULT NULL,
  cover_letter_text LONGTEXT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  follow_up_at DATE DEFAULT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_listing (listing_id),
  KEY idx_status (status),
  CONSTRAINT fk_app_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applied_signatures (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  signature CHAR(40) NOT NULL UNIQUE COMMENT 'sha1(company+normalized title)',
  company VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  first_applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blacklist (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  track_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL = global',
  type ENUM('company','keyword','recruiter','domain') NOT NULL,
  pattern VARCHAR(255) NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scraper_runs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(50) NOT NULL,
  track_id INT UNSIGNED DEFAULT NULL,
  started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME DEFAULT NULL,
  status ENUM('running','success','partial','failed') DEFAULT 'running',
  listings_found INT UNSIGNED DEFAULT 0,
  listings_new INT UNSIGNED DEFAULT 0,
  error_message TEXT DEFAULT NULL,
  raw_log LONGTEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS error_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level VARCHAR(20) DEFAULT 'error',
  message TEXT NOT NULL,
  context LONGTEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `value` TEXT DEFAULT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
