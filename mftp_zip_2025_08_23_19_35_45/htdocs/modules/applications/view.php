<?php
/**
 * CERTOLO - View Application
 * Display application details
 */

// Get application ID
$applicationId = $id ?? $_GET['id'] ?? null;

if (!$applicationId) {
    header('Location: /applications');
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get application details with related data
    $stmt = $db->query(
        "SELECT a.*, 
                s.name as standard_name, s.type as standard_type, 
                s.validity_months, s.price,
                u_app.company_name as applicant_company, u_app.email as applicant_email,
                u_app.contact_person as applicant_contact,
                u_cert.company_name as certifier_name, u_cert.email as certifier_email
         FROM applications a
         JOIN standards s ON a.standard_id = s.id
         JOIN users u_app ON a.applicant_id = u_app.id
         JOIN users u_cert ON a.certifier_id = u_cert.id
         WHERE a.id = :id",
        ['id' => $applicationId]
    );
    
    $application = $stmt->fetch();
    
    if (!$application) {
        header('HTTP/1.0 404 Not Found');
        exit('Application not found');
    }
    
    // Check access rights
    if ($userRole === ROLE_APPLICANT && $application['applicant_id'] != $userId) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied');
    } elseif ($userRole === ROLE_CERTIFIER && $application['certifier_id'] != $userId) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied');
    }
    
    // Parse JSON data
    $applicationData = json_decode($application['application_data'], true) ?? [];
    $companyData = json_decode($application['company_data'], true) ?? [];
    $criteriaResponses = $applicationData['criteria'] ?? [];
    
    // Get criteria details
    $criteriaStmt = $db->query(
        "SELECT * FROM criterias 
         WHERE standard_id = :standard_id 
         ORDER BY sort_order ASC, id ASC",
        ['standard_id' => $application['standard_id']]
    );
    
    $criteria = $criteriaStmt->fetchAll();
    
    // Get uploaded documents
    $docsStmt = $db->query(
        "SELECT * FROM application_documents 
         WHERE application_id = :app_id 
         ORDER BY uploaded_at DESC",
        ['app_id' => $applicationId]
    );
    
    $documents = $docsStmt->fetchAll();
    
    // Get discussion/comments
    $discussionStmt = $db->query(
        "SELECT d.*, u.contact_person as user_name, u.role as user_role
         FROM application_discussions d
         JOIN users u ON d.user_id = u.id
         WHERE d.application_id = :app_id 
         ORDER BY d.created_at DESC",
        ['app_id' => $applicationId]
    );
    
    $discussions = $discussionStmt->fetchAll();
    
} catch (Exception $e) {
    error_log('View application error: ' . $e->getMessage());
    header('Location: /applications');
    exit;
}

// Status badge colors
$statusBadges = [
    'draft' => 'bg-secondary',
    'submitted' => 'bg-blue',
    'under_review' => 'bg-yellow',
    'approved' => 'bg-green',
    'rejected' => 'bg-red',
    'issued' => 'bg-purple'
];

