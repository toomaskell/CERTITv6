<?php
/**
 * CERTOLO - Registration Module
 * Handles new user registration
 */

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard');
    exit;
}

$errors = [];
$formData = [
    'role' => $_POST['role'] ?? '',
    'company_name' => $_POST['company_name'] ?? '',
    'contact_person' => $_POST['contact_person'] ?? '',
    'email' => $_POST['email'] ?? '',
    'phone' => $_POST['phone'] ?? '',
    'address' => $_POST['address'] ?? '',
    'city' => $_POST['city'] ?? '',
    'country' => $_POST['country'] ?? '',
    'vat_number' => $_POST['vat_number'] ?? '',
    'registration_number' => $_POST['registration_number'] ?? '',
    'website' => $_POST['website'] ?? '',
    'certification_capabilities' => $_POST['certification_capabilities'] ?? []
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        // Validate role
        if (!in_array($formData['role'], ['applicant', 'certifier'])) {
            $errors[] = 'Please select a valid account type';
        }
        
        // Validate required fields
        if (empty($formData['company_name'])) {
            $errors[] = 'Company name is required';
        }
        
        if (empty($formData['contact_person'])) {
            $errors[] = 'Contact person name is required';
        }
        
        if (empty($formData['email'])) {
            $errors[] = 'Email address is required';
        } elseif (!Security::validateEmail($formData['email'])) {
            $errors[] = 'Invalid email format';
        }
        
        // Validate password
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($password)) {
            $errors[] = 'Password is required';
        } else {
            $passwordErrors = Security::validatePasswordStrength($password);
            if (!empty($passwordErrors)) {
                $errors = array_merge($errors, $passwordErrors);
            }
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        // Validate terms acceptance
        if (!isset($_POST['agree_terms'])) {
            $errors[] = 'You must agree to the terms and conditions';
        }
        
        // Check if email already exists
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                $stmt = $db->query(
                    "SELECT id FROM users WHERE email = :email",
                    ['email' => $formData['email']]
                );
                
                if ($stmt->fetch()) {
                    $errors[] = 'An account with this email already exists';
                }
            } catch (Exception $e) {
                error_log('Registration check error: ' . $e->getMessage());
                $errors[] = 'An error occurred. Please try again.';
            }
        }
        
        // Create account if no errors
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                $db->beginTransaction();
                
                // Hash password
                $hashedPassword = Security::hashPassword($password);
                
                // Generate email verification token
                $verificationToken = Security::generateToken(32);
                
                // Prepare certification capabilities for certifiers
                $capabilities = null;
                if ($formData['role'] === 'certifier' && !empty($formData['certification_capabilities'])) {
                    $capabilities = json_encode($formData['certification_capabilities']);
                }
                
                // Insert user
                $stmt = $db->query(
                    "INSERT INTO users (
                        email, password, role, company_name, contact_person, 
                        phone, address, city, country, vat_number, 
                        registration_number, website, certification_capabilities,
                        remember_token, created_at
                    ) VALUES (
                        :email, :password, :role, :company_name, :contact_person,
                        :phone, :address, :city, :country, :vat_number,
                        :registration_number, :website, :capabilities,
                        :token, NOW()
                    )",
                    [
                        'email' => $formData['email'],
                        'password' => $hashedPassword,
                        'role' => $formData['role'],
                        'company_name' => $formData['company_name'],
                        'contact_person' => $formData['contact_person'],
                        'phone' => $formData['phone'],
                        'address' => $formData['address'],
                        'city' => $formData['city'],
                        'country' => $formData['country'],
                        'vat_number' => $formData['vat_number'],
                        'registration_number' => $formData['registration_number'],
                        'website' => $formData['website'],
                        'capabilities' => $capabilities,
                        'token' => $verificationToken
                    ]
                );
                
                $userId = $db->lastInsertId();
                
                // Send verification email
                $verificationLink = SITE_URL . '/verify-email?token=' . $verificationToken;
                
                // Queue email (simplified for now - in production use proper email queue)
                $db->query(
                    "INSERT INTO email_logs (to_email, subject, template, data) 
                     VALUES (:email, :subject, :template, :data)",
                    [
                        'email' => $formData['email'],
                        'subject' => 'Verify your CERTOLO account',
                        'template' => 'email_verification',
                        'data' => json_encode([
                            'name' => $formData['contact_person'],
                            'verification_link' => $verificationLink
                        ])
                    ]
                );
                
                // Log activity
                $db->query(
                    "INSERT INTO activity_logs (user_id, action, module, ip_address, user_agent) 
                     VALUES (:user_id, 'register', 'auth', :ip, :agent)",
                    [
                        'user_id' => $userId,
                        'ip' => Security::getClientIP(),
                        'agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]
                );
                
                $db->commit();
                
                // Redirect to login with success message
                header('Location: /login?registered=1');
                exit;
                
            } catch (Exception $e) {
                $db->rollback();
                error_log('Registration error: ' . $e->getMessage());
                $errors[] = 'An error occurred during registration. Please try again.';
            }
        }
    }
}

