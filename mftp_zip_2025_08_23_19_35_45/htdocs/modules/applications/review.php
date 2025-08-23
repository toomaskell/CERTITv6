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
    
    // Handle review submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>