<?php
/**
 * CERTOLO - Main Entry Point
 * Certification Management System
 */

// Load configuration files
require_once 'config/constants.php';
require_once 'config/database.php';
require_once 'config/security.php';

// Start session
session_start();

// Get route from URL
$route = isset($_GET['route']) ? $_GET['route'] : '';
$route = trim($route, '/');

// Parse route into segments
$segments = $route ? explode('/', $route) : [];
$module = !empty($segments[0]) ? $segments[0] : 'home';
$action = !empty($segments[1]) ? $segments[1] : 'index';
$id = !empty($segments[2]) ? $segments[2] : null;

// Store action and id in GET for modules to access
$_GET['action'] = $action;
$_GET['id'] = $id;

// Check if it's a public page or requires authentication
$publicModules = ['home', 'login', 'register', 'forgot-password', 'reset-password', 'verify-email'];
$isPublic = in_array($module, $publicModules);

// Check authentication for protected pages
if (!$isPublic && !isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Route to appropriate module
switch ($module) {
    case 'home':
    case '':
        // Landing page
        require_once 'modules/home/index.php';
        break;
        
    case 'login':
        require_once 'modules/auth/login.php';
        break;
        
    case 'register':
        require_once 'modules/auth/register.php';
        break;
        
    case 'logout':
        require_once 'modules/auth/logout.php';
        break;
        
    case 'forgot-password':
        require_once 'modules/auth/forgot-password.php';
        break;
        
    case 'reset-password':
        require_once 'modules/auth/reset-password.php';
        break;
        
    case 'verify-email':
        require_once 'modules/auth/verify-email.php';
        break;
        
    case 'dashboard':
        require_once 'modules/dashboard/index.php';
        break;
        
    case 'standards':
        require_once 'modules/standards/index.php';
        break;
        
    case 'applications':
        require_once 'modules/applications/index.php';
        break;
        
    case 'certificates':
        require_once 'modules/certificates/index.php';
        break;
        
    case 'customers':
        // Only for certifiers
        if ($_SESSION['user_role'] !== ROLE_CERTIFIER) {
            header('HTTP/1.0 403 Forbidden');
            require_once 'error/403.php';
            exit;
        }
        require_once 'modules/customers/index.php';
        break;
        
    case 'profile':
        require_once 'modules/profile/index.php';
        break;
        
    case 'admin':
        // Check if user is admin (implement admin check)
        require_once 'modules/admin/index.php';
        break;
        
    default:
        // 404 Not Found
        header('HTTP/1.0 404 Not Found');
        require_once 'error/404.php';
        break;
}