// Set page title
$pageTitle = 'Register';

// Include header
include INCLUDES_PATH . 'header.php';
?>

<div class="page-body">
    <div class="container py-4" style="max-width: 800px;">
        <div class="text-center mb-4">
            <h1 class="certolo-logo-text text-decoration-none">
                <i class="ti ti-certificate-2 certolo-logo-icon" style="font-size: 3rem;"></i>
                CERTOLO
            </h1>
        </div>
        
        <form class="card" method="POST" action="/register" autocomplete="off" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="card-header">
                <h3 class="card-title">Create new account</h3>
            </div>
            
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <div class="d-flex">
                            <div>
                                <i class="ti ti-alert-circle icon alert-icon"></i>
                            </div>
                            <div>
                                <h4 class="alert-title">Please correct the following errors:</h4>
                                <?php foreach ($errors as $error): ?>
                                    <div><?php echo Security::escape($error); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                    </div>
                <?php endif; ?>
                
                <!-- Account Type Selection -->
                <div class="mb-4">
                    <label class="form-label required">I want to:</label>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-selectgroup-item">
                                <input type="radio" name="role" value="applicant" class="form-selectgroup-input" <?php echo $formData['role'] === 'applicant' ? 'checked' : ''; ?>>
                                <span class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3">
                                        <span class="form-selectgroup-check"></span>
                                    </span>
                                    <span>
                                        <strong>Apply for Certification</strong>
                                        <div class="text-muted">I'm seeking certification</div>
                                    </span>
                                </span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-selectgroup-item">
                                <input type="radio" name="role" value="certifier" class="form-selectgroup-input" <?php echo $formData['role'] === 'certifier' ? 'checked' : ''; ?>>
                                <span class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3">
                                        <span class="form-selectgroup-check"></span>
                                    </span>
                                    <span>
                                        <strong>Issue Certifications</strong>
                                        <div class="text-muted">I'm a certification body</div>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Company Information -->
                <h4 class="mb-3">Company Information</h4>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label required">Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo Security::escape($formData['company_name']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Registration Number</label>
                        <input type="text" name="registration_number" class="form-control" value="<?php echo Security::escape($formData['registration_number']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">VAT Number</label>
                        <input type="text" name="vat_number" class="form-control" value="<?php echo Security::escape($formData['vat_number']); ?>">
                    </div>
                </div>
                
                <!-- Contact Information -->
                <h4 class="mb-3">Contact Information</h4>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" value="<?php echo Security::escape($formData['contact_person']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo Security::escape($formData['email']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo Security::escape($formData['phone']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" value="<?php echo Security::escape($formData['website']); ?>">
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo Security::escape($formData['address']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="<?php echo Security::escape($formData['city']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Country</label>
                        <select name="country" class="form-select">
                            <option value="">Select country...</option>
                            <option value="EE" <?php echo $formData['country'] === 'EE' ? 'selected' : ''; ?>>Estonia</option>
                            <option value="LV" <?php echo $formData['country'] === 'LV' ? 'selected' : ''; ?>>Latvia</option>
                            <option value="LT" <?php echo $formData['country'] === 'LT' ? 'selected' : ''; ?>>Lithuania</option>
                            <option value="FI" <?php echo $formData['country'] === 'FI' ? 'selected' : ''; ?>>Finland</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                </div>
                
                <!-- Password -->
                <h4 class="mb-3">Security</h4>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <small class="form-hint">Min 8 chars with uppercase, lowercase, number & special char</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                
                <!-- Terms -->
                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" name="agree_terms" class="form-check-input" required>
                        <span class="form-check-label">
                            I agree to the terms and conditions and privacy policy
                        </span>
                    </label>
                </div>
            </div>
            
            <div class="card-footer bg-transparent">
                <div class="btn-list justify-content-end">
                    <a href="/login" class="btn">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-user-plus"></i> Create Account
                    </button>
                </div>
            </div>
        </form>
        
        <div class="text-center text-muted mt-3">
            Already have an account? <a href="/login" tabindex="-1">Sign in</a>
        </div>
    </div>
</div>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>