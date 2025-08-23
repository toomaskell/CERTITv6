<?php
/**
 * CERTOLO - Edit Application
 * Upload documents and manage draft applications
 */

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Get application ID
$applicationId = $id ?? $_GET['id'] ?? null;

if (!$applicationId) {
    header('Location: /applications');
    exit;
}

$errors = [];
$success = false;

try {
    $db = Database::getInstance();
    
    // Get application details
    $stmt = $db->query(
        "SELECT a.*, s.name as standard_name, s.id as standard_id
         FROM applications a
         JOIN standards s ON a.standard_id = s.id
         WHERE a.id = :id AND a.applicant_id = :user_id AND a.status = 'draft'",
        ['id' => $applicationId, 'user_id' => $userId]
    );
    
    $application = $stmt->fetch();
    
    if (!$application) {
        header('Location: /applications');
        exit;
    }
    
    // Get criteria for this standard
    $criteriaStmt = $db->query(
        "SELECT * FROM criterias 
         WHERE standard_id = :standard_id 
         AND status = 'active'
         ORDER BY sort_order ASC, id ASC",
        ['standard_id' => $application['standard_id']]
    );
    
    $criteria = $criteriaStmt->fetchAll();
    
    // Get existing documents
    $docsStmt = $db->query(
        "SELECT * FROM application_documents 
         WHERE application_id = :app_id 
         ORDER BY uploaded_at DESC",
        ['app_id' => $applicationId]
    );
    
    $documents = $docsStmt->fetchAll();
    
    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Verify CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please refresh and try again.';
        } else {
            if ($_POST['action'] === 'upload' && isset($_FILES['document'])) {
                $criterionId = $_POST['criterion_id'] ?? null;
                $documentType = $_POST['document_type'] ?? 'general';
                
                // Validate file
                $fileErrors = Security::validateFileUpload($_FILES['document']);
                
                if (!empty($fileErrors)) {
                    $errors = array_merge($errors, $fileErrors);
                } else {
                    try {
                        // Generate unique filename
                        $filename = Security::generateUniqueFilename($_FILES['document']['name']);
                        $uploadPath = UPLOAD_APPLICATIONS . $filename;
                        
                        // Create directory if not exists
                        if (!is_dir(UPLOAD_APPLICATIONS)) {
                            mkdir(UPLOAD_APPLICATIONS, 0755, true);
                        }
                        
                        // Move uploaded file
                        if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadPath)) {
                            // Save to database
                            $stmt = $db->query(
                                "INSERT INTO application_documents (
                                    application_id, criteria_id, document_type, 
                                    document_name, original_name, file_path, 
                                    file_size, file_type, uploaded_by
                                ) VALUES (
                                    :app_id, :criteria_id, :doc_type,
                                    :doc_name, :original_name, :file_path,
                                    :file_size, :file_type, :uploaded_by
                                )",
                                [
                                    'app_id' => $applicationId,
                                    'criteria_id' => $criterionId,
                                    'doc_type' => $documentType,
                                    'doc_name' => $_POST['document_name'] ?? $_FILES['document']['name'],
                                    'original_name' => $_FILES['document']['name'],
                                    'file_path' => 'applications/' . $filename,
                                    'file_size' => $_FILES['document']['size'],
                                    'file_type' => strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION)),
                                    'uploaded_by' => $userId
                                ]
                            );
                            
                            $success = true;
                            $_SESSION['success_message'] = 'Document uploaded successfully!';
                            
                            // Refresh page to show new document
                            header('Location: /applications/edit/' . $applicationId);
                            exit;
                            
                        } else {
                            $errors[] = 'Failed to upload file. Please try again.';
                        }
                    } catch (Exception $e) {
                        error_log('Document upload error: ' . $e->getMessage());
                        $errors[] = 'Failed to save document information.';
                    }
                }
            } elseif ($_POST['action'] === 'delete' && isset($_POST['document_id'])) {
                // Delete document
                try {
                    // Get document info
                    $docStmt = $db->query(
                        "SELECT * FROM application_documents 
                         WHERE id = :id AND application_id = :app_id",
                        ['id' => $_POST['document_id'], 'app_id' => $applicationId]
                    );
                    
                    $doc = $docStmt->fetch();
                    if ($doc) {
                        // Delete file from disk
                        $filePath = UPLOADS_PATH . $doc['file_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        
                        // Delete from database
                        $db->query(
                            "DELETE FROM application_documents WHERE id = :id",
                            ['id' => $_POST['document_id']]
                        );
                        
                        $_SESSION['success_message'] = 'Document deleted successfully!';
                    }
                    
                    header('Location: /applications/edit/' . $applicationId);
                    exit;
                    
                } catch (Exception $e) {
                    error_log('Document delete error: ' . $e->getMessage());
                    $errors[] = 'Failed to delete document.';
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log('Edit application error: ' . $e->getMessage());
    header('Location: /applications');
    exit;
}

// Set page title
$pageTitle = 'Edit Application - ' . $application['application_number'];

// Include header
include INCLUDES_PATH . 'header.php';
?>

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/applications">Applications</a></li>
                        <li class="breadcrumb-item"><a href="/applications/view/<?php echo $applicationId; ?>"><?php echo Security::escape($application['application_number']); ?></a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h2 class="page-title">Upload Supporting Documents</h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <a href="/applications/view/<?php echo $applicationId; ?>" class="btn">
                    <i class="ti ti-arrow-left"></i> Back to Application
                </a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <?php if (isset($_SESSION['success_message'])): ?>
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
            <!-- Upload Form -->
            <div class="col-lg-4">
                <div class="card">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="card-header">
                            <h3 class="card-title">Upload Document</h3>
                        </div>
                        
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Document Name</label>
                                <input type="text" name="document_name" class="form-control" 
                                       placeholder="e.g., Quality Manual, ISO Certificate">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Document Type</label>
                                <select name="document_type" class="form-select">
                                    <option value="general">General Document</option>
                                    <option value="certificate">Certificate</option>
                                    <option value="report">Report/Audit</option>
                                    <option value="policy">Policy Document</option>
                                    <option value="evidence">Evidence/Proof</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Related to Criterion (Optional)</label>
                                <select name="criterion_id" class="form-select">
                                    <option value="">-- General Document --</option>
                                    <?php foreach ($criteria as $criterion): ?>
                                        <option value="<?php echo $criterion['id']; ?>">
                                            <?php echo Security::escape($criterion['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required">Select File</label>
                                <input type="file" name="document" class="form-control" required
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <small class="form-hint">
                                    Allowed: PDF, Word, JPG, PNG (Max: <?php echo MAX_FILE_SIZE / 1024 / 1024; ?>MB)
                                </small>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ti ti-upload"></i> Upload Document
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Instructions -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h4>Document Guidelines</h4>
                        <ul class="ps-3">
                            <li>Upload all relevant certificates and documents</li>
                            <li>Ensure documents are clear and readable</li>
                            <li>Use descriptive names for easy identification</li>
                            <li>Link documents to specific criteria when applicable</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Documents List -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Uploaded Documents (<?php echo count($documents); ?>)</h3>
                    </div>
                    
                    <?php if (empty($documents)): ?>
                        <div class="card-body text-center py-5">
                            <i class="ti ti-files-off" style="font-size: 4rem; color: #ccc;"></i>
                            <h3 class="mt-3">No Documents Yet</h3>
                            <p class="text-muted">Start by uploading supporting documents for your application.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Document Name</th>
                                        <th>Type</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th width="100"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <?php
                                                        $iconMap = [
                                                            'pdf' => 'file-type-pdf',
                                                            'doc' => 'file-type-doc',
                                                            'docx' => 'file-type-docx',
                                                            'jpg' => 'file-type-jpg',
                                                            'jpeg' => 'file-type-jpg',
                                                            'png' => 'file-type-png'
                                                        ];
                                                        $icon = $iconMap[$doc['file_type']] ?? 'file';
                                                        ?>
                                                        <i class="ti ti-<?php echo $icon; ?>" style="font-size: 2rem;"></i>
                                                    </div>
                                                    <div>
                                                        <div><?php echo Security::escape($doc['document_name']); ?></div>
                                                        <?php if ($doc['criteria_id']): ?>
                                                            <?php
                                                            $criterion = array_filter($criteria, function($c) use ($doc) {
                                                                return $c['id'] == $doc['criteria_id'];
                                                            });
                                                            $criterion = reset($criterion);
                                                            ?>
                                                            <?php if ($criterion): ?>
                                                                <small class="text-muted">
                                                                    Related to: <?php echo Security::escape($criterion['name']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-muted">
                                                <?php echo ucfirst($doc['document_type']); ?>
                                            </td>
                                            <td class="text-muted">
                                                <?php echo number_format($doc['file_size'] / 1024, 1); ?> KB
                                            </td>
                                            <td class="text-muted">
                                                <?php echo date('d M Y', strtotime($doc['uploaded_at'])); ?>
                                            </td>
                                            <td>
                                                <div class="btn-list flex-nowrap">
                                                    <a href="/uploads/<?php echo $doc['file_path']; ?>" 
                                                       target="_blank" 
                                                       class="btn btn-sm">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                                        <button type="submit" 
                                                                class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Delete this document?')">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-footer">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <?php if (count($documents) > 0): ?>
                                    <span class="text-muted">
                                        Total size: 
                                        <?php 
                                        $totalSize = array_sum(array_column($documents, 'file_size'));
                                        echo number_format($totalSize / 1024 / 1024, 2); 
                                        ?> MB
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="/applications/view/<?php echo $applicationId; ?>" class="btn btn-primary">
                                    <i class="ti ti-check"></i> Done Uploading
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>