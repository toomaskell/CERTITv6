<?php
/**
 * CERTOLO - Standards Module
 * Main standards listing and management
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
        if ($userRole !== ROLE_CERTIFIER) {
            header('HTTP/1.0 403 Forbidden');
            exit('Access denied');
        }
        require_once 'create.php';
        break;
        
    case 'edit':
        if ($userRole !== ROLE_CERTIFIER) {
            header('HTTP/1.0 403 Forbidden');
            exit('Access denied');
        }
        require_once 'edit.php';
        break;
        
    case 'view':
        require_once 'view.php';
        break;
        
    case 'criteria':
        if ($userRole !== ROLE_CERTIFIER) {
            header('HTTP/1.0 403 Forbidden');
            exit('Access denied');
        }
        require_once 'criteria.php';
        break;
        
    case 'delete':
        if ($userRole !== ROLE_CERTIFIER) {
            header('HTTP/1.0 403 Forbidden');
            exit('Access denied');
        }
        require_once 'delete.php';
        break;
        
    default:
        // List standards
        listStandards();
        break;
}

function listStandards() {
    global $userId, $userRole;
    
    try {
        $db = Database::getInstance();
        
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $status = $_GET['status'] ?? 'active';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        
        // Build query based on user role
        if ($userRole === ROLE_CERTIFIER) {
            // Certifiers see only their own standards
            $where = "WHERE s.certifier_id = :user_id";
            $params = ['user_id' => $userId];
        } else {
            // Applicants see all active standards
            $where = "WHERE s.status = 'active'";
            $params = [];
        }
        
        // Add filters
        if ($search) {
            $where .= " AND (s.name LIKE :search OR s.description LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        
        if ($type) {
            $where .= " AND s.type = :type";
            $params['type'] = $type;
        }
        
        if ($status && $userRole === ROLE_CERTIFIER) {
            $where .= " AND s.status = :status";
            $params['status'] = $status;
        }
        
        // Get total count
        $countStmt = $db->query(
            "SELECT COUNT(*) as total FROM standards s $where",
            $params
        );
        $totalItems = $countStmt->fetch()['total'];
        $totalPages = ceil($totalItems / $limit);
        
        // Get standards
        $stmt = $db->query(
            "SELECT s.*, u.company_name as certifier_name,
                    (SELECT COUNT(*) FROM criterias WHERE standard_id = s.id) as criteria_count,
                    (SELECT COUNT(*) FROM applications WHERE standard_id = s.id) as application_count
             FROM standards s
             JOIN users u ON s.certifier_id = u.id
             $where
             ORDER BY s.created_at DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $limit, 'offset' => $offset])
        );
        
        $standards = $stmt->fetchAll();
        
        // Get standard types for filter
        $typesStmt = $db->query("SELECT DISTINCT type FROM standards ORDER BY type");
        $types = $typesStmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (Exception $e) {
        error_log('Standards list error: ' . $e->getMessage());
        $standards = [];
        $totalPages = 0;
    }
    
    // Set page title
    $pageTitle = 'Standards';
    
    // Include header
    include INCLUDES_PATH . 'header.php';
    ?>

    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Certification</div>
                    <h2 class="page-title">Standards</h2>
                </div>
                <?php if ($userRole === ROLE_CERTIFIER): ?>
                <div class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        <a href="/standards/create" class="btn btn-primary">
                            <i class="ti ti-plus"></i> Create Standard
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <div class="row">
                <!-- Filters -->
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="GET" action="/standards" class="row g-2">
                                <div class="col-md-4">
                                    <input type="text" 
                                           name="search" 
                                           class="form-control" 
                                           placeholder="Search standards..."
                                           value="<?php echo Security::escape($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select name="type" class="form-select">
                                        <option value="">All Types</option>
                                        <?php foreach ($types as $t): ?>
                                            <option value="<?php echo Security::escape($t); ?>" 
                                                    <?php echo $type === $t ? 'selected' : ''; ?>>
                                                <?php echo Security::escape($t); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if ($userRole === ROLE_CERTIFIER): ?>
                                <div class="col-md-3">
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="ti ti-search"></i> Search
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Standards Grid -->
                <div class="col-12">
                    <div class="row row-cards">
                        <?php if (empty($standards)): ?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <i class="ti ti-certificate-off" style="font-size: 4rem; color: #ccc;"></i>
                                        <h3 class="mt-3">No Standards Found</h3>
                                        <p class="text-muted">
                                            <?php if ($userRole === ROLE_CERTIFIER): ?>
                                                You haven't created any standards yet.
                                            <?php else: ?>
                                                No standards are currently available.
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($userRole === ROLE_CERTIFIER): ?>
                                            <a href="/standards/create" class="btn btn-primary mt-3">
                                                <i class="ti ti-plus"></i> Create Your First Standard
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($standards as $standard): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="card-title"><?php echo Security::escape($standard['name']); ?></h3>
                                            <div class="card-actions">
                                                <?php if ($standard['status'] === 'active'): ?>
                                                    <span class="badge bg-green">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <div class="text-muted small">Type</div>
                                                <strong><?php echo Security::escape($standard['type']); ?></strong>
                                            </div>
                                            <div class="mb-3">
                                                <div class="text-muted small">Certifier</div>
                                                <strong><?php echo Security::escape($standard['certifier_name']); ?></strong>
                                            </div>
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <div class="text-muted small">Criteria</div>
                                                    <div class="h3 m-0"><?php echo $standard['criteria_count']; ?></div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-muted small">Validity</div>
                                                    <div class="h3 m-0"><?php echo $standard['validity_months']; ?>mo</div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-muted small">Price</div>
                                                    <div class="h3 m-0">€<?php echo number_format($standard['price'], 0); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <div class="btn-list">
                                                <a href="/standards/view/<?php echo $standard['id']; ?>" class="btn btn-sm">
                                                    <i class="ti ti-eye"></i> View
                                                </a>
                                                <?php if ($userRole === ROLE_CERTIFIER && $standard['certifier_id'] == $userId): ?>
                                                    <a href="/standards/edit/<?php echo $standard['id']; ?>" class="btn btn-sm">
                                                        <i class="ti ti-edit"></i> Edit
                                                    </a>
                                                <?php elseif ($userRole === ROLE_APPLICANT): ?>
                                                    <a href="/applications/create?standard=<?php echo $standard['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="ti ti-file-plus"></i> Apply
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>">
                                            <i class="ti ti-chevron-left"></i> prev
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>">
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
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>">
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
    </div>

    <?php
    // Include footer
    include INCLUDES_PATH . 'footer.php';
}