<?php
/**
 * CERTOLO - View Standard
 * Display standard details and criteria
 */

// Get standard ID
$standardId = $id ?? $_GET['id'] ?? null;

if (!$standardId) {
    header('Location: /standards');
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get standard details
    $stmt = $db->query(
        "SELECT s.*, u.company_name as certifier_name, u.email as certifier_email,
                u.phone as certifier_phone, u.city as certifier_city, u.country as certifier_country
         FROM standards s
         JOIN users u ON s.certifier_id = u.id
         WHERE s.id = :id",
        ['id' => $standardId]
    );
    
    $standard = $stmt->fetch();
    
    if (!$standard) {
        header('HTTP/1.0 404 Not Found');
        exit('Standard not found');
    }
    
    // Check access - if inactive, only certifier can view
    if ($standard['status'] === 'inactive' && 
        ($userRole !== ROLE_CERTIFIER || $standard['certifier_id'] != $userId)) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied');
    }
    
    // Get criteria
    $criteriaStmt = $db->query(
        "SELECT * FROM criterias 
         WHERE standard_id = :standard_id 
         AND status = 'active'
         ORDER BY sort_order ASC, id ASC",
        ['standard_id' => $standardId]
    );
    
    $criteria = $criteriaStmt->fetchAll();
    
    // Get application count
    $appStmt = $db->query(
        "SELECT COUNT(*) as count FROM applications WHERE standard_id = :id",
        ['id' => $standardId]
    );
    $applicationCount = $appStmt->fetch()['count'];
    
} catch (Exception $e) {
    error_log('View standard error: ' . $e->getMessage());
    header('Location: /standards');
    exit;
}

