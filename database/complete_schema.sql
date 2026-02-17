-- Vigilant Backend - Complete MySQL Schema
-- Run this file to create all required tables for the Vigilant backend
-- Database: vigilant_db (create it first: CREATE DATABASE vigilant_db;)

USE vigilant_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. Core Identity & Devices
-- ============================================

-- 1.1 Users Table
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(190) NOT NULL UNIQUE,
  phone           VARCHAR(32) NULL,
  password_hash   VARCHAR(255) NOT NULL,
  full_name       VARCHAR(190) NOT NULL,
  is_premium      TINYINT(1) NOT NULL DEFAULT 0,
  is_verified     TINYINT(1) NOT NULL DEFAULT 0,
  otp_code        VARCHAR(6) NULL,
  otp_expires_at  TIMESTAMP NULL,
  otp_created_at  TIMESTAMP NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.2 User Sessions Table
DROP TABLE IF EXISTS user_sessions;
CREATE TABLE user_sessions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  device_id       BIGINT UNSIGNED NULL,
  access_token    VARCHAR(255) NOT NULL UNIQUE,
  ip_address      VARCHAR(64) NULL,
  user_agent      VARCHAR(255) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      TIMESTAMP NULL,
  revoked_at      TIMESTAMP NULL,
  CONSTRAINT fk_sessions_user
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE CASCADE,
  INDEX idx_sessions_token (access_token),
  INDEX idx_sessions_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.3 Devices Table
