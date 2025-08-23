<?php
/**
 * CERTOLO - Header Template with Logo
 * Included at the top of all pages
 */

// Ensure user data is available if logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] ?? 'User' : '';
$userRole = $isLoggedIn ? $_SESSION['user_role'] ?? '' : '';
$userEmail = $isLoggedIn ? $_SESSION['user_email'] ?? '' : '';

// Get current page for active menu highlighting
$currentPage = $module ?? 'home';

// Generate CSRF token for forms
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title><?php echo isset($pageTitle) ? Security::escape($pageTitle) . ' - ' : ''; ?>CERTOLO - Certification Management System</title>
    
    <!-- CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons@latest/iconfont/tabler-icons.min.css" rel="stylesheet"/>
    
    <!-- Custom CSS with CERTOLO branding colors -->
    <style>
        :root {
            --tblr-primary: <?php echo COLOR_PRIMARY_MEDIUM; ?>;
            --tblr-primary-rgb: 46, 125, 50;
            --certolo-dark: <?php echo COLOR_PRIMARY_DARK; ?>;
            --certolo-medium: <?php echo COLOR_PRIMARY_MEDIUM; ?>;
            --certolo-light: <?php echo COLOR_PRIMARY_LIGHT; ?>;
            --certolo-accent: <?php echo COLOR_ACCENT; ?>;
            --certolo-bg: <?php echo COLOR_BACKGROUND; ?>;
            --certolo-bg-off: <?php echo COLOR_BACKGROUND_OFF; ?>;
            --certolo-text: <?php echo COLOR_TEXT; ?>;
        }
        
        .navbar-brand-image {
            height: 2.5rem;
            width: auto;
            max-width: 180px;
        }
        
        .btn-primary {
            background-color: var(--certolo-medium);
            border-color: var(--certolo-medium);
        }
        
        .btn-primary:hover {
            background-color: var(--certolo-dark);
            border-color: var(--certolo-dark);
        }
        
        .btn-accent {
            background-color: var(--certolo-accent);
            border-color: var(--certolo-accent);
            color: white;
        }
        
        .btn-accent:hover {
            background-color: #E68900;
            border-color: #E68900;
            color: white;
        }
        
        .text-primary {
            color: var(--certolo-medium) !important;
        }
        
        .bg-primary {
            background-color: var(--certolo-medium) !important;
        }
        
        .navbar-light {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,.1);
        }
        
        .certolo-logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--certolo-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .certolo-logo-icon {
            color: var(--certolo-medium);
        }
        
        .page-wrapper {
            background-color: var(--certolo-bg-off);
            min-height: calc(100vh - 60px);
        }
        
        /* Logo with text fallback */
        .navbar-brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .navbar-brand-image {
                height: 2rem;
            }
        }
    </style>
    
    <!-- Custom CSS file -->
    <link href="/assets/css/custom.css?v=<?php echo time(); ?>" rel="stylesheet"/>
    
    <!-- Favicon -->
    <link rel="icon" href="/assets/images/favicon.ico" type="image/x-icon"/>