// Set page title
$pageTitle = $standard['name'];

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
                        <li class="breadcrumb-item active"><?php echo Security::escape($standard['name']); ?></li>
                    </ol>
                </nav>
                <h2 class="page-title"><?php echo Security::escape($standard['name']); ?></h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <?php if ($userRole === ROLE_CERTIFIER && $standard['certifier_id'] == $userId): ?>
                        <a href="/standards/edit/<?php echo $standardId; ?>" class="btn">
                            <i class="ti ti-edit"></i> Edit
                        </a>
                        <a href="/standards/criteria/<?php echo $standardId; ?>" class="btn">
                            <i class="ti ti-list"></i> Manage Criteria
                        </a>
                    <?php elseif ($userRole === ROLE_APPLICANT && $standard['status'] === 'active'): ?>
                        <a href="/applications/create?standard=<?php echo $standardId; ?>" class="btn btn-primary">
                            <i class="ti ti-file-plus"></i> Apply for Certification
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="row">
            <!-- Standard Details -->
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Standard Information</h3>
                        <?php if ($standard['status'] === 'active'): ?>
                            <div class="card-actions">
                                <span class="badge bg-green">Active</span>
                            </div>
                        <?php else: ?>
                            <div class="card-actions">
                                <span class="badge bg-secondary">Inactive</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($standard['code']): ?>
                            <div class="mb-3">
                                <strong>Code:</strong> <?php echo Security::escape($standard['code']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <strong>Type:</strong> <?php echo Security::escape($standard['type']); ?>
                        </div>
                        
                        <?php if ($standard['description']): ?>
                            <div class="mb-3">
                                <strong>Description:</strong>
                                <p class="text-muted mb-0"><?php echo nl2br(Security::escape($standard['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($standard['requirements']): ?>
                            <div class="mb-3">
                                <strong>Requirements:</strong>
                                <p class="text-muted mb-0"><?php echo nl2br(Security::escape($standard['requirements'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="text-muted small">Validity Period</div>
                                    <div class="h3 m-0"><?php echo $standard['validity_months']; ?> months</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="text-muted small">Certification Fee</div>
                                    <div class="h3 m-0">€<?php echo number_format($standard['price'], 2); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="text-muted small">Applications</div>
                                    <div class="h3 m-0"><?php echo $applicationCount; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($standard['file_path']): ?>
                            <hr>
                            <div>
                                <strong>Standard Document:</strong>
                                <a href="/uploads/<?php echo $standard['file_path']; ?>" target="_blank" class="btn btn-sm">
                                    <i class="ti ti-download"></i> Download
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Criteria List -->
                <?php if (!empty($criteria)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Certification Criteria (<?php echo count($criteria); ?>)</h3>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($criteria as $index => $criterion): ?>
                                <div class="list-group-item">
                                    <div class="row align-items-start">
                                        <div class="col-auto">
                                            <span class="badge bg-primary"><?php echo $index + 1; ?></span>
                                        </div>
                                        <div class="col">
                                            <div class="mb-2">
                                                <strong><?php echo Security::escape($criterion['name']); ?></strong>
                                                <?php if ($criterion['aspect']): ?>
                                                    <span class="badge bg-info ms-2"><?php echo ASPECTS[$criterion['aspect']] ?? $criterion['aspect']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($criterion['ra'] === 'Yes'): ?>
                                                    <span class="badge bg-warning ms-1">Risk Assessment Required</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($criterion['description']): ?>
                                                <p class="text-muted mb-2"><?php echo nl2br(Security::escape($criterion['description'])); ?></p>
                                            <?php endif; ?>
                                            <?php if ($criterion['requirements']): ?>
                                                <div class="text-muted small">
                                                    <strong>Requirements:</strong> <?php echo nl2br(Security::escape($criterion['requirements'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="ti ti-list-check" style="font-size: 3rem; color: #ccc;"></i>
                            <h3 class="mt-3">No Criteria Defined</h3>
                            <p class="text-muted">This standard doesn't have any criteria defined yet.</p>
                            <?php if ($userRole === ROLE_CERTIFIER && $standard['certifier_id'] == $userId): ?>
                                <a href="/standards/criteria/<?php echo $standardId; ?>" class="btn btn-primary">
                                    <i class="ti ti-plus"></i> Add Criteria
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Certifier Information -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Certification Body</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong><?php echo Security::escape($standard['certifier_name']); ?></strong>
                        </div>
                        
                        <?php if ($standard['certifier_email']): ?>
                            <div class="mb-2">
                                <i class="ti ti-mail me-2"></i>
                                <a href="mailto:<?php echo Security::escape($standard['certifier_email']); ?>">
                                    <?php echo Security::escape($standard['certifier_email']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($standard['certifier_phone']): ?>
                            <div class="mb-2">
                                <i class="ti ti-phone me-2"></i>
                                <?php echo Security::escape($standard['certifier_phone']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($standard['certifier_city'] || $standard['certifier_country']): ?>
                            <div class="mb-2">
                                <i class="ti ti-map-pin me-2"></i>
                                <?php 
                                echo Security::escape($standard['certifier_city']);
                                if ($standard['certifier_city'] && $standard['certifier_country']) echo ', ';
                                echo Security::escape($standard['certifier_country']); 
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($userRole === ROLE_APPLICANT && $standard['status'] === 'active'): ?>
                        <div class="card-footer">
                            <a href="/applications/create?standard=<?php echo $standardId; ?>" class="btn btn-primary w-100">
                                <i class="ti ti-file-plus"></i> Apply Now
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <?php if ($userRole === ROLE_CERTIFIER && $standard['certifier_id'] == $userId): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="/standards/edit/<?php echo $standardId; ?>" class="list-group-item list-group-item-action">
                                <i class="ti ti-edit me-2"></i> Edit Standard
                            </a>
                            <a href="/standards/criteria/<?php echo $standardId; ?>" class="list-group-item list-group-item-action">
                                <i class="ti ti-list-check me-2"></i> Manage Criteria
                            </a>
                            <a href="/applications?standard=<?php echo $standardId; ?>" class="list-group-item list-group-item-action">
                                <i class="ti ti-file-text me-2"></i> View Applications
                            </a>
                            <a href="/standards/delete/<?php echo $standardId; ?>" class="list-group-item list-group-item-action text-danger" 
                               data-confirm="Are you sure you want to delete this standard?">
                                <i class="ti ti-trash me-2"></i> Delete Standard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>