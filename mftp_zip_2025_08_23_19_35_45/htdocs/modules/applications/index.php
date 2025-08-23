<?php
/**
 * CERTOLO - Applications Module
 * Manage certification applications
 */

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Get action from URL
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// Route to appropriate action
switch ($action) {
    case 'create':
        if ($userRole !== ROLE_APPLICANT) {
            header('HTTP/1.0 403 Forbidden');
            exit('Only applicants can create applications');
        }
        require_once 'create.php';
        break;
        
    case 'edit':
        require_once 'edit.php';
        break;
        
    case 'view':
        require_once 'view.php';
        break;
        
    case 'review':
        if ($userRole !== ROLE_CERTIFIER) {
            header('HTTP/1.0 403 Forbidden');
            exit('Only certifiers can review applications');
        }
        require_once 'review.php';
        break;
        
    case 'submit':
        if ($userRole !== ROLE_APPLICANT) {
            header('HTTP/1.0 403 Forbidden');
            exit('Access denied');
        }
        require_once 'submit.php';
        break;
        
    default:
        // List applications
        listApplications();
        break;
}

function listApplications() {
    global $userId, $userRole;
    
    try {
        $db = Database::getInstance();
        
        // Get filter parameters
        $status = $_GET['status'] ?? '';
        $standard = $_GET['standard'] ?? '';
        $search = $_GET['search'] ?? '';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        
        // Build query based on user role
        if ($userRole === ROLE_APPLICANT) {
            $where = "WHERE a.applicant_id = :user_id";
            $params = ['user_id' => $userId];
        } else {
            // Certifiers see applications for their standards
            $where = "WHERE a.certifier_id = :user_id";
            $params = ['user_id' => $userId];
        }
        
        // Add filters
        if ($status) {
            $where .= " AND a.status = :status";
            $params['status'] = $status;
        }
        
        if ($standard) {
            $where .= " AND a.standard_id = :standard";
            $params['standard'] = $standard;
        }
        
        if ($search) {
            $where .= " AND (a.application_number LIKE :search OR u_app.company_name LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        
        // Get total count
        $countStmt = $db->query(
            "SELECT COUNT(*) as total 
             FROM applications a 
             $where",
            $params
        );
        $totalItems = $countStmt->fetch()['total'];
        $totalPages = ceil($totalItems / $limit);
        
        // Get applications
        $stmt = $db->query(
            "SELECT a.*, 
                    s.name as standard_name, s.type as standard_type,
                    u_app.company_name as applicant_name, u_app.email as applicant_email,
                    u_cert.company_name as certifier_name
             FROM applications a
             JOIN standards s ON a.standard_id = s.id
             JOIN users u_app ON a.applicant_id = u_app.id
             JOIN users u_cert ON a.certifier_id = u_cert.id
             $where
             ORDER BY a.created_at DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $limit, 'offset' => $offset])
        );
        
        $applications = $stmt->fetchAll();
        
        // Get status counts
        $statusStmt = $db->query(
            "SELECT status, COUNT(*) as count 
             FROM applications a
             $where
             GROUP BY status",
            $params
        );
        
        $statusCounts = [];
        while ($row = $statusStmt->fetch()) {
            $statusCounts[$row['status']] = $row['count'];
        }
        
    } catch (Exception $e) {
        error_log('Applications list error: ' . $e->getMessage());
        $applications = [];
        $totalPages = 0;
        $statusCounts = [];
    }
    
    // Set page title
    $pageTitle = 'Applications';
    
    // Include header
    include INCLUDES_PATH . 'header.php';
    ?>

    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Certification</div>
                    <h2 class="page-title">Applications</h2>
                </div>
                <?php if ($userRole === ROLE_APPLICANT): ?>
                <div class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        <a href="/standards" class="btn btn-primary">
                            <i class="ti ti-plus"></i> New Application
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <!-- Status Cards -->
            <div class="row row-cards mb-3">
                <div class="col-sm-6 col-lg-2">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-secondary text-white avatar">
                                        <i class="ti ti-file-text"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="font-weight-medium">Draft</div>
                                    <div class="text-muted"><?php echo $statusCounts['draft'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-2">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-blue text-white avatar">
                                        <i class="ti ti-send"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="font-weight-medium">Submitted</div>
                                    <div class="text-muted"><?php echo $statusCounts['submitted'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-2">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-yellow text-white avatar">
                                        <i class="ti ti-clock"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="font-weight-medium">Under Review</div>
                                    <div class="text-muted"><?php echo $statusCounts['under_review'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-2">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-green text-white avatar">
                                        <i class="ti ti-check"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="font-weight-medium">Approved</div>
                                    <div class="text-muted"><?php echo $statusCounts['approved'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-2">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-red text-white avatar">
                                        <i class="ti ti-x"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="font-weight-medium">Rejected</div>
                                    <div class="text-muted"><?php echo $statusCounts['rejected'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-2">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <span class="bg-purple text-white avatar">
                                        <i class="ti ti-award"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="font-weight-medium">Issued</div>
                                    <div class="text-muted"><?php echo $statusCounts['issued'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="/applications" class="row g-2">
                        <div class="col-md-3">
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Search..."
                                   value="<?php echo Security::escape($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="submitted" <?php echo $status === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="under_review" <?php echo $status === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="issued" <?php echo $status === 'issued' ? 'selected' : ''; ?>>Issued</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ti ti-search"></i> Filter
                            </button>
                        </div>
                        <?php if ($search || $status): ?>
                        <div class="col-md-2">
                            <a href="/applications" class="btn btn-link w-100">Clear</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Applications List -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Application #</th>
                                <th>Standard</th>
                                <?php if ($userRole === ROLE_CERTIFIER): ?>
                                <th>Applicant</th>
                                <?php else: ?>
                                <th>Certifier</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Date</th>
                                <th width="100"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="ti ti-file-off" style="font-size: 3rem; color: #ccc;"></i>
                                        <p class="mt-3 mb-0">No applications found</p>
                                        <?php if ($userRole === ROLE_APPLICANT): ?>
                                            <a href="/standards" class="btn btn-primary mt-3">
                                                <i class="ti ti-plus"></i> Browse Standards
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo Security::escape($app['application_number'] ?? 'APP-' . str_pad($app['id'], 6, '0', STR_PAD_LEFT)); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo Security::escape($app['standard_name']); ?></div>
                                            <small class="text-muted"><?php echo Security::escape($app['standard_type']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($userRole === ROLE_CERTIFIER): ?>
                                                <div><?php echo Security::escape($app['applicant_name']); ?></div>
                                                <small class="text-muted"><?php echo Security::escape($app['applicant_email']); ?></small>
                                            <?php else: ?>
                                                <div><?php echo Security::escape($app['certifier_name']); ?></div>
                                            <?php endif; ?>
                                        </td>
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
                                            <?php 
                                            if ($app['submitted_at']) {
                                                echo date('d M Y', strtotime($app['submitted_at']));
                                            } else {
                                                echo date('d M Y', strtotime($app['created_at']));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-list flex-nowrap">
                                                <a href="/applications/view/<?php echo $app['id']; ?>" class="btn btn-sm">
                                                    <i class="ti ti-eye"></i> View
                                                </a>
                                                <?php if ($userRole === ROLE_APPLICANT && $app['status'] === 'draft'): ?>
                                                    <a href="/applications/edit/<?php echo $app['id']; ?>" class="btn btn-sm">
                                                        <i class="ti ti-edit"></i>
                                                    </a>
                                                <?php elseif ($userRole === ROLE_CERTIFIER && in_array($app['status'], ['submitted', 'under_review'])): ?>
                                                    <a href="/applications/review/<?php echo $app['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="ti ti-clipboard-check"></i> Review
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer d-flex align-items-center">
                        <p class="m-0 text-muted">
                            Showing <span><?php echo ($offset + 1); ?></span> to 
                            <span><?php echo min($offset + $limit, $totalItems); ?></span> of 
                            <span><?php echo $totalItems; ?></span> entries
                        </p>
                        <ul class="pagination m-0 ms-auto">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="ti ti-chevron-left"></i> prev
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                        next <i class="ti ti-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    // Include footer
    include INCLUDES_PATH . 'footer.php';
}