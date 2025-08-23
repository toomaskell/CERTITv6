<?php
/**
 * CERTOLO - Dashboard Module
 * Main dashboard for logged in users
 */

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$userName = $_SESSION['user_name'];
$companyName = $_SESSION['company_name'];

try {
    $db = Database::getInstance();
    
    // Get dashboard statistics based on user role
    if ($userRole === ROLE_APPLICANT) {
        // Applicant statistics
        $stats = [];
        
        // Total applications
        $stmt = $db->query(
            "SELECT COUNT(*) as count FROM applications WHERE applicant_id = :user_id",
            ['user_id' => $userId]
        );
        $stats['total_applications'] = $stmt->fetch()['count'];
        
        // Applications by status
        $stmt = $db->query(
            "SELECT status, COUNT(*) as count 
             FROM applications 
             WHERE applicant_id = :user_id 
             GROUP BY status",
            ['user_id' => $userId]
        );
        $statusCounts = [];
        while ($row = $stmt->fetch()) {
            $statusCounts[$row['status']] = $row['count'];
        }
        
        // Active certificates
        $stmt = $db->query(
            "SELECT COUNT(*) as count 
             FROM certificates 
             WHERE applicant_id = :user_id 
             AND status = 'active' 
             AND expires_at > NOW()",
            ['user_id' => $userId]
        );
        $stats['active_certificates'] = $stmt->fetch()['count'];
        
        // Recent applications
        $stmt = $db->query(
            "SELECT a.*, s.name as standard_name, u.company_name as certifier_name
             FROM applications a
             JOIN standards s ON a.standard_id = s.id
             JOIN users u ON a.certifier_id = u.id
             WHERE a.applicant_id = :user_id
             ORDER BY a.created_at DESC
             LIMIT 5",
            ['user_id' => $userId]
        );
        $recentApplications = $stmt->fetchAll();
        
        // Upcoming certificate expirations
        $stmt = $db->query(
            "SELECT c.*, s.name as standard_name
             FROM certificates c
             JOIN standards s ON c.standard_id = s.id
             WHERE c.applicant_id = :user_id
             AND c.status = 'active'
             AND c.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 90 DAY)
             ORDER BY c.expires_at ASC
             LIMIT 5",
            ['user_id' => $userId]
        );
        $expiringCertificates = $stmt->fetchAll();
        
    } else {
        // Certifier statistics
        $stats = [];
        
        // Total standards
        $stmt = $db->query(
            "SELECT COUNT(*) as count FROM standards WHERE certifier_id = :user_id",
            ['user_id' => $userId]
        );
        $stats['total_standards'] = $stmt->fetch()['count'];
        
        // Total applications received
        $stmt = $db->query(
            "SELECT COUNT(*) as count FROM applications WHERE certifier_id = :user_id",
            ['user_id' => $userId]
        );
        $stats['total_applications'] = $stmt->fetch()['count'];
        
        // Pending reviews
        $stmt = $db->query(
            "SELECT COUNT(*) as count 
             FROM applications 
             WHERE certifier_id = :user_id 
             AND status IN ('submitted', 'under_review')",
            ['user_id' => $userId]
        );
        $stats['pending_reviews'] = $stmt->fetch()['count'];
        
        // Certificates issued
        $stmt = $db->query(
            "SELECT COUNT(*) as count FROM certificates WHERE certifier_id = :user_id",
            ['user_id' => $userId]
        );
        $stats['certificates_issued'] = $stmt->fetch()['count'];
        
        // Recent applications for review
        $stmt = $db->query(
            "SELECT a.*, s.name as standard_name, u.company_name as applicant_name
             FROM applications a
             JOIN standards s ON a.standard_id = s.id
             JOIN users u ON a.applicant_id = u.id
             WHERE a.certifier_id = :user_id
             AND a.status IN ('submitted', 'under_review')
             ORDER BY a.submitted_at DESC
             LIMIT 5",
            ['user_id' => $userId]
        );
        $pendingReviews = $stmt->fetchAll();
        
        // Recent activities
        $stmt = $db->query(
            "SELECT a.*, s.name as standard_name, u.company_name as applicant_name
             FROM applications a
             JOIN standards s ON a.standard_id = s.id
             JOIN users u ON a.applicant_id = u.id
             WHERE a.certifier_id = :user_id
             ORDER BY a.updated_at DESC
             LIMIT 10",
            ['user_id' => $userId]
        );
        $recentActivities = $stmt->fetchAll();
    }
    
    // Get recent notifications
    $stmt = $db->query(
        "SELECT * FROM notifications 
         WHERE user_id = :user_id 
         AND is_read = 0 
         ORDER BY created_at DESC 
         LIMIT 5",
        ['user_id' => $userId]
    );
    $notifications = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Dashboard error: ' . $e->getMessage());
}

// Set page title
$pageTitle = 'Dashboard';