// Set page title
$pageTitle = 'Application #' . $application['application_number'];

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
                        <li class="breadcrumb-item active"><?php echo Security::escape($application['application_number']); ?></li>
                    </ol>
                </nav>
                <h2 class="page-title">
                    Application Details
                    <span class="badge <?php echo $statusBadges[$application['status']] ?? 'bg-secondary'; ?> ms-2">
                        <?php echo ucwords(str_replace('_', ' ', $application['status'])); ?>
                    </span>
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <?php if ($userRole === ROLE_APPLICANT && $application['status'] === 'draft'): ?>
                        <a href="/applications/edit/<?php echo $applicationId; ?>" class="btn btn-primary">
                            <i class="ti ti-edit"></i> Edit Application
                        </a>
                    <?php elseif ($userRole === ROLE_CERTIFIER && in_array($application['status'], ['submitted', 'under_review'])): ?>
                        <a href="/applications/review/<?php echo $applicationId; ?>" class="btn btn-primary">
                            <i class="ti ti-clipboard-check"></i> Review Application
                        </a>
                    <?php endif; ?>
                    
                    <button onclick="window.print()" class="btn">
                        <i class="ti ti-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Application Info -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Application Information</h3>
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
                                    <strong>Type:</strong><br>
                                    <?php echo Security::escape($application['standard_type']); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Created:</strong><br>
                                    <?php echo date('d M Y H:i', strtotime($application['created_at'])); ?>
                                </div>
                                <?php if ($application['submitted_at']): ?>
                                <div class="mb-3">
                                    <strong>Submitted:</strong><br>
                                    <?php echo date('d M Y H:i', strtotime($application['submitted_at'])); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($application['reviewed_at']): ?>
                                <div class="mb-3">
                                    <strong>Reviewed:</strong><br>
                                    <?php echo date('d M Y H:i', strtotime($application['reviewed_at'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Company Information -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Company Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Company Name:</strong><br>
                                    <?php echo Security::escape($companyData['company_name'] ?? $application['applicant_company']); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Contact Person:</strong><br>
                                    <?php echo Security::escape($companyData['contact_person'] ?? $application['applicant_contact']); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Email:</strong><br>
                                    <?php echo Security::escape($companyData['email'] ?? $application['applicant_email']); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Phone:</strong><br>
                                    <?php echo Security::escape($companyData['phone'] ?? '-'); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Address:</strong><br>
                                    <?php echo Security::escape($companyData['address'] ?? '-'); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>City, Country:</strong><br>
                                    <?php 
                                    echo Security::escape($companyData['city'] ?? '');
                                    if (!empty($companyData['city']) && !empty($companyData['country'])) echo ', ';
                                    echo Security::escape($companyData['country'] ?? '');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Criteria Assessment -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Criteria Assessment</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($criteria as $index => $criterion): ?>
                            <?php 
                            $response = $criteriaResponses[$criterion['id']] ?? null;
                            $meetsRequirement = $response['meets_requirement'] ?? 'no';
                            $notes = $response['notes'] ?? '';
                            
                            $responseColors = [
                                'yes' => 'success',
                                'partial' => 'warning',
                                'no' => 'danger'
                            ];
                            $responseIcons = [
                                'yes' => 'check',
                                'partial' => 'alert-circle',
                                'no' => 'x'
                            ];
                            ?>
                            <div class="mb-4 pb-4 border-bottom">
                                <div class="d-flex align-items-start">
                                    <div class="me-3">
                                        <span class="badge bg-primary"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="flex-fill">
                                        <h4 class="mb-2"><?php echo Security::escape($criterion['name']); ?></h4>
                                        
                                        <?php if ($criterion['description']): ?>
                                            <p class="text-muted"><?php echo nl2br(Security::escape($criterion['description'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <strong>Response:</strong>
                                            <span class="badge bg-<?php echo $responseColors[$meetsRequirement] ?? 'secondary'; ?> ms-2">
                                                <i class="ti ti-<?php echo $responseIcons[$meetsRequirement] ?? 'question-mark'; ?>"></i>
                                                <?php echo ucfirst($meetsRequirement); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($notes): ?>
                                            <div class="mt-2">
                                                <strong>Notes:</strong><br>
                                                <div class="text-muted"><?php echo nl2br(Security::escape($notes)); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($companyData['additional_info'])): ?>
                            <div class="mt-3">
                                <strong>Additional Information:</strong><br>
                                <div class="text-muted"><?php echo nl2br(Security::escape($companyData['additional_info'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Documents -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Supporting Documents (<?php echo count($documents); ?>)</h3>
                    </div>
                    <?php if (empty($documents)): ?>
                        <div class="card-body text-center py-5">
                            <i class="ti ti-files-off" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 mb-0">No documents uploaded yet</p>
                            <?php if ($userRole === ROLE_APPLICANT && $application['status'] === 'draft'): ?>
                                <a href="/applications/edit/<?php echo $applicationId; ?>" class="btn btn-primary mt-3">
                                    <i class="ti ti-upload"></i> Upload Documents
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($documents as $doc): ?>
                                <div class="list-group-item">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <i class="ti ti-file text-muted" style="font-size: 2rem;"></i>
                                        </div>
                                        <div class="col">
                                            <div><?php echo Security::escape($doc['document_name']); ?></div>
                                            <small class="text-muted">
                                                Uploaded <?php echo date('d M Y', strtotime($doc['uploaded_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="col-auto">
                                            <a href="/uploads/<?php echo $doc['file_path']; ?>" 
                                               target="_blank" 
                                               class="btn btn-sm">
                                                <i class="ti ti-download"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Status Card -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Application Status</h3>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <span class="badge <?php echo $statusBadges[$application['status']] ?? 'bg-secondary'; ?>" style="font-size: 1.2rem; padding: 0.5rem 1rem;">
                                <?php echo ucwords(str_replace('_', ' ', $application['status'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($userRole === ROLE_APPLICANT && $application['status'] === 'draft'): ?>
                            <form method="POST" action="/applications/submit/<?php echo $applicationId; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ti ti-send"></i> Submit for Review
                                </button>
                            </form>
                            <small class="text-muted mt-2 d-block">
                                Make sure all documents are uploaded before submitting
                            </small>
                        <?php endif; ?>
                        
                        <?php if ($application['status'] === 'approved' && $userRole === ROLE_CERTIFIER): ?>
                            <a href="/certificates/issue/<?php echo $applicationId; ?>" class="btn btn-success w-100">
                                <i class="ti ti-award"></i> Issue Certificate
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($application['status'] === 'issued'): ?>
                            <a href="/certificates/view/<?php echo $applicationId; ?>" class="btn btn-purple w-100">
                                <i class="ti ti-certificate"></i> View Certificate
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Certifier Info -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Certification Body</h3>
                    </div>
                    <div class="card-body">
                        <strong><?php echo Security::escape($application['certifier_name']); ?></strong><br>
                        <a href="mailto:<?php echo Security::escape($application['certifier_email']); ?>">
                            <?php echo Security::escape($application['certifier_email']); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Timeline -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Timeline</h3>
                    </div>
                    <div class="card-body">
                        <div class="status-timeline">
                            <div class="status-timeline-item completed">
                                <strong>Created</strong><br>
                                <small class="text-muted"><?php echo date('d M Y', strtotime($application['created_at'])); ?></small>
                            </div>
                            
                            <?php if ($application['submitted_at']): ?>
                            <div class="status-timeline-item completed">
                                <strong>Submitted</strong><br>
                                <small class="text-muted"><?php echo date('d M Y', strtotime($application['submitted_at'])); ?></small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($application['reviewed_at']): ?>
                            <div class="status-timeline-item completed">
                                <strong>Reviewed</strong><br>
                                <small class="text-muted"><?php echo date('d M Y', strtotime($application['reviewed_at'])); ?></small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($application['status'] === 'approved'): ?>
                            <div class="status-timeline-item completed">
                                <strong>Approved</strong><br>
                                <small class="text-muted"><?php echo date('d M Y', strtotime($application['approved_at'])); ?></small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($application['status'] === 'issued'): ?>
                            <div class="status-timeline-item completed">
                                <strong>Certificate Issued</strong><br>
                                <small class="text-muted"><?php echo date('d M Y', strtotime($application['issued_at'])); ?></small>
                            </div>
                            <?php endif; ?>
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