</head>
<body>
    <div class="page">
        <!-- Navbar -->
        <header class="navbar navbar-expand-md navbar-light d-print-none">
            <div class="container-xl">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
                    <a href="/" class="navbar-brand-logo">
                        <?php 
                        // Try to use logo image, fallback to icon+text if not found
                        $logoPath = '/assets/images/certolo-logo.png';
                        $logoPath2 = '/assets/images/logo.png';
                        $logoPath3 = '/assets/images/certolo logo.png'; // In case filename has space
                        
                        // Check which logo exists (you'll need to update this with actual filename)
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath)): ?>
                            <img src="<?php echo $logoPath; ?>" alt="CERTOLO" class="navbar-brand-image">
                        <?php elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath2)): ?>
                            <img src="<?php echo $logoPath2; ?>" alt="CERTOLO" class="navbar-brand-image">
                        <?php elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath3)): ?>
                            <img src="<?php echo $logoPath3; ?>" alt="CERTOLO" class="navbar-brand-image">
                        <?php else: ?>
                            <!-- Fallback to icon and text if no logo found -->
                            <span class="certolo-logo-text text-decoration-none">
                                <i class="ti ti-certificate-2 certolo-logo-icon" style="font-size: 2rem;"></i>
                                CERTOLO
                            </span>
                        <?php endif; ?>
                    </a>
                </h1>
                <div class="navbar-nav flex-row order-md-last">
                    <?php if ($isLoggedIn): ?>
                    <!-- Notifications -->
                    <div class="nav-item dropdown d-none d-md-flex me-3">
                        <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1" aria-label="Show notifications">
                            <i class="ti ti-bell" style="font-size: 1.2rem;"></i>
                            <span class="badge bg-red" id="notification-count" style="display: none;"></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Notifications</h3>
                                </div>
                                <div class="list-group list-group-flush list-group-hoverable" id="notification-list">
                                    <div class="list-group-item">
                                        <div class="text-muted">No new notifications</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User menu -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open user menu">
                            <span class="avatar avatar-sm" style="background-color: var(--certolo-medium);">
                                <?php echo strtoupper(substr($userName, 0, 2)); ?>
                            </span>
                            <div class="d-none d-xl-block ps-2">
                                <div><?php echo Security::escape($userName); ?></div>
                                <div class="mt-1 small text-muted"><?php echo ucfirst($userRole); ?></div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a href="/profile" class="dropdown-item">
                                <i class="ti ti-user dropdown-item-icon"></i>
                                Profile
                            </a>
                            <a href="/profile/settings" class="dropdown-item">
                                <i class="ti ti-settings dropdown-item-icon"></i>
                                Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="/logout" class="dropdown-item">
                                <i class="ti ti-logout dropdown-item-icon"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="nav-item">
                        <a href="/login" class="btn btn-primary">
                            <i class="ti ti-login"></i> Login
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="collapse navbar-collapse" id="navbar-menu">
                    <div class="d-flex flex-column flex-md-row flex-fill align-items-stretch align-items-md-center">
                        <ul class="navbar-nav">
                            <?php if ($isLoggedIn): ?>
                                <!-- Dashboard -->
                                <li class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                                    <a class="nav-link" href="/dashboard">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-home"></i>
                                        </span>
                                        <span class="nav-link-title">Dashboard</span>
                                    </a>
                                </li>
                                
                                <!-- Standards -->
                                <li class="nav-item <?php echo $currentPage === 'standards' ? 'active' : ''; ?>">
                                    <a class="nav-link" href="/standards">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-certificate"></i>
                                        </span>
                                        <span class="nav-link-title">Standards</span>
                                    </a>
                                </li>
                                
                                <!-- Applications -->
                                <li class="nav-item <?php echo $currentPage === 'applications' ? 'active' : ''; ?>">
                                    <a class="nav-link" href="/applications">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-file-text"></i>
                                        </span>
                                        <span class="nav-link-title">Applications</span>
                                    </a>
                                </li>
                                
                                <!-- Certificates -->
                                <li class="nav-item <?php echo $currentPage === 'certificates' ? 'active' : ''; ?>">
                                    <a class="nav-link" href="/certificates">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-award"></i>
                                        </span>
                                        <span class="nav-link-title">Certificates</span>
                                    </a>
                                </li>
                                
                                <?php if ($userRole === ROLE_CERTIFIER): ?>
                                <!-- Certifier-only menu items -->
                                <li class="nav-item <?php echo $currentPage === 'customers' ? 'active' : ''; ?>">
                                    <a class="nav-link" href="/customers">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-users"></i>
                                        </span>
                                        <span class="nav-link-title">Customers</span>
                                    </a>
                                </li>
                                
                                <li class="nav-item <?php echo $currentPage === 'reviews' ? 'active' : ''; ?>">
                                    <a class="nav-link" href="/applications?status=under_review">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-clipboard-check"></i>
                                        </span>
                                        <span class="nav-link-title">Reviews</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Public menu -->
                                <li class="nav-item <?php echo $currentPage === 'home' ? 'active' : ''; ?>">
                                    <a class="nav-link" href="/">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-home"></i>
                                        </span>
                                        <span class="nav-link-title">Home</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="/#features">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-star"></i>
                                        </span>
                                        <span class="nav-link-title">Features</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="/#how-it-works">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-help"></i>
                                        </span>
                                        <span class="nav-link-title">How It Works</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="/#contact">
                                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                                            <i class="ti ti-mail"></i>
                                        </span>
                                        <span class="nav-link-title">Contact</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Page wrapper -->
        <div class="page-wrapper">