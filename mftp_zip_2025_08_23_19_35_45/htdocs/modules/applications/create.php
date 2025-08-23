<?php
/**
 * CERTOLO - Create Application
 * For applicants to apply for certification
 */

// Temporary debugging - REMOVE IN PRODUCTION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Check if user is applicant
if ($userRole !== ROLE_APPLICANT) {
    header('HTTP/1.0 403 Forbidden');
    exit('Only applicants can create applications');
}

// Get standard ID from query parameter or POST
$standardId = $_GET['standard'] ?? $_POST['standard'] ?? null;

if (!$standardId) {
    header('Location: /standards');
    exit;
}

$errors = [];
$success = false;

try {
    $db = Database::getInstance();
    
    // Get standard details with criteria
    $stmt = $db->query(
        "SELECT s.*, u.company_name as certifier_name, u.id as certifier_id
         FROM standards s
         JOIN users u ON s.certifier_id = u.id
         WHERE s.id = :id AND s.status = 'active'",
        ['id' => $standardId]
    );
    
    $standard = $stmt->fetch();
    
    if (!$standard) {
        header('HTTP/1.0 404 Not Found');
        exit('Standard not found or not available');
    }
    
    // Check if user already has an application for this standard
    $existingStmt = $db->query(
        "SELECT id, status FROM applications 
         WHERE applicant_id = :applicant_id 
         AND standard_id = :standard_id 
         AND status NOT IN ('rejected', 'expired')",
        ['applicant_id' => $userId, 'standard_id' => $standardId]
    );
    
    $existingApp = $existingStmt->fetch();
    if ($existingApp && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Only redirect if not submitting form
        header('Location: /applications/view/' . $existingApp['id']);
        exit;
    }
    
    // Get criteria for this standard
    $criteriaStmt = $db->query(
        "SELECT * FROM criterias 
         WHERE standard_id = :standard_id 
         AND status = 'active'
         ORDER BY sort_order ASC, id ASC",
        ['standard_id' => $standardId]
    );
    
    $criteria = $criteriaStmt->fetchAll();
    
    if (empty($criteria)) {
        $errors[] = 'This standard has no criteria defined. Please contact the certifier.';
    }
    
    // Get user company information
    $userStmt = $db->query(
        "SELECT * FROM users WHERE id = :id",
        ['id' => $userId]
    );
    $userData = $userStmt->fetch();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
        // Debug: Check if form is being submitted
        error_log('Form submitted for standard: ' . $standardId);
        
        // Verify CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please refresh and try again.';
            error_log('CSRF token validation failed');
        } else {
            // Collect form data
            $applicationData = [
                'company_name' => $_POST['company_name'] ?? $userData['company_name'],
                'contact_person' => $_POST['contact_person'] ?? $userData['contact_person'],
                'email' => $_POST['email'] ?? $userData['email'],
                'phone' => $_POST['phone'] ?? $userData['phone'],
                'address' => $_POST['address'] ?? $userData['address'],
                'city' => $_POST['city'] ?? $userData['city'],
                'country' => $_POST['country'] ?? $userData['country'],
                'additional_info' => $_POST['additional_info'] ?? ''
            ];
            
            // Validate required fields
            if (empty($applicationData['company_name'])) {
                $errors[] = 'Company name is required';
            }
            if (empty($applicationData['contact_person'])) {
                $errors[] = 'Contact person is required';
            }
            if (empty($applicationData['email'])) {
                $errors[] = 'Email is required';
            }
            
            // Collect criteria responses
            $criteriaResponses = [];
            $missingResponses = [];
            
            foreach ($criteria as $criterion) {
                $response = $_POST['criteria_' . $criterion['id']] ?? '';
                if (empty($response)) {
                    $missingResponses[] = $criterion['name'];
                } else {
                    $criteriaResponses[$criterion['id']] = [
                        'meets_requirement' => $response,
                        'notes' => $_POST['notes_' . $criterion['id']] ?? ''
                    ];
                }
            }
            
            if (!empty($missingResponses)) {
                $errors[] = 'Please answer all criteria questions. Missing: ' . implode(', ', array_slice($missingResponses, 0, 3)) . (count($missingResponses) > 3 ? '...' : '');
            }
            
            error_log('Validation errors: ' . json_encode($errors));
            
            // Create application if no errors
            if (empty($errors)) {
                try {
                    $db->beginTransaction();
                    
                    // Generate application number
                    $appNumber = 'APP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Insert application
                    $stmt = $db->query(
                        "INSERT INTO applications (
                            application_number, applicant_id, certifier_id, standard_id,
                            status, application_data, company_data, created_at
                        ) VALUES (
                            :app_number, :applicant_id, :certifier_id, :standard_id,
                            'draft', :app_data, :company_data, NOW()
                        )",
                        [
                            'app_number' => $appNumber,
                            'applicant_id' => $userId,
                            'certifier_id' => $standard['certifier_id'],
                            'standard_id' => $standardId,
                            'app_data' => json_encode(['criteria' => $criteriaResponses]),
                            'company_data' => json_encode($applicationData)
                        ]
                    );
                    
                    $applicationId = $db->lastInsertId();
                    error_log('Application created with ID: ' . $applicationId);
                    
                    // Log activity
                    $db->query(
                        "INSERT INTO activity_logs (user_id, action, module, record_id, record_type, ip_address) 
                         VALUES (:user_id, 'create', 'applications', :record_id, 'application', :ip)",
                        [
                            'user_id' => $userId,
                            'record_id' => $applicationId,
                            'ip' => Security::getClientIP()
                        ]
                    );
                    
                    $db->commit();
                    
                    // Redirect to edit/upload documents
                    $_SESSION['success_message'] = 'Application created! Now upload supporting documents.';
                    header('Location: /applications/edit/' . $applicationId);
                    exit;
                    
                } catch (Exception $e) {
                    $db->rollback();
                    error_log('Create application error: ' . $e->getMessage());
                    $errors[] = 'Failed to create application: ' . $e->getMessage();
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log('Application create error: ' . $e->getMessage());
    header('Location: /standards');
    exit;
}

// Set page title
$pageTitle = 'Apply for ' . $standard['name'];

// Include header
include INCLUDES_PATH . 'header.php';
?>

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/standards">Standards</a></li>
                        <li class="breadcrumb-item"><a href="/standards/view/<?php echo $standardId; ?>"><?php echo Security::escape($standard['name']); ?></a></li>
                        <li class="breadcrumb-item active">Apply</li>
                    </ol>
                </nav>
                <h2 class="page-title">Certification Application</h2>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
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
        
        <?php 
        // DEBUG - Remove in production
        if ($_SERVER['REQUEST_METHOD'] === 'POST'): 
        ?>
            <div class="alert alert-info">
                <h4>Debug Info - Form Submitted</h4>
                <pre><?php print_r($_POST); ?></pre>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($criteria)): ?>
        <form method="POST" action="" class="row">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="standard" value="<?php echo $standardId; ?>">
            
            <!-- Standard Information -->
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Standard Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Standard:</strong><br>
                            <?php echo Security::escape($standard['name']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Type:</strong><br>
                            <?php echo Security::escape($standard['type']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Certifier:</strong><br>
                            <?php echo Security::escape($standard['certifier_name']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Validity:</strong><br>
                            <?php echo $standard['validity_months']; ?> months
                        </div>
                        <div>
                            <strong>Fee:</strong><br>
                            €<?php echo number_format($standard['price'], 2); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Company Information -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Company Information</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">This information will be pre-filled from your profile. You can update it if needed.</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" 
                                   value="<?php echo Security::escape($userData['company_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" 
                                   value="<?php echo Security::escape($userData['contact_person']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo Security::escape($userData['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo Security::escape($userData['phone']); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Criteria Checklist -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Criteria Assessment</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">
                            Please review each criterion below and indicate whether your organization meets the requirement. 
                            You will be able to upload supporting documents in the next step.
                        </p>
                        
                        <?php foreach ($criteria as $index => $criterion): ?>
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">
                                        <?php echo ($index + 1); ?>. <?php echo Security::escape($criterion['name']); ?>
                                        <?php if ($criterion['ra'] === 'Yes'): ?>
                                            <span class="badge bg-warning ms-2">Risk Assessment Required</span>
                                        <?php endif; ?>
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php if ($criterion['description']): ?>
                                        <p class="text-muted"><?php echo nl2br(Security::escape($criterion['description'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($criterion['requirements']): ?>
                                        <div class="alert alert-info">
                                            <strong>Requirements:</strong><br>
                                            <?php echo nl2br(Security::escape($criterion['requirements'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Does your organization meet this requirement?</label>
                                        <div class="form-selectgroup">
                                            <label class="form-selectgroup-item">
                                                <input type="radio" name="criteria_<?php echo $criterion['id']; ?>" 
                                                       value="yes" class="form-selectgroup-input" required>
                                                <span class="form-selectgroup-label">
                                                    <i class="ti ti-check text-success"></i> Yes
                                                </span>
                                            </label>
                                            <label class="form-selectgroup-item">
                                                <input type="radio" name="criteria_<?php echo $criterion['id']; ?>" 
                                                       value="partial" class="form-selectgroup-input">
                                                <span class="form-selectgroup-label">
                                                    <i class="ti ti-alert-circle text-warning"></i> Partially
                                                </span>
                                            </label>
                                            <label class="form-selectgroup-item">
                                                <input type="radio" name="criteria_<?php echo $criterion['id']; ?>" 
                                                       value="no" class="form-selectgroup-input">
                                                <span class="form-selectgroup-label">
                                                    <i class="ti ti-x text-danger"></i> No
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Additional Notes (optional)</label>
                                        <textarea name="notes_<?php echo $criterion['id']; ?>" 
                                                  class="form-control" 
                                                  rows="2"
                                                  placeholder="Provide any relevant details or explanations..."></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Additional Information (optional)</label>
                            <textarea name="additional_info" class="form-control" rows="3" 
                                      placeholder="Any other information you'd like to provide..."></textarea>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="btn-list justify-content-end">
                            <a href="/standards/view/<?php echo $standardId; ?>" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary" onclick="console.log('Submit clicked');">
                                <i class="ti ti-device-floppy"></i> Save & Continue
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Debug form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            console.log('Form is submitting...');
            
            // Check if all radio buttons are selected
            const radioGroups = {};
            const radios = form.querySelectorAll('input[type="radio"]');
            
            radios.forEach(radio => {
                if (!radioGroups[radio.name]) {
                    radioGroups[radio.name] = false;
                }
                if (radio.checked) {
                    radioGroups[radio.name] = true;
                }
            });
            
            // Check for unselected groups
            let missing = [];
            for (const [name, selected] of Object.entries(radioGroups)) {
                if (!selected && name.startsWith('criteria_')) {
                    missing.push(name);
                }
            }
            
            if (missing.length > 0) {
                alert('Please answer all criteria questions before continuing.');
                e.preventDefault();
                return false;
            }
            
            console.log('Form validation passed, submitting...');
        });
    }
});
</script>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>