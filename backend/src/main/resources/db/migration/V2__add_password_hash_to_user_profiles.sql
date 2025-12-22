-- Add password_hash column to user_profiles table
ALTER TABLE user_profiles
ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT '';

-- Add index on email for faster lookups
CREATE INDEX idx_user_profiles_email ON user_profiles(email);
