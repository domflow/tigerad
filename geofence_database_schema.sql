-- Geofence Advertising App Database Schema
-- MySQL/MariaDB with full geospatial support

CREATE DATABASE IF NOT EXISTS geofence_ads;
USE geofence_ads;

-- Store owners/business accounts
CREATE TABLE store_owners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_documents JSON,
    government_id_url VARCHAR(500),
    business_license_url VARCHAR(500),
    address_proof_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_verification_status (verification_status)
);

-- Store locations with geospatial data
CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    store_name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    geofence_radius_meters INT DEFAULT 1609, -- 1 mile = 1609 meters
    trigger_radius_meters INT DEFAULT 3, -- 3 meters from store entrance
    -- Geospatial columns for efficient queries
    location POINT NOT NULL SRID 4326, -- WGS 84 coordinate system
    geofence_circle POLYGON NOT NULL SRID 4326, -- Circular geofence
    is_active BOOLEAN DEFAULT TRUE,
    business_hours JSON,
    phone VARCHAR(20),
    website VARCHAR(500),
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES store_owners(id) ON DELETE CASCADE,
    INDEX idx_owner_id (owner_id),
    INDEX idx_location (location),
    INDEX idx_active (is_active),
    SPATIAL INDEX idx_geofence (geofence_circle)
);

-- Credit packages available for purchase
CREATE TABLE credit_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    credits INT NOT NULL,
    views_per_credit INT NOT NULL,
    total_views INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_sort_order (sort_order)
);

-- Credit transactions for store owners
CREATE TABLE credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    transaction_type ENUM('purchase', 'refund', 'usage', 'expiry') NOT NULL,
    credits INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('google_wallet', 'stripe', 'credits') DEFAULT 'stripe',
    payment_reference VARCHAR(255), -- Transaction ID from payment processor
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    refund_reason TEXT,
    gateway_response JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store_id (store_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
);

-- Store owner credit balance
CREATE TABLE credit_balances (
    store_id INT PRIMARY KEY,
    total_credits INT DEFAULT 0,
    used_credits INT DEFAULT 0,
    available_credits INT DEFAULT 0,
    pending_refund_credits INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

-- Advertisements/ads created by store owners
CREATE TABLE advertisements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    images JSON NOT NULL, -- Array of image URLs and metadata
    call_to_action VARCHAR(100),
    link_url VARCHAR(500),
    credits_purchased INT NOT NULL,
    views_allocated INT NOT NULL,
    views_used INT DEFAULT 0,
    views_remaining INT GENERATED ALWAYS AS (views_allocated - views_used) STORED,
    status ENUM('draft', 'active', 'paused', 'completed', 'expired') DEFAULT 'draft',
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    last_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store_id (store_id),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_created_at (created_at)
);

-- User interactions with advertisements (anonymous)
CREATE TABLE user_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advertisement_id INT NOT NULL,
    user_fingerprint VARCHAR(255) NOT NULL, -- Anonymous user identifier
    interaction_type ENUM('view', 'click', 'dismiss', 'enter_geofence', 'exit_geofence') NOT NULL,
    user_latitude DECIMAL(10, 8) NOT NULL,
    user_longitude DECIMAL(11, 8) NOT NULL,
    user_location POINT NOT NULL SRID 4326,
    store_distance_meters DECIMAL(10, 2) NOT NULL,
    device_info JSON,
    session_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (advertisement_id) REFERENCES advertisements(id) ON DELETE CASCADE,
    INDEX idx_advertisement_id (advertisement_id),
    INDEX idx_user_fingerprint (user_fingerprint),
    INDEX idx_interaction_type (interaction_type),
    INDEX idx_created_at (created_at),
    INDEX idx_session_id (session_id),
    SPATIAL INDEX idx_user_location (user_location)
);

-- Geofence triggers and events
CREATE TABLE geofence_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    user_fingerprint VARCHAR(255) NOT NULL,
    event_type ENUM('enter', 'exit', 'dwell') NOT NULL,
    user_latitude DECIMAL(10, 8) NOT NULL,
    user_longitude DECIMAL(11, 8) NOT NULL,
    user_location POINT NOT NULL SRID 4326,
    distance_to_store_meters DECIMAL(10, 2) NOT NULL,
    trigger_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store_user (store_id, user_fingerprint),
    INDEX idx_event_type (event_type),
    INDEX idx_trigger_time (trigger_time),
    SPATIAL INDEX idx_user_location (user_location)
);

-- Abuse prevention and rate limiting
CREATE TABLE rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL, -- user_fingerprint or IP address
    limit_type ENUM('geofence_entry', 'ad_creation', 'image_upload') NOT NULL,
    store_id INT NULL,
    request_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    window_duration_minutes INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_limit (identifier, limit_type, store_id, window_start),
    INDEX idx_identifier (identifier),
    INDEX idx_limit_type (limit_type),
    INDEX idx_store_id (store_id)
);

-- Payment processing logs
CREATE TABLE payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(255) NOT NULL,
    store_id INT NOT NULL,
    payment_method ENUM('google_wallet', 'stripe') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('initiated', 'processing', 'completed', 'failed', 'refunded') NOT NULL,
    gateway_response JSON,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_store_id (store_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Image storage metadata
CREATE TABLE images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advertisement_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    file_size_bytes INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    is_optimized BOOLEAN DEFAULT FALSE,
    optimized_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (advertisement_id) REFERENCES advertisements(id) ON DELETE CASCADE,
    INDEX idx_advertisement_id (advertisement_id),
    INDEX idx_created_at (created_at)
);

-- API keys for mobile app
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_owner_id INT,
    api_key VARCHAR(255) UNIQUE NOT NULL,
    key_name VARCHAR(100),
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_owner_id) REFERENCES store_owners(id) ON DELETE CASCADE,
    INDEX idx_api_key (api_key),
    INDEX idx_active (is_active),
    INDEX idx_expires_at (expires_at)
);

-- System settings and configuration
CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') NOT NULL,
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default credit packages
INSERT INTO credit_packages (name, price, credits, views_per_credit, total_views, sort_order) VALUES
('Starter Pack', 1.00, 1, 180, 180, 1),
('Basic Pack', 5.00, 5, 180, 900, 2),
('Premium Pack', 10.00, 10, 180, 1800, 3),
('Enterprise Pack', 20.00, 20, 180, 3600, 4);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('geofence_radius_meters', '1609', 'integer', 'Default geofence radius in meters (1 mile)'),
('trigger_radius_meters', '3', 'integer', 'Trigger zone radius in meters'),
('max_images_per_ad', '3', 'integer', 'Maximum images allowed per advertisement'),
('ad_creation_interval_minutes', '15', 'integer', 'Minimum interval between ad creations'),
('geofence_entry_limit_hours', '1', 'integer', 'Hours between geofence entries per user'),
('credit_expiry_months', '12', 'integer', 'Months before credits expire'),
('max_geofences_per_device', '100', 'integer', 'Maximum geofences per device'),
('location_update_interval_seconds', '30', 'integer', 'Location update interval');

-- Create indexes for performance
CREATE INDEX idx_store_location ON stores (latitude, longitude);
CREATE INDEX idx_ad_status_dates ON advertisements (status, start_date, end_date);
CREATE INDEX idx_interaction_composite ON user_interactions (advertisement_id, user_fingerprint, created_at);
CREATE INDEX idx_geofence_composite ON geofence_events (store_id, user_fingerprint, event_type, trigger_time);