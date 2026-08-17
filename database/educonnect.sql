CREATE DATABASE IF NOT EXISTS educonnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE educonnect;
DROP TABLE IF EXISTS tutoring_requests;
DROP TABLE IF EXISTS resource_purchases;
DROP TABLE IF EXISTS resources;
DROP TABLE IF EXISTS users;

CREATE TABLE users(
 user_id INT AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(255) NOT NULL,
 email VARCHAR(255) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 course VARCHAR(255) NOT NULL,
 year_of_study VARCHAR(50) NOT NULL,
 role ENUM('student','admin') NOT NULL DEFAULT 'student',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resources(
 resource_id INT AUTO_INCREMENT PRIMARY KEY,
 uploader_id INT NOT NULL,
 title VARCHAR(255) NOT NULL,
 course VARCHAR(255) DEFAULT NULL,
 course_unit VARCHAR(255) DEFAULT NULL,
 subject VARCHAR(255) NOT NULL,
 topic VARCHAR(255) DEFAULT NULL,
 resource_type VARCHAR(100) NOT NULL,
 description TEXT,
 file_path VARCHAR(255) NOT NULL,
 downloads INT NOT NULL DEFAULT 0,
 is_paid TINYINT(1) NOT NULL DEFAULT 0,
 price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 currency VARCHAR(10) NOT NULL DEFAULT 'GHS',
 uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_uploader_id(uploader_id),
 CONSTRAINT fk_resources_user FOREIGN KEY(uploader_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resource_purchases(
 purchase_id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 resource_id INT NOT NULL,
 tx_ref VARCHAR(255) NOT NULL,
 transaction_id VARCHAR(255) DEFAULT NULL,
 amount DECIMAL(10,2) NOT NULL,
 currency VARCHAR(10) NOT NULL DEFAULT 'GHS',
 provider VARCHAR(50) DEFAULT NULL,
 status ENUM('pending','successful','failed','cancelled') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unique_user_resource (user_id, resource_id),
 UNIQUE KEY unique_tx_ref (tx_ref),
 KEY idx_user_id(user_id),
 KEY idx_resource_id(resource_id),
 CONSTRAINT fk_purchases_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE,
 CONSTRAINT fk_purchases_resource FOREIGN KEY(resource_id) REFERENCES resources(resource_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tutoring_requests(
 request_id INT AUTO_INCREMENT PRIMARY KEY,
 student_id INT NOT NULL,
 subject VARCHAR(255) NOT NULL,
 topic VARCHAR(255) NOT NULL,
 preferred_date DATE NOT NULL,
 preferred_time TIME DEFAULT NULL,
 message TEXT,
 status ENUM('open','accepted','completed','cancelled') NOT NULL DEFAULT 'open',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_student_id(student_id),
 CONSTRAINT fk_tutoring_student FOREIGN KEY(student_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
