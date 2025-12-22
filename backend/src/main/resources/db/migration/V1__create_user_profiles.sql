-- Initial user profiles table
CREATE TABLE IF NOT EXISTS user_profiles (
    user_id VARCHAR(255) PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255),
    aspri_name VARCHAR(100) DEFAULT 'ASPRI',
    aspri_persona TEXT,
    call_preference VARCHAR(100),
    preferred_language VARCHAR(5) DEFAULT 'id',
    theme_preference VARCHAR(10) DEFAULT 'light',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP
);

-- Index for email lookup
CREATE INDEX idx_user_profiles_email ON user_profiles(email);

-- Index for language preference
CREATE INDEX idx_user_profiles_language ON user_profiles(preferred_language);
