<?php
/**
 * CERTOLO - System Constants
 * Certification Management System
 */

// Environment settings
define('ENVIRONMENT', 'production'); // Change to 'production' when ready

// Site information
define('SITE_NAME', 'CERTOLO');
define('SITE_URL', 'https://certit.ee');
define('SITE_EMAIL', 'info@certit.ee');
define('SITE_DOMAIN', 'certit.ee');

// Color scheme constants
define('COLOR_PRIMARY_DARK', '#1B5E20');
define('COLOR_PRIMARY_MEDIUM', '#2E7D32');
define('COLOR_PRIMARY_LIGHT', '#66BB6A');
define('COLOR_BACKGROUND', '#FFFFFF');
define('COLOR_BACKGROUND_OFF', '#F7F9F8');
define('COLOR_ACCENT', '#FF9800');
define('COLOR_TEXT', '#212121');

// Directory paths - Fixed for Zone.ee
define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('APP_PATH', ROOT_PATH . 'app/');
define('MODULES_PATH', ROOT_PATH . 'modules/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('TEMPLATES_PATH', ROOT_PATH . 'templates/');

// URL paths
define('ASSETS_URL', '/assets/');
define('UPLOADS_URL', '/uploads/');
define('MODULES_URL', '/modules/');

// Upload directories
define('UPLOAD_STANDARDS', UPLOADS_PATH . 'standards/');
define('UPLOAD_CRITERIA', UPLOADS_PATH . 'criteria/');
define('UPLOAD_APPLICATIONS', UPLOADS_PATH . 'applications/');
define('UPLOAD_CERTIFICATES', UPLOADS_PATH . 'certificates/');

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// Session settings
define('SESSION_NAME', 'certolo_session');
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_PATH', '/');
define('SESSION_SECURE', true); // HTTPS only
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// Security settings
define('BCRYPT_COST', 12);
define('MIN_PASSWORD_LENGTH', 8);
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Certificate settings
define('CERTIFICATE_PREFIX', 'CERT');
define('VERIFICATION_CODE_LENGTH', 10);

// Email settings
define('EMAIL_FROM_NAME', 'CERTOLO');
define('EMAIL_FROM_ADDRESS', 'noreply@certit.ee');
define('EMAIL_REPLY_TO', 'support@certit.ee');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Date format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd.m.Y');
define('DISPLAY_DATETIME_FORMAT', 'd.m.Y H:i');

// Status constants
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_SUSPENDED', 'suspended');

// User roles
define('ROLE_APPLICANT', 'applicant');
define('ROLE_CERTIFIER', 'certifier');

// Application statuses
define('APP_DRAFT', 'draft');
define('APP_SUBMITTED', 'submitted');
define('APP_UNDER_REVIEW', 'under_review');
define('APP_APPROVED', 'approved');
define('APP_REJECTED', 'rejected');
define('APP_ISSUED', 'issued');

// Certificate statuses
define('CERT_ACTIVE', 'active');
define('CERT_EXPIRED', 'expired');
define('CERT_REVOKED', 'revoked');

// Criteria aspects
define('ASPECTS', [
    'ENV' => 'Environmental',
    'FIR' => 'Fire Safety',
    'CON' => 'Construction',
    'SEC' => 'Security',
    'CAB' => 'Cabling',
    'POW' => 'Power',
    'ACV' => 'HVAC',
    'ORG' => 'Organization',
    'DOC' => 'Documentation',
    'EFF' => 'Efficiency'
]);

// Zone.ee hosting limitations
define('ZONE_EE_STARTER', true);
define('CRON_JOBS_LIMIT', 5);
define('EMAIL_HOURLY_LIMIT', 100);

// Development/Debug settings
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Europe/Tallinn');