-- =====================================================
-- Admin Role Migration Script
-- Version: 1.0
-- Date: December 29, 2025
-- =====================================================

-- Create admin table (separate from teacher/student)
CREATE TABLE IF NOT EXISTS admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    uname VARCHAR(100) NOT NULL UNIQUE,
    pword VARCHAR(255) NOT NULL,
    fname VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create admin audit log for tracking admin actions
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_table VARCHAR(50),
    target_id INT,
    action_details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin(id)
);

-- Create indexes
CREATE INDEX idx_admin_uname ON admin(uname);
CREATE INDEX idx_admin_audit_admin ON admin_audit_log(admin_id);
CREATE INDEX idx_admin_audit_action ON admin_audit_log(action_type);
CREATE INDEX idx_admin_audit_date ON admin_audit_log(created_at);

-- Insert a default admin user (password: admin123)
-- MD5 hash of 'admin123' = 0192023a7bbd73250516f069df18b500
INSERT INTO admin (uname, pword, fname, email, status) 
VALUES ('admin', '0192023a7bbd73250516f069df18b500', 'System Admin', 'admin@examflow.com', 'active')
ON DUPLICATE KEY UPDATE uname = uname;

-- =====================================================
-- Migration Complete
-- =====================================================
