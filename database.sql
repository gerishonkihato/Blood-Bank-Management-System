CREATE DATABASE knbts_db;
USE knbts_db;

-- Users Table (Base for all roles)
CREATE TABLE users (
    userId VARCHAR(36) PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    passwordHash VARCHAR(255) NOT NULL,
    role ENUM('DONOR', 'RECIPIENT', 'ADMIN') NOT NULL,
    lastLogin DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Donors Table
CREATE TABLE donors (
    donorId VARCHAR(36) PRIMARY KEY,
    userId VARCHAR(36),
    encryptedName LONGTEXT,
    encryptedPhone LONGTEXT,
    bloodGroup VARCHAR(3),
    rhFactor VARCHAR(1),
    lastDonationDate DATE NULL,
    status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(userId)
);

-- Recipients Table
CREATE TABLE recipients (
    recipientId VARCHAR(36) PRIMARY KEY,
    userId VARCHAR(36),
    encryptedName LONGTEXT,
    encryptedPhone LONGTEXT,
    hospitalName VARCHAR(100),
    status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(userId)
);

-- Blood Requests (Encrypted Details)
CREATE TABLE blood_requests (
    requestId VARCHAR(36) PRIMARY KEY,
    recipientId VARCHAR(36),
    bloodGroup VARCHAR(3),
    quantity INT,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    requestTimestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    approvedBy VARCHAR(36) NULL,
    FOREIGN KEY (recipientId) REFERENCES recipients(recipientId)
);

-- Inventory
CREATE TABLE inventory (
    bloodType VARCHAR(4) PRIMARY KEY, -- e.g., A+
    unitsAvailable INT DEFAULT 0,
    lastUpdated DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Blood Units (records of individual donations)
CREATE TABLE blood_units (
    unitId VARCHAR(36) PRIMARY KEY,
    donorId VARCHAR(36),
    collectionDate DATE,
    status ENUM('COLLECTED', 'AVAILABLE', 'USED') DEFAULT 'COLLECTED',
    FOREIGN KEY (donorId) REFERENCES donors(donorId)
);

-- Audit Log (Security Requirement)
CREATE TABLE audit_log (
    logId BIGINT AUTO_INCREMENT PRIMARY KEY,
    userId VARCHAR(36),
    action VARCHAR(50),
    targetId VARCHAR(36),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert Default Admin (Password: admin123)
-- Hash generated via PHP password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO users (userId, username, passwordHash, role) VALUES 
('admin-001', 'admin', '$2y$10$x3eGh0q2kTKLw1CKTL2zD.W/L0H7/BBZ/qipem4HJjdK2aUegTae6', 'ADMIN');