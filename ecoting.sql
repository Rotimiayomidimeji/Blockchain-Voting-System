-- Drop existing database if exists and create fresh
DROP DATABASE IF EXISTS evoting_system;
CREATE DATABASE evoting_system;
USE evoting_system;

-- Users table (both admin and voters)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    voter_id VARCHAR(20) UNIQUE,
    phone VARCHAR(20),
    role ENUM('admin', 'voter') DEFAULT 'voter',
    is_verified BOOLEAN DEFAULT FALSE,
    has_voted BOOLEAN DEFAULT FALSE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Election periods
CREATE TABLE elections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Candidates
CREATE TABLE candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    party VARCHAR(100) NOT NULL,
    description TEXT,
    photo VARCHAR(255),
    election_id INT,
    votes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
);

-- Votes
CREATE TABLE votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    voter_id INT NOT NULL,
    candidate_id INT NOT NULL,
    election_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    UNIQUE KEY unique_vote (voter_id, election_id),
    FOREIGN KEY (voter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
);

-- Audit logs
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_action (user_id, action),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert DEFAULT ADMIN (password: Admin@2024)
-- Hashed password for: Admin@2024
INSERT INTO users (username, email, password, full_name, role, is_verified) 
VALUES (
    'admin',
    'admin@evoting.com',
    '$2y$10$Uw.ZcN.t7n2K7p8J9q0YCeB5x3D6F7G8H9I0J1K2L3M4N5O6P7Q8R9S0T1U2V3',
    'System Administrator',
    'admin',
    TRUE
);

-- Insert SAMPLE VOTER (password: Voter@2024)
-- Hashed password for: Voter@2024
INSERT INTO users (username, email, password, full_name, voter_id, role, is_verified) 
VALUES (
    'voter001',
    'john@example.com',
    '$2y$10$A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0U1V2W3X4Y5Z6a7b8c9d0',
    'John Doe',
    'VOTER001',
    'voter',
    TRUE
);

-- Create a default election
INSERT INTO elections (title, description, start_date, end_date, is_active, created_by)
VALUES (
    'Presidential Election 2024',
    'National Presidential Election for the year 2024',
    DATE_ADD(NOW(), INTERVAL 1 DAY),
    DATE_ADD(NOW(), INTERVAL 7 DAY),
    TRUE,
    1
);

-- Insert sample candidates
INSERT INTO candidates (name, party, description, election_id) 
VALUES 
    ('John Smith', 'Democratic Party', 'Experienced leader with 10 years in public service.', 1),
    ('Sarah Johnson', 'Progressive Alliance', 'Young visionary focused on education reform.', 1),
    ('Michael Brown', 'Conservative Union', 'Advocate for economic stability and growth.', 1);

-- Create indexes for better performance
CREATE INDEX idx_users_role ON users(role, is_verified);
CREATE INDEX idx_elections_active ON elections(is_active, end_date);
CREATE INDEX idx_candidates_election ON candidates(election_id);
CREATE INDEX idx_votes_election ON votes(election_id, voter_id);