// Include header
include INCLUDES_PATH . 'header.php';
?>

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Overview</div>
                <h2 class="page-title">
                    Welcome back, <?php echo Security::escape($userName); ?>!
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <?php if ($userRole === ROLE_APPLICANT): ?>
                        <a href="/standards" class="btn btn-primary">
                            <i class="ti ti-plus"></i> New Application
                        </a>
                    <?php else: ?>
                        <a href="/standards/create" class="btn btn-primary">
                            <i class="ti ti-plus"></i> Create Standard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <?php if ($userRole === ROLE_APPLICANT): ?>
            <!-- Applicant Dashboard -->
            
            <!-- Statistics Cards -->
            <div class="row row-deck row-cards mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Total Applications</div>
                            </div>
                            <div class="h1 mb-3"><?php echo $stats['total_applications']; ?></div>
                            <div class="d-flex mb-2">
                                <div>All time submissions</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Active Certificates</div>
                            </div>
                            <div class="h1 mb-3 text-success"><?php echo $stats['active_certificates']; ?></div>
                            <div class="d-flex mb-2">
                                <div>Currently valid</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Pending Review</div>
                            </div>
                            <div class="h1 mb-3 text-warning"><?php echo $statusCounts['under_review'] ?? 0; ?></div>
                            <div class="d-flex mb-2">
                                <div>Awaiting decision</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Draft Applications</div>
                            </div>
                            <div class="h1 mb-3"><?php echo $statusCounts['draft'] ?? 0; ?></div>
                            <div class="d-flex mb-2">
                                <div>Not yet submitted</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row row-deck row-cards">
                <!-- Recent Applications -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Applications</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Standard</th>
                                        <th>Certifier</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentApplications)): ?>
                                        <?php foreach ($recentApplications as $app): ?>
                                            <tr>
                                                <td><?php echo Security::escape($app['standard_name']); ?></td>
                                                <td class="text-muted"><?php echo Security::escape($app['certifier_name']); ?></td>
                                                <td>
                                                    <?php
                                                    $statusBadges = [
                                                        'draft' => 'bg-secondary',
                                                        'submitted' => 'bg-blue',
                                                        'under_review' => 'bg-yellow',
                                                        'approved' => 'bg-green',
                                                        'rejected' => 'bg-red',
                                                        'issued' => 'bg-purple'
                                                    ];
                                                    $badgeClass = $statusBadges[$app['status']] ?? 'bg-secondary';
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="text-muted">
                                                    <?php echo $app['submitted_at'] ? date('d M Y', strtotime($app['submitted_at'])) : 'Not submitted'; ?>
                                                </td>
                                                <td class="text-end">
                                                    <a href="/applications/view/<?php echo $app['id']; ?>" class="btn btn-sm">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                No applications yet. <a href="/standards">Browse standards</a> to get started.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($recentApplications)): ?>
                            <div class="card-footer d-flex align-items-center">
                                <a href="/applications" class="btn btn-sm">View all applications</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Notifications & Expiring Certificates -->
                <div class="col-lg-4">
                    <?php if (!empty($notifications)): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">Recent Notifications</h3>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <div class="text-truncate">
                                                    <strong><?php echo Security::escape($notification['title']); ?></strong>
                                                </div>
                                                <div class="text-muted small text-truncate">
                                                    <?php echo Security::escape($notification['message']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($expiringCertificates)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Expiring Certificates</h3>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($expiringCertificates as $cert): ?>
                                    <div class="list-group-item">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <div class="text-truncate">
                                                    <strong><?php echo Security::escape($cert['standard_name']); ?></strong>
                                                </div>
                                                <div class="text-muted small">
                                                    Expires: <?php echo date('d M Y', strtotime($cert['expires_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <a href="/certificates/view/<?php echo $cert['id']; ?>" class="btn btn-sm">
                                                    View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Certifier Dashboard -->
            
            <!-- Statistics Cards -->
            <div class="row row-deck row-cards mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Active Standards</div>
                            </div>
                            <div class="h1 mb-3"><?php echo $stats['total_standards']; ?></div>
                            <div class="d-flex mb-2">
                                <div>Available for certification</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Total Applications</div>
                            </div>
                            <div class="h1 mb-3"><?php echo $stats['total_applications']; ?></div>
                            <div class="d-flex mb-2">
                                <div>All time received</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Pending Reviews</div>
                            </div>
                            <div class="h1 mb-3 text-warning"><?php echo $stats['pending_reviews']; ?></div>
                            <div class="d-flex mb-2">
                                <div>Awaiting your review</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Certificates Issued</div>
                            </div>
                            <div class="h1 mb-3 text-success"><?php echo $stats['certificates_issued']; ?></div>
                            <div class="d-flex mb-2">
                                <div>Total issued</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row row-deck row-cards">
                <!-- Pending Reviews -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Applications Pending Review</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Application #</th>
                                        <th>Applicant</th>
                                        <th>Standard</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($pendingReviews)): ?>
                                        <?php foreach ($pendingReviews as $app): ?>
                                            <tr>
                                                <td><?php echo Security::escape($app['application_number'] ?? 'APP-' . str_pad($app['id'], 6, '0', STR_PAD_LEFT)); ?></td>
                                                <td>
                                                    <div><?php echo Security::escape($app['applicant_name']); ?></div>
                                                </td>
                                                <td><?php echo Security::escape($app['standard_name']); ?></td>
                                                <td class="text-muted">
                                                    <?php echo date('d M Y', strtotime($app['submitted_at'])); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-yellow">
                                                        <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="/applications/review/<?php echo $app['id']; ?>" class="btn btn-sm btn-primary">
                                                        Review
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                No applications pending review.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($stats['pending_reviews'] > 5): ?>
                            <div class="card-footer d-flex align-items-center">
                                <a href="/applications?status=under_review" class="btn btn-sm">View all pending reviews</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>