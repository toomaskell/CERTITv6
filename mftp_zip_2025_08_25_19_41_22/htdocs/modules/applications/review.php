<?php
/**
 * CERTOLO - Review Application
 * For certifiers to review and approve/reject applications
 */

// Check if user is certifier
if ($userRole !== ROLE_CERTIFIER) {
    header('HTTP/1.0 403 Forbidden');
    exit('Only certifiers can review applications');
}

// Get application ID
$applicationId = $id ?? $_GET['id'] ?? null;

if (!$applicationId) {
    header('Location: /applications');
    exit;
}

$errors = [];

try {
    $db = Database::getInstance();
    
    // Get application details with all related data
    $stmt = $db->query(
        "SELECT a.*, 
                s.name as standard_name, s.type as standard_type,
                u.company_name as applicant_company, u.email as applicant_email,
                u.contact_person as applicant_contact, u.phone as applicant_phone
         FROM applications a
         JOIN standards s ON a.standard_id = s.id
         JOIN users u ON a.applicant_id = u.id
         WHERE a.id = :id 
         AND a.certifier_id = :certifier_id 
         AND a.status IN ('submitted', 'under_review')",
        ['id' => $applicationId, 'certifier_id' => $userId]
    );
    
    $application = $stmt->fetch();
    
    if (!$application) {
        header('Location: /applications');
        exit;
    }
    
    // Parse application data
    $applicationData = json_decode($application['application_data'], true) ?? [];
    $companyData = json_decode($application['company_data'], true) ?? [];
    $criteriaResponses = $applicationData['criteria'] ?? [];
    
    // Get criteria
    $criteriaStmt = $db->query(
        "SELECT * FROM criterias 
         WHERE standard_id = :standard_id 
         ORDER BY sort_order ASC, id ASC",
        ['standard_id' => $application['standard_id']]
    );
    
    $criteria = $criteriaStmt->fetchAll();
    
    // Get documents
    $docsStmt = $db->query(
        "SELECT * FROM application_documents 
         WHERE application_id = :app_id 
         ORDER BY uploaded_at DESC",
        ['app_id' => $applicationId]
    );
    
    $documents = $docsStmt->fetchAll();
    
    // Update status to under_review if still submitted
    if ($application['status'] === 'submitted') {
        $db->query(
            "UPDATE applications SET status = 'under_review', reviewed_at = NOW() WHERE id = :id",
            ['id' => $applicationId]
        );
        $application['status'] = 'under_review';
    }
    
    // Handle AJAX comment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'add_comment') {
        header('Content-Type: application/json');
        
        try {
            $criteriaId = $_POST['criteria_id'] ?? null;
            $message = trim($_POST['message'] ?? '');
            
            if (!$criteriaId || empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit;
            }
            
            // Verify CSRF token
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Invalid security token']);
                exit;
            }
            
            // Insert comment
            $stmt = $db->query(
                "INSERT INTO criteria_discussions (application_id, criteria_id, user_id, message) 
                 VALUES (:app_id, :criteria_id, :user_id, :message)",
                [
                    'app_id' => $applicationId,
                    'criteria_id' => $criteriaId,
                    'user_id' => $userId,
                    'message' => $message
                ]
            );
            
            // Get the inserted comment with user info
            $commentId = $db->lastInsertId();
            $commentStmt = $db->query(
                "SELECT cd.*, u.contact_person, u.role 
                 FROM criteria_discussions cd
                 JOIN users u ON cd.user_id = u.id
                 WHERE cd.id = :id",
                ['id' => $commentId]
            );
            $comment = $commentStmt->fetch();
            
            echo json_encode([
                'success' => true,
                'comment' => [
                    'id' => $comment['id'],
                    'message' => $comment['message'],
                    'author' => $comment['contact_person'],
                    'role' => $comment['role'],
                    'created_at' => date('d M Y H:i', strtotime($comment['created_at']))
                ]
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
            exit;
        }
    }
    
    // Handle AJAX file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'upload_file') {
        header('Content-Type: application/json');
        
        try {
            $criteriaId = $_POST['criteria_id'] ?? null;
            
            if (!$criteriaId || !isset($_FILES['file'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit;
            }
            
            // Verify CSRF token
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Invalid security token']);
                exit;
            }
            
            // Validate file
            $file = $_FILES['file'];
            $fileErrors = Security::validateFileUpload($file);
            
            if (!empty($fileErrors)) {
                echo json_encode(['success' => false, 'message' => implode(', ', $fileErrors)]);
                exit;
            }
            
            // Generate unique filename
            $filename = Security::generateUniqueFilename($file['name']);
            $uploadPath = UPLOAD_APPLICATIONS . $filename;
            
            // Create directory if not exists
            if (!is_dir(UPLOAD_APPLICATIONS)) {
                mkdir(UPLOAD_APPLICATIONS, 0755, true);
            }
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Save to database
                $stmt = $db->query(
                    "INSERT INTO criteria_files (
                        application_id, criteria_id, user_id, 
                        file_name, original_name, file_path, 
                        file_size, file_type
                    ) VALUES (
                        :app_id, :criteria_id, :user_id,
                        :file_name, :original_name, :file_path,
                        :file_size, :file_type
                    )",
                    [
                        'app_id' => $applicationId,
                        'criteria_id' => $criteriaId,
                        'user_id' => $userId,
                        'file_name' => pathinfo($file['name'], PATHINFO_FILENAME),
                        'original_name' => $file['name'],
                        'file_path' => 'applications/' . $filename,
                        'file_size' => $file['size'],
                        'file_type' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))
                    ]
                );
                
                echo json_encode([
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'file' => [
                        'id' => $db->lastInsertId(),
                        'name' => $file['name'],
                        'size' => $file['size'],
                        'uploader' => $_SESSION['user_name'],
                        'timestamp' => date('d M Y H:i'),
                        'path' => 'applications/' . $filename
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
            
        } catch (Exception $e) {
            error_log('File upload error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        }
        exit;
    }
    
    // Handle review submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
        // Verify CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token.';
        } else {
            $decision = $_POST['decision'] ?? '';
            $notes = $_POST['decision_notes'] ?? '';
            $criteriaReviews = $_POST['criteria_review'] ?? [];
            
            if (!in_array($decision, ['approve', 'reject'])) {
                $errors[] = 'Please select a decision.';
            }
            
            if (empty($notes)) {
                $errors[] = 'Please provide review notes.';
            }
            
            if (empty($errors)) {
                try {
                    $db->beginTransaction();
                    
                    // Update application
                    $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
                    $dateField = $decision === 'approve' ? 'approved_at' : 'rejected_at';
                    
                    $updateStmt = $db->query(
                        "UPDATE applications 
                         SET status = :status, 
                             decision_notes = :notes,
                             reviewer_id = :reviewer_id,
                             $dateField = NOW(),
                             updated_at = NOW()
                         WHERE id = :id",
                        [
                            'status' => $newStatus,
                            'notes' => $notes,
                            'reviewer_id' => $userId,
                            'id' => $applicationId
                        ]
                    );
                    
                    // Save review history
                    $historyStmt = $db->query(
                        "INSERT INTO review_history (application_id, reviewer_id, action, notes, criteria_reviews) 
                         VALUES (:app_id, :reviewer_id, :action, :notes, :criteria)",
                        [
                            'app_id' => $applicationId,
                            'reviewer_id' => $userId,
                            'action' => $decision,
                            'notes' => $notes,
                            'criteria' => json_encode($criteriaReviews)
                        ]
                    );
                    
                    // Create notification for applicant
                    $notifyStmt = $db->query(
                        "INSERT INTO notifications (user_id, type, title, message, data) 
                         VALUES (:user_id, :type, :title, :message, :data)",
                        [
                            'user_id' => $application['applicant_id'],
                            'type' => 'application_' . $newStatus,
                            'title' => 'Application ' . ucfirst($newStatus),
                            'message' => 'Your application for ' . $application['standard_name'] . ' has been ' . $newStatus . '.',
                            'data' => json_encode(['application_id' => $applicationId])
                        ]
                    );
                    
                    // Queue email
                    $emailStmt = $db->query(
                        "INSERT INTO email_logs (to_email, subject, template, data) 
                         VALUES (:email, :subject, :template, :data)",
                        [
                            'email' => $application['applicant_email'],
                            'subject' => 'Application ' . ucfirst($newStatus) . ' - ' . $application['application_number'],
                            'template' => 'application_' . $newStatus,
                            'data' => json_encode([
                                'applicant_name' => $application['applicant_contact'],
                                'application_number' => $application['application_number'],
                                'standard_name' => $application['standard_name'],
                                'decision_notes' => $notes,
                                'view_link' => SITE_URL . '/applications/view/' . $applicationId
                            ])
                        ]
                    );
                    
                    $db->commit();
                    
                    $_SESSION['success_message'] = 'Application ' . $newStatus . ' successfully!';
                    header('Location: /applications');
                    exit;
                    
                } catch (Exception $e) {
                    $db->rollback();
                    error_log('Review submission error: ' . $e->getMessage());
                    $errors[] = 'Failed to submit review. Please try again.';
                }
            }
        }
    }
    
    // Get all criteria discussions
    $discussionsStmt = $db->query(
        "SELECT cd.*, u.contact_person, u.role 
         FROM criteria_discussions cd
         JOIN users u ON cd.user_id = u.id
         WHERE cd.application_id = :app_id
         ORDER BY cd.created_at ASC",
        ['app_id' => $applicationId]
    );
    
    $allDiscussions = $discussionsStmt->fetchAll();
    
    // Group discussions by criteria_id
    $criteriaDiscussions = [];
    foreach ($allDiscussions as $discussion) {
        $criteriaDiscussions[$discussion['criteria_id']][] = $discussion;
    }
    
    // Get all criteria files
    $filesStmt = $db->query(
        "SELECT cf.*, u.contact_person, u.role 
         FROM criteria_files cf
         JOIN users u ON cf.user_id = u.id
         WHERE cf.application_id = :app_id
         ORDER BY cf.uploaded_at DESC",
        ['app_id' => $applicationId]
    );
    
    $allFiles = $filesStmt->fetchAll();
    
    // Group files by criteria_id
    $criteriaFiles = [];
    foreach ($allFiles as $file) {
        $criteriaFiles[$file['criteria_id']][] = $file;
    }
    
} catch (Exception $e) {
    error_log('Review page error: ' . $e->getMessage());
    header('Location: /applications');
    exit;
}

