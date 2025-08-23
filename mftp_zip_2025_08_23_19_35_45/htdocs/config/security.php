<?php
/**
 * CERTOLO - Security Configuration
 * Certification Management System
 */

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => SESSION_PATH,
        'domain' => SITE_DOMAIN,
        'secure' => SESSION_SECURE,
        'httponly' => SESSION_HTTPONLY,
        'samesite' => SESSION_SAMESITE
    ]);
}

/**
 * Security helper functions
 */
class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            $errors[] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return $errors;
    }
    
    /**
     * Sanitize output
     */
    public static function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generate random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Clean input
     */
    public static function cleanInput($input) {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input);
        return $input;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['error']) || is_array($file['error'])) {
            $errors[] = 'Invalid file upload';
            return $errors;
        }
        
        // Check upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded';
                return $errors;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File size exceeds limit';
                return $errors;
            default:
                $errors[] = 'Unknown upload error';
                return $errors;
        }
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB limit';
        }
        
        // Check file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        $allowedMimes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ALLOWED_FILE_TYPES)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_FILE_TYPES);
        }
        
        if (isset($allowedMimes[$ext]) && $mimeType !== $allowedMimes[$ext]) {
            $errors[] = 'File content does not match file extension';
        }
        
        return $errors;
    }
    
    /**
     * Generate unique filename
     */
    public static function generateUniqueFilename($originalName) {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        return $basename . '_' . uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    }
    
    /**
     * Check login attempts
     */
    public static function checkLoginAttempts($email, $ip) {
        $db = Database::getInstance();
        
        // Clean old attempts (older than lockout time)
        $cleanupTime = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_TIME);
        $db->query(
            "DELETE FROM login_attempts WHERE attempted_at < :cleanup_time",
            ['cleanup_time' => $cleanupTime]
        );
        
        // Count recent attempts
        $stmt = $db->query(
            "SELECT COUNT(*) as attempts FROM login_attempts 
             WHERE (email = :email OR ip_address = :ip) 
             AND attempted_at > :lockout_time",
            [
                'email' => $email,
                'ip' => $ip,
                'lockout_time' => $cleanupTime
            ]
        );
        
        $result = $stmt->fetch();
        return $result['attempts'] < LOGIN_ATTEMPTS_LIMIT;
    }
    
    /**
     * Record login attempt
     */
    public static function recordLoginAttempt($email, $ip) {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)",
            ['email' => $email, 'ip' => $ip]
        );
    }
    
    /**
     * Clear login attempts
     */
    public static function clearLoginAttempts($email, $ip) {
        $db = Database::getInstance();
        $db->query(
            "DELETE FROM login_attempts WHERE email = :email OR ip_address = :ip",
            ['email' => $email, 'ip' => $ip]
        );
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}