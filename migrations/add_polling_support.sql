-- Migration: Add polling support columns to app_scans table
-- This migration is OPTIONAL - the backend works without these columns
-- but adding them enables better real-time progress tracking

-- Add status column (RUNNING, COMPLETED, FAILED)
ALTER TABLE app_scans 
ADD COLUMN status ENUM('RUNNING', 'COMPLETED', 'FAILED') NULL AFTER overall_risk_level;

-- Add apps_scanned column for progress tracking
ALTER TABLE app_scans 
ADD COLUMN apps_scanned INT NOT NULL DEFAULT 0 AFTER app_count;

-- Update existing completed scans to have COMPLETED status
UPDATE app_scans 
SET status = 'COMPLETED', apps_scanned = app_count 
WHERE completed_at IS NOT NULL;

-- Update existing running scans to have RUNNING status
UPDATE app_scans 
SET status = 'RUNNING' 
WHERE completed_at IS NULL;

-- Add index for faster status queries
CREATE INDEX idx_app_scans_status ON app_scans(status, device_id);