// Set page title
$pageTitle = 'Review Application - ' . $application['application_number'];

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
                        <li class="breadcrumb-item active">Review</li>
                    </ol>
                </nav>
                <h2 class="page-title">Review Application</h2>
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
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Application Info -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Application Details</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Application Number:</strong><br>
                                        <?php echo Security::escape($application['application_number']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Standard:</strong><br>
                                        <?php echo Security::escape($application['standard_name']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Submitted:</strong><br>
                                        <?php echo date('d M Y H:i', strtotime($application['submitted_at'])); ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Company:</strong><br>
                                        <?php echo Security::escape($companyData['company_name'] ?? $application['applicant_company']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Contact:</strong><br>
                                        <?php echo Security::escape($companyData['contact_person'] ?? $application['applicant_contact']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Email:</strong><br>
                                        <?php echo Security::escape($companyData['email'] ?? $application['applicant_email']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Criteria Review -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Criteria Assessment Review</h3>
                        </div>
                        <div class="card-body">
                            <?php foreach ($criteria as $index => $criterion): ?>
                                <?php 
                                $response = $criteriaResponses[$criterion['id']] ?? null;
                                $meetsRequirement = $response['meets_requirement'] ?? 'no';
                                $notes = $response['notes'] ?? '';
                                $discussions = $criteriaDiscussions[$criterion['id']] ?? [];
                                $files = $criteriaFiles[$criterion['id']] ?? [];
                                ?>
                                <div class="mb-4 pb-4 border-bottom">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <span class="badge bg-primary"><?php echo $index + 1; ?></span>
                                        </div>
                                        <div class="flex-fill">
                                            <h4 class="mb-2">
                                                <?php echo Security::escape($criterion['name']); ?>
                                                <?php if ($criterion['ra'] === 'Yes'): ?>
                                                    <span class="badge bg-warning ms-2">Risk Assessment Required</span>
                                                <?php endif; ?>
                                            </h4>
                                            
                                            <?php if ($criterion['requirements']): ?>
                                                <div class="alert alert-info mb-3">
                                                    <strong>Requirements:</strong><br>
                                                    <?php echo nl2br(Security::escape($criterion['requirements'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <strong>Applicant's Response:</strong>
                                                    <?php
                                                    $colors = ['yes' => 'success', 'partial' => 'warning', 'no' => 'danger'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $colors[$meetsRequirement] ?? 'secondary'; ?>">
                                                        <?php echo ucfirst($meetsRequirement); ?>
                                                    </span>
                                                    <?php if ($notes): ?>
                                                        <div class="mt-2 text-muted">
                                                            <?php echo nl2br(Security::escape($notes)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Your Assessment:</label>
                                                    <select name="criteria_review[<?php echo $criterion['id']; ?>]" class="form-select">
                                                        <option value="meets">Meets Requirement</option>
                                                        <option value="partial">Partially Meets</option>
                                                        <option value="not_meets">Does Not Meet</option>
                                                        <option value="na">Not Applicable</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <!-- Files Section -->
                                            <div class="mt-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <h5 class="mb-0">Files</h5>
                                                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" 
                                                            onclick="document.getElementById('file-<?php echo $criterion['id']; ?>').click()">
                                                        <i class="ti ti-upload"></i> Upload File
                                                    </button>
                                                    <input type="file" 
                                                           id="file-<?php echo $criterion['id']; ?>" 
                                                           class="d-none criteria-file-input" 
                                                           data-criteria-id="<?php echo $criterion['id']; ?>"
                                                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                </div>
                                                
                                                <div id="files-list-<?php echo $criterion['id']; ?>" class="files-list">
                                                    <?php if (empty($files)): ?>
                                                        <p class="text-muted mb-0">No files uploaded yet.</p>
                                                    <?php else: ?>
                                                        <div class="list-group">
                                                            <?php foreach ($files as $file): ?>
                                                                <div class="list-group-item">
                                                                    <div class="row align-items-center">
                                                                        <div class="col-auto">
                                                                            <i class="ti ti-file text-muted" style="font-size: 1.5rem;"></i>
                                                                        </div>
                                                                        <div class="col">
                                                                            <div><?php echo Security::escape($file['original_name']); ?></div>
                                                                            <small class="text-muted">
                                                                                Uploaded by <?php echo Security::escape($file['contact_person']); ?> 
                                                                                <span class="badge bg-secondary"><?php echo ucfirst($file['role']); ?></span>
                                                                                on <?php echo date('d M Y H:i', strtotime($file['uploaded_at'])); ?>
                                                                            </small>
                                                                        </div>
                                                                        <div class="col-auto">
                                                                            <a href="/uploads/<?php echo $file['file_path']; ?>" 
                                                                               target="_blank" 
                                                                               class="btn btn-sm btn-primary">
                                                                                <i class="ti ti-download"></i>
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Discussion Section -->
                                            <div class="mt-3">
                                                <h5>Discussion</h5>
                                                <div class="discussion-container" id="discussion-<?php echo $criterion['id']; ?>">
                                                    <?php if (empty($discussions)): ?>
                                                        <p class="text-muted">No comments yet.</p>
                                                    <?php else: ?>
                                                        <?php foreach ($discussions as $discussion): ?>
                                                            <div class="comment mb-2 p-2 bg-light rounded">
                                                                <div class="d-flex justify-content-between">
                                                                    <strong class="<?php echo $discussion['role'] === 'certifier' ? 'text-primary' : 'text-success'; ?>">
                                                                        <?php echo Security::escape($discussion['contact_person']); ?>
                                                                        <span class="badge bg-secondary ms-1"><?php echo ucfirst($discussion['role']); ?></span>
                                                                    </strong>
                                                                    <small class="text-muted">
                                                                        <?php echo date('d M Y H:i', strtotime($discussion['created_at'])); ?>
                                                                    </small>
                                                                </div>
                                                                <div class="mt-1">
                                                                    <?php echo nl2br(Security::escape($discussion['message'])); ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Add Comment Form -->
                                                <div class="mt-2">
                                                    <div class="input-group">
                                                        <input type="text" 
                                                               class="form-control comment-input" 
                                                               placeholder="Add a comment..."
                                                               data-criteria-id="<?php echo $criterion['id']; ?>">
                                                        <button type="button" 
                                                                class="btn btn-primary btn-add-comment"
                                                                data-criteria-id="<?php echo $criterion['id']; ?>">
                                                            <i class="ti ti-send"></i> Send
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Review Decision -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Review Decision</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label required">Decision</label>
                                <div class="form-selectgroup">
                                    <label class="form-selectgroup-item">
                                        <input type="radio" name="decision" value="approve" class="form-selectgroup-input" required>
                                        <span class="form-selectgroup-label">
                                            <i class="ti ti-check text-success"></i> Approve
                                        </span>
                                    </label>
                                    <label class="form-selectgroup-item">
                                        <input type="radio" name="decision" value="reject" class="form-selectgroup-input">
                                        <span class="form-selectgroup-label">
                                            <i class="ti ti-x text-danger"></i> Reject
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required">Review Notes</label>
                                <textarea name="decision_notes" 
                                          class="form-control" 
                                          rows="4" 
                                          placeholder="Provide detailed feedback for the applicant..."
                                          required></textarea>
                                <small class="form-hint">
                                    These notes will be shared with the applicant. Be clear and constructive.
                                </small>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="btn-list justify-content-end">
                                <a href="/applications" class="btn">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-send"></i> Submit Review
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Documents -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Supporting Documents (<?php echo count($documents); ?>)</h3>
                        </div>
                        <?php if (empty($documents)): ?>
                            <div class="card-body text-center">
                                <i class="ti ti-files-off text-muted" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No documents uploaded</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($documents as $doc): ?>
                                    <div class="list-group-item">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <div class="text-truncate">
                                                    <?php echo Security::escape($doc['document_name']); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo ucfirst($doc['document_type']); ?>
                                                </small>
                                            </div>
                                            <div class="col-auto">
                                                <a href="/uploads/<?php echo $doc['file_path']; ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-body">
                            <h4>Review Guidelines</h4>
                            <ul class="ps-3">
                                <li>Review all criteria carefully</li>
                                <li>Check supporting documents</li>
                                <li>Provide clear feedback</li>
                                <li>Be objective and fair</li>
                                <li>Consider partial approval if applicable</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle comment submission
    document.querySelectorAll('.btn-add-comment').forEach(function(button) {
        button.addEventListener('click', function() {
            const criteriaId = this.getAttribute('data-criteria-id');
            const input = document.querySelector('.comment-input[data-criteria-id="' + criteriaId + '"]');
            const message = input.value.trim();
            
            if (!message) {
                alert('Please enter a comment');
                return;
            }
            
            // Disable button and show loading
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('ajax_action', 'add_comment');
            formData.append('criteria_id', criteriaId);
            formData.append('message', message);
            formData.append('csrf_token', '<?php echo $csrfToken; ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add comment to discussion
                    const discussionContainer = document.getElementById('discussion-' + criteriaId);
                    
                    // Remove "no comments" message if exists
                    const noComments = discussionContainer.querySelector('.text-muted');
                    if (noComments && noComments.textContent === 'No comments yet.') {
                        noComments.remove();
                    }
                    
                    // Create comment HTML
                    const commentDiv = document.createElement('div');
                    commentDiv.className = 'comment mb-2 p-2 bg-light rounded';
                    commentDiv.innerHTML = `
                        <div class="d-flex justify-content-between">
                            <strong class="${data.comment.role === 'certifier' ? 'text-primary' : 'text-success'}">
                                ${data.comment.author}
                                <span class="badge bg-secondary ms-1">${data.comment.role.charAt(0).toUpperCase() + data.comment.role.slice(1)}</span>
                            </strong>
                            <small class="text-muted">
                                ${data.comment.created_at}
                            </small>
                        </div>
                        <div class="mt-1">
                            ${data.comment.message}
                        </div>
                    `;
                    
                    discussionContainer.appendChild(commentDiv);
                    
                    // Clear input
                    input.value = '';
                } else {
                    alert(data.message || 'Failed to add comment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                // Re-enable button
                button.disabled = false;
                button.innerHTML = '<i class="ti ti-send"></i> Send';
            });
        });
    });
    
    // Handle Enter key in comment inputs
    document.querySelectorAll('.comment-input').forEach(function(input) {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const criteriaId = this.getAttribute('data-criteria-id');
                const button = document.querySelector('.btn-add-comment[data-criteria-id="' + criteriaId + '"]');
                button.click();
            }
        });
    });
    
    // Handle file uploads
    document.querySelectorAll('.criteria-file-input').forEach(function(input) {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            
            const criteriaId = this.getAttribute('data-criteria-id');
            const uploadButton = document.querySelector(`button[onclick*="file-${criteriaId}"]`);
            
            // Show loading state
            uploadButton.disabled = true;
            uploadButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Uploading...';
            
            // Create form data
            const formData = new FormData();
            formData.append('ajax_action', 'upload_file');
            formData.append('criteria_id', criteriaId);
            formData.append('file', file);
            formData.append('csrf_token', '<?php echo $csrfToken; ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update files list
                    const filesList = document.getElementById(`files-list-${criteriaId}`);
                    const noFiles = filesList.querySelector('.text-muted');
                    
                    if (noFiles && noFiles.textContent === 'No files uploaded yet.') {
                        filesList.innerHTML = '<div class="list-group"></div>';
                    }
                    
                    const listGroup = filesList.querySelector('.list-group');
                    const fileHtml = `
                        <div class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <i class="ti ti-file text-muted" style="font-size: 1.5rem;"></i>
                                </div>
                                <div class="col">
                                    <div>${data.file.name}</div>
                                    <small class="text-muted">
                                        Uploaded by ${data.file.uploader} 
                                        <span class="badge bg-secondary">Certifier</span>
                                        on ${data.file.timestamp}
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <a href="/uploads/${data.file.path}" 
                                       target="_blank" 
                                       class="btn btn-sm btn-primary">
                                        <i class="ti ti-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    listGroup.insertAdjacentHTML('afterbegin', fileHtml);
                    
                    // Clear file input
                    input.value = '';
                    
                    alert('File uploaded successfully!');
                } else {
                    alert(data.message || 'Failed to upload file');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during upload. Please try again.');
            })
            .finally(() => {
                // Reset button
                uploadButton.disabled = false;
                uploadButton.innerHTML = '<i class="ti ti-upload"></i> Upload File';
            });
        });
    });
});
</script>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>