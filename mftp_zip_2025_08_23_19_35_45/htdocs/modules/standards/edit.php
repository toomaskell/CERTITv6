<?php
/**
 * CERTOLO - Edit Standard
 * For certifiers to edit their certification standards
 */

// Check if user is certifier
if ($userRole !== ROLE_CERTIFIER) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Get standard ID
$standardId = $id ?? $_GET['id'] ?? null;

if (!$standardId) {
    header('Location: /standards');
    exit;
}

$errors = [];
$success = false;

try {
    $db = Database::getInstance();
    
    // Get standard details
    $stmt = $db->query(
        "SELECT * FROM standards WHERE id = :id AND certifier_id = :certifier_id",
        ['id' => $standardId, 'certifier_id' => $userId]
    );
    
    $standard = $stmt->fetch();
    
    if (!$standard) {
        header('HTTP/1.0 404 Not Found');
        exit('Standard not found or access denied');
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please refresh and try again.';
        } else {
            $formData = [
                'name' => $_POST['name'] ?? '',
                'code' => $_POST['code'] ?? '',
                'type' => $_POST['type'] ?? '',
                'description' => $_POST['description'] ?? '',
                'requirements' => $_POST['requirements'] ?? '',
                'validity_months' => $_POST['validity_months'] ?? 12,
                'price' => $_POST['price'] ?? '',
                'status' => $_POST['status'] ?? 'active'
            ];
            
            // Validate required fields
            if (empty($formData['name'])) {
                $errors[] = 'Standard name is required';
            }
            
            if (empty($formData['type'])) {
                $errors[] = 'Standard type is required';
            }
            
            if (empty($formData['validity_months']) || !is_numeric($formData['validity_months'])) {
                $errors[] = 'Valid validity period is required';
            }
            
            if (empty($formData['price']) || !is_numeric($formData['price'])) {
                $errors[] = 'Valid price is required';
            }
            
            // Handle file upload if provided
            $filePath = $standard['file_path'];
            if (isset($_FILES['standard_file']) && $_FILES['standard_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $fileErrors = Security::validateFileUpload($_FILES['standard_file']);
                if (!empty($fileErrors)) {
                    $errors = array_merge($errors, $fileErrors);
                } else {
                    // Delete old file if exists
                    if ($filePath && file_exists(UPLOADS_PATH . $filePath)) {
                        unlink(UPLOADS_PATH . $filePath);
                    }
                    
                    // Generate unique filename and move file
                    $filename = Security::generateUniqueFilename($_FILES['standard_file']['name']);
                    $uploadPath = UPLOAD_STANDARDS . $filename;
                    
                    if (!move_uploaded_file($_FILES['standard_file']['tmp_name'], $uploadPath)) {
                        $errors[] = 'Failed to upload file';
                    } else {
                        $filePath = 'standards/' . $filename;
                    }
                }
            }
            
            // Update standard if no errors
            if (empty($errors)) {
                try {
                    // Update standard
                    $updateStmt = $db->query(
                        "UPDATE standards SET
                            name = :name,
                            code = :code,
                            type = :type,
                            description = :description,
                            requirements = :requirements,
                            validity_months = :validity_months,
                            price = :price,
                            status = :status,
                            file_path = :file_path,
                            updated_at = NOW()
                        WHERE id = :id AND certifier_id = :certifier_id",
                        [
                            'name' => $formData['name'],
                            'code' => $formData['code'],
                            'type' => $formData['type'],
                            'description' => $formData['description'],
                            'requirements' => $formData['requirements'],
                            'validity_months' => $formData['validity_months'],
                            'price' => $formData['price'],
                            'status' => $formData['status'],
                            'file_path' => $filePath,
                            'id' => $standardId,
                            'certifier_id' => $userId
                        ]
                    );
                    
                    // Log activity
                    $db->query(
                        "INSERT INTO activity_logs (user_id, action, module, record_id, record_type, ip_address) 
                         VALUES (:user_id, 'update', 'standards', :record_id, 'standard', :ip)",
                        [
                            'user_id' => $userId,
                            'record_id' => $standardId,
                            'ip' => Security::getClientIP()
                        ]
                    );
                    
                    $success = true;
                    $_SESSION['success_message'] = 'Standard updated successfully!';
                    
                    // Refresh standard data
                    $stmt = $db->query(
                        "SELECT * FROM standards WHERE id = :id",
                        ['id' => $standardId]
                    );
                    $standard = $stmt->fetch();
                    
                } catch (Exception $e) {
                    error_log('Update standard error: ' . $e->getMessage());
                    $errors[] = 'An error occurred while updating the standard';
                }
            }
        }
    } else {
        // Set form data from existing standard
        $formData = $standard;
    }
    
} catch (Exception $e) {
    error_log('Edit standard error: ' . $e->getMessage());
    header('Location: /standards');
    exit;
}

// Common standard types
$standardTypes = [
    'ISO 9001' => 'ISO 9001 - Quality Management',
    'ISO 14001' => 'ISO 14001 - Environmental Management',
    'ISO 45001' => 'ISO 45001 - Occupational Health & Safety',
    'ISO 27001' => 'ISO 27001 - Information Security',
    'Industry' => 'Industry Specific',
    'Custom' => 'Custom Standard'
];

// Set page title
$pageTitle = 'Edit Standard - ' . $standard['name'];

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
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h2 class="page-title">Edit Standard</h2>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <?php if ($success && isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible" role="alert">
                <div class="d-flex">
                    <div>
                        <i class="ti ti-check icon alert-icon"></i>
                    </div>
                    <div>
                        <?php 
                        echo Security::escape($_SESSION['success_message']); 
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                </div>
                <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="/standards/edit/<?php echo $standardId; ?>" enctype="multipart/form-data" class="card">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="card-header">
                <h3 class="card-title">Standard Information</h3>
            </div>
            
            <div class="card-body">
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
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label required">Standard Name</label>
                            <input type="text" 
                                   name="name" 
                                   class="form-control" 
                                   value="<?php echo Security::escape($formData['name']); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Standard Code</label>
                            <input type="text" 
                                   name="code" 
                                   class="form-control" 
                                   value="<?php echo Security::escape($formData['code']); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label required">Standard Type</label>
                            <select name="type" class="form-select" required>
                                <option value="">Select type...</option>
                                <?php foreach ($standardTypes as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" 
                                            <?php echo $formData['type'] === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>
                                    Active - Available for applications
                                </option>
                                <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>
                                    Inactive - Not available
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" 
                                      class="form-control" 
                                      rows="3"><?php echo Security::escape($formData['description']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Requirements</label>
                            <textarea name="requirements" 
                                      class="form-control" 
                                      rows="4"><?php echo Security::escape($formData['requirements']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label required">Validity Period (months)</label>
                            <input type="number" 
                                   name="validity_months" 
                                   class="form-control" 
                                   min="1" 
                                   max="60"
                                   value="<?php echo Security::escape($formData['validity_months']); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label required">Price (EUR)</label>
                            <input type="number" 
                                   name="price" 
                                   class="form-control" 
                                   min="0" 
                                   step="0.01"
                                   value="<?php echo Security::escape($formData['price']); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Standard Document</label>
                            <input type="file" 
                                   name="standard_file" 
                                   class="form-control"
                                   accept=".pdf,.doc,.docx">
                            <?php if ($formData['file_path']): ?>
                                <small class="form-hint">
                                    Current file: <a href="/uploads/<?php echo $formData['file_path']; ?>" target="_blank">Download</a>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-footer">
                <div class="btn-list justify-content-end">
                    <a href="/standards/view/<?php echo $standardId; ?>" class="btn">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>