DROP TABLE IF EXISTS devices;
CREATE TABLE devices (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  device_uuid     VARCHAR(100) NOT NULL,
  device_model    VARCHAR(100) NOT NULL,
  os_version      VARCHAR(50) NOT NULL,
  locale          VARCHAR(16) NULL,
  last_ip         VARCHAR(64) NULL,
  last_active_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_devices_user_uuid UNIQUE (user_id, device_uuid),
  CONSTRAINT fk_devices_user
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE CASCADE,
  INDEX idx_devices_user (user_id),
  INDEX idx_devices_uuid (device_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. Apps, Scans & Risk
-- ============================================

-- 2.1 Installed Apps Table
DROP TABLE IF EXISTS installed_apps;
CREATE TABLE installed_apps (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id       BIGINT UNSIGNED NOT NULL,
  package_name    VARCHAR(255) NOT NULL,
  app_name        VARCHAR(255) NOT NULL,
  version_name    VARCHAR(64) NULL,
  version_code     INT NULL,
  first_seen_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_system_app   TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT uq_installed_apps_device_pkg UNIQUE (device_id, package_name),
  CONSTRAINT fk_installed_apps_device
    FOREIGN KEY (device_id) REFERENCES devices(id)
      ON DELETE CASCADE,
  INDEX idx_installed_apps_device (device_id),
  INDEX idx_installed_apps_package (package_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.2 App Scans Table (with polling support)
DROP TABLE IF EXISTS app_scans;
CREATE TABLE app_scans (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id       BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  mode            ENUM('quick','deep') NOT NULL DEFAULT 'quick',
  status          ENUM('RUNNING','COMPLETED','FAILED') NULL,
  overall_risk_score INT NOT NULL DEFAULT 0,
  overall_risk_level ENUM('SAFE','LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'SAFE',
  started_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at    TIMESTAMP NULL,
  app_count       INT NOT NULL DEFAULT 0,
  apps_scanned    INT NOT NULL DEFAULT 0,
  high_risk_count INT NOT NULL DEFAULT 0,
  medium_risk_count INT NOT NULL DEFAULT 0,
  safe_count      INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_app_scans_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_scans_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  INDEX idx_app_scans_device (device_id),
  INDEX idx_app_scans_user (user_id),
  INDEX idx_app_scans_status (status, device_id),
  INDEX idx_app_scans_completed (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.3 App Scan Results Table
DROP TABLE IF EXISTS app_scan_results;
CREATE TABLE app_scan_results (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scan_id         BIGINT UNSIGNED NOT NULL,
  installed_app_id BIGINT UNSIGNED NOT NULL,
  risk_score      INT NOT NULL,
  risk_level      ENUM('SAFE','LOW','MEDIUM','HIGH','CRITICAL') NOT NULL,
  top_factor_desc VARCHAR(255) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_scan_results_scan
    FOREIGN KEY (scan_id) REFERENCES app_scans(id)
      ON DELETE CASCADE,
  CONSTRAINT fk_app_scan_results_installed_app
    FOREIGN KEY (installed_app_id) REFERENCES installed_apps(id)
      ON DELETE CASCADE,
  INDEX idx_scan_results_scan (scan_id),
  INDEX idx_scan_results_app (installed_app_id),
  INDEX idx_scan_results_risk (risk_level, risk_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.4 Risk Factors Table
DROP TABLE IF EXISTS risk_factors;
CREATE TABLE risk_factors (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  app_scan_result_id BIGINT UNSIGNED NOT NULL,
  description     VARCHAR(255) NOT NULL,
  score           INT NOT NULL DEFAULT 0,
  factor_type     ENUM('PERMISSION','BEHAVIOR','RUNTIME','MODIFIER') NOT NULL DEFAULT 'BEHAVIOR',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_risk_factors_result
    FOREIGN KEY (app_scan_result_id) REFERENCES app_scan_results(id)
      ON DELETE CASCADE,
  INDEX idx_risk_factors_result (app_scan_result_id),
  INDEX idx_risk_factors_type (factor_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.5 Device Risk Scores Table
DROP TABLE IF EXISTS device_risk_scores;
CREATE TABLE device_risk_scores (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id       BIGINT UNSIGNED NOT NULL,
  last_score      INT NOT NULL DEFAULT 0,
  last_level      ENUM('SAFE','LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'SAFE',
  last_scan_id    BIGINT UNSIGNED NULL,
  last_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_device_risk UNIQUE (device_id),
  CONSTRAINT fk_device_risk_device FOREIGN KEY (device_id)    REFERENCES devices(id)    ON DELETE CASCADE,
  CONSTRAINT fk_device_risk_scan   FOREIGN KEY (last_scan_id) REFERENCES app_scans(id) ON DELETE SET NULL,
  INDEX idx_device_risk_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. Permission Usage & Timeline
-- ============================================

-- 3.1 Permission Events Table
DROP TABLE IF EXISTS permission_events;
CREATE TABLE permission_events (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id       BIGINT UNSIGNED NOT NULL,
  installed_app_id BIGINT UNSIGNED NOT NULL,
  permission      VARCHAR(190) NOT NULL,
  event_type      ENUM('GRANTED','REVOKED','USED') NOT NULL,
  context         ENUM('FOREGROUND','BACKGROUND','SCREEN_OFF') NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  extra_meta      JSON NULL,
  CONSTRAINT fk_permission_events_device
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  CONSTRAINT fk_permission_events_app
    FOREIGN KEY (installed_app_id) REFERENCES installed_apps(id) ON DELETE CASCADE,
  INDEX idx_permission_events_device_time (device_id, created_at),
  INDEX idx_permission_events_app_time (installed_app_id, created_at),
  INDEX idx_permission_events_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. Security Alerts
-- ============================================

-- 4.1 Security Alerts Table
DROP TABLE IF EXISTS security_alerts;
CREATE TABLE security_alerts (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id       VARCHAR(100) NOT NULL UNIQUE,
  user_id         BIGINT UNSIGNED NOT NULL,
  device_id       BIGINT UNSIGNED NOT NULL,
  installed_app_id BIGINT UNSIGNED NULL,
  title           VARCHAR(255) NOT NULL,
  short_desc      VARCHAR(255) NOT NULL,
  detailed_info   TEXT NOT NULL,
  severity        ENUM('HIGH','MEDIUM','LOW') NOT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at TIMESTAMP NULL,
  CONSTRAINT fk_security_alerts_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_security_alerts_device
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  CONSTRAINT fk_security_alerts_app
    FOREIGN KEY (installed_app_id) REFERENCES installed_apps(id) ON DELETE SET NULL,
  INDEX idx_security_alerts_user (user_id),
  INDEX idx_security_alerts_device (device_id),
  INDEX idx_security_alerts_public_id (public_id),
  INDEX idx_security_alerts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.2 Security Alert Recommendations Table
DROP TABLE IF EXISTS security_alert_recommendations;
CREATE TABLE security_alert_recommendations (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_id        BIGINT UNSIGNED NOT NULL,
  recommendation  VARCHAR(255) NOT NULL,
  CONSTRAINT fk_alert_recs_alert
    FOREIGN KEY (alert_id) REFERENCES security_alerts(id)
      ON DELETE CASCADE,
  INDEX idx_alert_recs_alert (alert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. Community Threats & Reports
-- ============================================

-- 5.1 Community Threats Table
DROP TABLE IF EXISTS community_threats;
CREATE TABLE community_threats (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  app_name        VARCHAR(255) NOT NULL,
  package_name    VARCHAR(255) NOT NULL,
  category        ENUM('SPYWARE','STALKERWARE','PERMISSION_ABUSE','ADWARE','DATA_THEFT','OTHER') NOT NULL,
  risk_level      ENUM('CRITICAL','HIGH','MEDIUM','LOW') NOT NULL,
  report_count    INT NOT NULL DEFAULT 0,
  first_seen_at   DATE NOT NULL,
  last_reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  description     TEXT NOT NULL,
  behaviors       JSON NULL,
  INDEX idx_threats_pkg (package_name),
  INDEX idx_threats_category (category),
  INDEX idx_threats_risk (risk_level),
  INDEX idx_threats_reported (last_reported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5.2 Community Reports Table
DROP TABLE IF EXISTS community_reports;
CREATE TABLE community_reports (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  community_threat_id BIGINT UNSIGNED NULL,
  user_id         BIGINT UNSIGNED NULL,
  device_id       BIGINT UNSIGNED NULL,
  app_name        VARCHAR(255) NOT NULL,
  package_name    VARCHAR(255) NOT NULL,
  category        ENUM('SPYWARE','STALKERWARE','PERMISSION_ABUSE','DATA_THEFT','OTHER') NOT NULL,
  description     TEXT NOT NULL,
  additional_details TEXT NULL,
  consent_anonymous   TINYINT(1) NOT NULL DEFAULT 1,
  consent_data_usage  TINYINT(1) NOT NULL DEFAULT 1,
  status          ENUM('UNDER_REVIEW','APPROVED','REJECTED') NOT NULL DEFAULT 'UNDER_REVIEW',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_threat
    FOREIGN KEY (community_threat_id) REFERENCES community_threats(id)
      ON DELETE SET NULL,
  CONSTRAINT fk_reports_user
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE SET NULL,
  CONSTRAINT fk_reports_device
    FOREIGN KEY (device_id) REFERENCES devices(id)
      ON DELETE SET NULL,
  INDEX idx_reports_user (user_id),
  INDEX idx_reports_pkg (package_name),
  INDEX idx_reports_status (status),
  INDEX idx_reports_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. Settings & Preferences
-- ============================================

-- 6.1 Alert Rules Table
DROP TABLE IF EXISTS alert_rules;
CREATE TABLE alert_rules (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  device_id       BIGINT UNSIGNED NULL,
  notify_critical TINYINT(1) NOT NULL DEFAULT 1,
  notify_suspicious TINYINT(1) NOT NULL DEFAULT 1,
  notify_permissions TINYINT(1) NOT NULL DEFAULT 1,
  notify_community  TINYINT(1) NOT NULL DEFAULT 1,
  quiet_hours_enabled TINYINT(1) NOT NULL DEFAULT 0,
  quiet_start_time    TIME NULL,
  quiet_end_time      TIME NULL,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_alert_rules_user_device UNIQUE (user_id, device_id),
  CONSTRAINT fk_alert_rules_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_alert_rules_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  INDEX idx_alert_rules_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6.2 Scan Settings Table
DROP TABLE IF EXISTS scan_settings;
CREATE TABLE scan_settings (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  device_id       BIGINT UNSIGNED NULL,
  auto_scan       TINYINT(1) NOT NULL DEFAULT 0,
  scan_frequency  ENUM('DAILY','WEEKLY','MONTHLY') NOT NULL DEFAULT 'DAILY',
  detection_level ENUM('LOW','MEDIUM','HIGH') NOT NULL DEFAULT 'MEDIUM',
  deep_scan       TINYINT(1) NOT NULL DEFAULT 0,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_scan_settings_user_device UNIQUE (user_id, device_id),
  CONSTRAINT fk_scan_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_scan_settings_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  INDEX idx_scan_settings_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6.3 Privacy Settings Table
DROP TABLE IF EXISTS privacy_settings;
CREATE TABLE privacy_settings (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  share_usage_stats  TINYINT(1) NOT NULL DEFAULT 0,
  share_crash_reports TINYINT(1) NOT NULL DEFAULT 0,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_privacy_user UNIQUE (user_id),
  CONSTRAINT fk_privacy_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_privacy_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. Weekly Reports & Analytics
-- ============================================

-- 7.1 Weekly Reports Table
DROP TABLE IF EXISTS weekly_reports;
CREATE TABLE weekly_reports (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  device_id       BIGINT UNSIGNED NULL,
  week_start      DATE NOT NULL,
  week_end        DATE NOT NULL,
  threats_count   INT NOT NULL DEFAULT 0,
  violations_count INT NOT NULL DEFAULT 0,
  alerts_count    INT NOT NULL DEFAULT 0,
  top_offenders   JSON NULL,
  risk_trend      JSON NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_weekly_report_user_device_week UNIQUE (user_id, device_id, week_start),
  CONSTRAINT fk_weekly_reports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_weekly_reports_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  INDEX idx_weekly_reports_user (user_id),
  INDEX idx_weekly_reports_week (week_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Initial Data (Optional)
-- ============================================

-- Insert some sample community threats (optional)
-- INSERT INTO community_threats (app_name, package_name, category, risk_level, report_count, first_seen_at, description) VALUES
-- ('Suspicious Tracker', 'com.tracker.suspicious', 'SPYWARE', 'HIGH', 5, CURDATE(), 'Reports indicate this app tracks user location without consent');

-- ============================================
-- Verification Queries
-- ============================================

-- Show all tables
-- SHOW TABLES;

-- Count tables
-- SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'vigilant_db';
