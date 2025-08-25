<?php
/**
 * CERTOLO - Login Module
 * Handles user authentication
 */

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard');
    exit;
}

$errors = [];
$success = '';
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        // Get and validate input
        $email = Security::cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Validate email
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!Security::validateEmail($email)) {
            $errors[] = 'Invalid email format';
        }
        
        // Validate password
        if (empty($password)) {
            $errors[] = 'Password is required';
        }
        
        // Check login attempts
        if (empty($errors)) {
            $clientIP = Security::getClientIP();
            
            if (!Security::checkLoginAttempts($email, $clientIP)) {
                $errors[] = 'Too many failed login attempts. Please try again in 15 minutes.';
            }
        }
        
        // Attempt login if no errors
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                $stmt = $db->query(
                    "SELECT id, email, password, role, company_name, contact_person, status, email_verified_at 
                     FROM users 
                     WHERE email = :email 
                     LIMIT 1",
                    ['email' => $email]
                );
                
                $user = $stmt->fetch();
                
                if ($user && Security::verifyPassword($password, $user['password'])) {
                    // Check if email is verified
                    if (is_null($user['email_verified_at'])) {
                        $errors[] = 'Please verify your email address before logging in. Check your inbox for the verification link.';
                        Security::recordLoginAttempt($email, $clientIP);
                    }
                    // Check if account is active
                    elseif ($user['status'] !== 'active') {
                        $errors[] = 'Your account has been ' . $user['status'] . '. Please contact support.';
                        Security::recordLoginAttempt($email, $clientIP);
                    }
                    else {
                        // Login successful
                        Security::clearLoginAttempts($email, $clientIP);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_name'] = $user['contact_person'];
                        $_SESSION['company_name'] = $user['company_name'];
                        
                        // Handle remember me
                        if ($remember) {
                            $token = Security::generateToken(64);
                            $db->query(
                                "UPDATE users SET remember_token = :token WHERE id = :id",
                                ['token' => $token, 'id' => $user['id']]
                            );
                            
                            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                        }
                        
                        // Log activity
                        $db->query(
                            "INSERT INTO activity_logs (user_id, action, module, ip_address, user_agent) 
                             VALUES (:user_id, 'login', 'auth', :ip, :agent)",
                            [
                                'user_id' => $user['id'],
                                'ip' => $clientIP,
                                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                            ]
                        );
                        
                        // Redirect to dashboard or intended page
                        $redirect = $_SESSION['redirect_after_login'] ?? '/dashboard';
                        unset($_SESSION['redirect_after_login']);
                        header('Location: ' . $redirect);
                        exit;
                    }
                } else {
                    $errors[] = 'Invalid email or password';
                    Security::recordLoginAttempt($email, $clientIP);
                }
                
            } catch (Exception $e) {
                error_log('Login error: ' . $e->getMessage());
                $errors[] = 'An error occurred. Please try again later.';
            }
        }
    }
}

// Check for messages
if (isset($_GET['registered'])) {
    $success = 'Registration successful! Please check your email to verify your account.';
}
if (isset($_GET['reset'])) {
    $success = 'Password reset successful! You can now login with your new password.';
}
if (isset($_GET['verified'])) {
    $success = 'Email verified successfully! You can now login.';
}

// Set page title
$pageTitle = 'Login';

// Include header
include INCLUDES_PATH . 'header.php';
?>

<div class="page-body">
    <div class="container-tight py-4">
        <div class="text-center mb-4">
            <a href="/" class="navbar-brand navbar-brand-autodark">
                <h1 class="certolo-logo-text text-decoration-none">
                    <i class="ti ti-certificate-2 certolo-logo-icon" style="font-size: 3rem;"></i>
                    CERTOLO
                </h1>
            </a>
        </div>
        
        <div class="card card-md">
            <div class="card-body">
                <h2 class="h2 text-center mb-4">Login to your account</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <div class="d-flex">
                            <div>
                                <i class="ti ti-alert-circle icon alert-icon"></i>
                            </div>
                            <div>
                                <?php foreach ($errors as $error): ?>
                                    <div><?php echo Security::escape($error); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <div class="d-flex">
                            <div>
                                <i class="ti ti-check icon alert-icon"></i>
                            </div>
                            <div>
                                <?php echo Security::escape($success); ?>
                            </div>
                        </div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="/login" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <input type="email" 
                               name="email" 
                               class="form-control <?php echo !empty($errors) && empty($email) ? 'is-invalid' : ''; ?>" 
                               placeholder="your@email.com" 
                               value="<?php echo Security::escape($email); ?>"
                               autocomplete="username"
                               required>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label">
                            Password
                            <span class="form-label-description">
                                <a href="/forgot-password">Forgot password?</a>
                            </span>
                        </label>
                        <div class="input-group input-group-flat">
                            <input type="password" 
                                   name="password" 
                                   id="password"
                                   class="form-control <?php echo !empty($errors) && isset($_POST['password']) ? 'is-invalid' : ''; ?>" 
                                   placeholder="Your password" 
                                   autocomplete="current-password"
                                   required>
                            <span class="input-group-text">
                                <a href="#" class="link-secondary" title="Show password" data-bs-toggle="tooltip" onclick="togglePassword()">
                                    <i class="ti ti-eye" id="password-icon"></i>
                                </a>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-check">
                            <input type="checkbox" name="remember" class="form-check-input"/>
                            <span class="form-check-label">Remember me on this device</span>
                        </label>
                    </div>
                    
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-login"></i> Sign in
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="text-center text-muted mt-3">
            Don't have an account yet? 
            <a href="/register" tabindex="-1">Sign up</a>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const passwordIcon = document.getElementById('password-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordIcon.classList.remove('ti-eye');
        passwordIcon.classList.add('ti-eye-off');
    } else {
        passwordInput.type = 'password';
        passwordIcon.classList.remove('ti-eye-off');
        passwordIcon.classList.add('ti-eye');
    }
}
</script>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>