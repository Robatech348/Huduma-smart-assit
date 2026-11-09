-- Huduma SmartAssist schema (MySQL 5.7+ / MariaDB 10.3+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS centres (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  county VARCHAR(80) NOT NULL,
  address VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  opening_hours VARCHAR(160) NOT NULL DEFAULT 'Mon-Fri 8:00am - 5:00pm',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  agency VARCHAR(160) NOT NULL,
  category VARCHAR(80) NOT NULL,
  summary TEXT NOT NULL,
  requirements TEXT NOT NULL,          -- one item per line
  steps TEXT NOT NULL,                 -- one step per line
  fee VARCHAR(160) NOT NULL DEFAULT 'Free',
  processing_time VARCHAR(160) NOT NULL DEFAULT 'Varies',
  keywords VARCHAR(255) NOT NULL DEFAULT '',
  bookable TINYINT(1) NOT NULL DEFAULT 1,
  apply_url VARCHAR(255) NULL,          -- official online application portal
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS centre_services (
  centre_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (centre_id, service_id),
  FOREIGN KEY (centre_id) REFERENCES centres(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  keywords VARCHAR(255) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  centre_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (centre_id) REFERENCES centres(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  centre_id INT UNSIGNED NOT NULL,
  slot_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  booked SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  UNIQUE KEY uq_slot (centre_id, slot_date, start_time),
  KEY idx_date (slot_date),
  FOREIGN KEY (centre_id) REFERENCES centres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS citizens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(16) NOT NULL UNIQUE,
  manage_token CHAR(40) NOT NULL UNIQUE,
  slot_id INT UNSIGNED NOT NULL,
  centre_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NULL,
  phone VARCHAR(40) NULL,
  status ENUM('booked','checked_in','served','no_show','cancelled') NOT NULL DEFAULT 'booked',
  channel ENUM('online','assisted') NOT NULL DEFAULT 'online',
  needs_assistance TINYINT(1) NOT NULL DEFAULT 0,
  staff_notes VARCHAR(500) NULL,
  reminder_sent_at DATETIME NULL,
  feedback_requested_at DATETIME NULL,
  checked_in_at DATETIME NULL,
  served_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  citizen_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_citizen (citizen_id),
  KEY idx_email (email),
  FOREIGN KEY (slot_id) REFERENCES slots(id),
  FOREIGN KEY (centre_id) REFERENCES centres(id),
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(16) NOT NULL UNIQUE,
  track_token CHAR(40) NOT NULL UNIQUE,
  appointment_id INT UNSIGNED NULL,
  centre_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NULL,
  phone VARCHAR(40) NULL,
  status ENUM('submitted','in_progress','action_needed','ready','completed','rejected') NOT NULL DEFAULT 'submitted',
  created_by INT UNSIGNED NULL,
  citizen_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_citizen (citizen_id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  FOREIGN KEY (centre_id) REFERENCES centres(id),
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_updates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL,
  public_note TEXT NULL,
  staff_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES service_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL UNIQUE,
  rating TINYINT UNSIGNED NOT NULL,
  prepared TINYINT(1) NULL,
  comment TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(160) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_text TEXT NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  type VARCHAR(40) NOT NULL,
  related_ref VARCHAR(16) NULL,
  status ENUM('queued','sent','logged','failed') NOT NULL DEFAULT 'queued',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assistant_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(1000) NOT NULL,
  source ENUM('ai','catalogue','fallback') NOT NULL,
  matched_service_id INT UNSIGNED NULL,
  helpful TINYINT(1) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  details VARCHAR(500) NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(160) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
