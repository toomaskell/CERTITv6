<?php
/**
 * CERTOLO - Manage Standard Criteria
 * For certifiers to add/edit criteria for their standards
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
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        // Verify CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please refresh and try again.';
        } else {
            switch ($action) {
                case 'add':
                    // Add new criterion
                    $criterionData = [
                        'name' => $_POST['name'] ?? '',
                        'description' => $_POST['description'] ?? '',
                        'requirements' => $_POST['requirements'] ?? '',
                        'aspect' => $_POST['aspect'] ?? '',
                        'ra' => $_POST['ra'] ?? 'No',
                        'sort_order' => $_POST['sort_order'] ?? 0
                    ];
                    
                    if (empty($criterionData['name'])) {
                        $errors[] = 'Criterion name is required';
                    }
                    
                    if (empty($errors)) {
                        try {
                            $stmt = $db->query(
                                "INSERT INTO criterias (
                                    standard_id, name, description, requirements, 
                                    aspect, ra, sort_order, status
                                ) VALUES (
                                    :standard_id, :name, :description, :requirements,
                                    :aspect, :ra, :sort_order, 'active'
                                )",
                                [
                                    'standard_id' => $standardId,
                                    'name' => $criterionData['name'],
                                    'description' => $criterionData['description'],
                                    'requirements' => $criterionData['requirements'],
                                    'aspect' => $criterionData['aspect'],
                                    'ra' => $criterionData['ra'],
                                    'sort_order' => $criterionData['sort_order']
                                ]
                            );
                            
                            $success = true;
                            $_SESSION['success_message'] = 'Criterion added successfully!';
                        } catch (Exception $e) {
                            error_log('Add criterion error: ' . $e->getMessage());
                            $errors[] = 'Failed to add criterion';
                        }
                    }
                    break;
                    
                case 'update':
                    // Update criterion
                    $criterionId = $_POST['criterion_id'] ?? null;
                    if ($criterionId) {
                        $criterionData = [
                            'name' => $_POST['name'] ?? '',
                            'description' => $_POST['description'] ?? '',
                            'requirements' => $_POST['requirements'] ?? '',
                            'aspect' => $_POST['aspect'] ?? '',
                            'ra' => $_POST['ra'] ?? 'No',
                            'sort_order' => $_POST['sort_order'] ?? 0,
                            'status' => $_POST['status'] ?? 'active'
                        ];
                        
                        if (empty($criterionData['name'])) {
                            $errors[] = 'Criterion name is required';
                        }
                        
                        if (empty($errors)) {
                            try {
                                $stmt = $db->query(
                                    "UPDATE criterias SET
                                        name = :name,
                                        description = :description,
                                        requirements = :requirements,
                                        aspect = :aspect,
                                        ra = :ra,
                                        sort_order = :sort_order,
                                        status = :status,
                                        updated_at = NOW()
                                    WHERE id = :id AND standard_id = :standard_id",
                                    [
                                        'name' => $criterionData['name'],
                                        'description' => $criterionData['description'],
                                        'requirements' => $criterionData['requirements'],
                                        'aspect' => $criterionData['aspect'],
                                        'ra' => $criterionData['ra'],
                                        'sort_order' => $criterionData['sort_order'],
                                        'status' => $criterionData['status'],
                                        'id' => $criterionId,
                                        'standard_id' => $standardId
                                    ]
                                );
                                
                                $success = true;
                                $_SESSION['success_message'] = 'Criterion updated successfully!';
                            } catch (Exception $e) {
                                error_log('Update criterion error: ' . $e->getMessage());
                                $errors[] = 'Failed to update criterion';
                            }
                        }
                    }
                    break;
                    
                case 'delete':
                    // Delete criterion
                    $criterionId = $_POST['criterion_id'] ?? null;
                    if ($criterionId) {
                        try {
                            $stmt = $db->query(
                                "DELETE FROM criterias WHERE id = :id AND standard_id = :standard_id",
                                ['id' => $criterionId, 'standard_id' => $standardId]
                            );
                            
                            $success = true;
                            $_SESSION['success_message'] = 'Criterion deleted successfully!';
                        } catch (Exception $e) {
                            error_log('Delete criterion error: ' . $e->getMessage());
                            $errors[] = 'Failed to delete criterion';
                        }
                    }
                    break;
                    
                case 'reorder':
                    // Reorder criteria
                    $order = $_POST['order'] ?? [];
                    foreach ($order as $index => $criterionId) {
                        $db->query(
                            "UPDATE criterias SET sort_order = :order WHERE id = :id AND standard_id = :standard_id",
                            ['order' => $index, 'id' => $criterionId, 'standard_id' => $standardId]
                        );
                    }
                    $success = true;
                    $_SESSION['success_message'] = 'Criteria order updated!';
                    break;
            }
        }
    }
    
    // Get all criteria for this standard
    $criteriaStmt = $db->query(
        "SELECT * FROM criterias 
         WHERE standard_id = :standard_id 
         ORDER BY sort_order ASC, id ASC",
        ['standard_id' => $standardId]
    );
    
    $criteria = $criteriaStmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Criteria management error: ' . $e->getMessage());
    header('Location: /standards');
    exit;
}

// Set page title
$pageTitle = 'Manage Criteria - ' . $standard['name'];

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
                        <li class="breadcrumb-item active">Criteria</li>
                    </ol>
                </nav>
                <h2 class="page-title">Manage Criteria</h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
                    <i class="ti ti-plus"></i> Add Criterion
                </button>
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
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Criteria List (<?php echo count($criteria); ?>)</h3>
            </div>
            
            <?php if (empty($criteria)): ?>
                <div class="card-body text-center py-5">
                    <i class="ti ti-list-check" style="font-size: 4rem; color: #ccc;"></i>
                    <h3 class="mt-3">No Criteria Yet</h3>
                    <p class="text-muted">Start by adding criteria that applicants must meet for this certification.</p>
                    <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
                        <i class="ti ti-plus"></i> Add First Criterion
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>Name</th>
                                <th>Aspect</th>
                                <th>Risk Assessment</th>
                                <th>Status</th>
                                <th width="100"></th>
                            </tr>
                        </thead>
                        <tbody id="criteria-list">
                            <?php foreach ($criteria as $index => $criterion): ?>
                                <tr data-id="<?php echo $criterion['id']; ?>">
                                    <td>
                                        <span class="text-muted"><?php echo $index + 1; ?></span>
                                    </td>
                                    <td>
                                        <div><?php echo Security::escape($criterion['name']); ?></div>
                                        <?php if ($criterion['description']): ?>
                                            <small class="text-muted"><?php echo Security::escape(substr($criterion['description'], 0, 100)); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($criterion['aspect']): ?>
                                            <span class="badge bg-info"><?php echo ASPECTS[$criterion['aspect']] ?? $criterion['aspect']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($criterion['ra'] === 'Yes'): ?>
                                            <span class="badge bg-warning">Required</span>
                                        <?php else: ?>
                                            <span class="text-muted">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($criterion['status'] === 'active'): ?>
                                            <span class="badge bg-green">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-list flex-nowrap">
                                            <button type="button" 
                                                    class="btn btn-sm" 
                                                    onclick="editCriterion(<?php echo htmlspecialchars(json_encode($criterion)); ?>)">
                                                <i class="ti ti-edit"></i>
                                            </button>
                                            <form method="POST" action="/standards/criteria/<?php echo $standardId; ?>" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="criterion_id" value="<?php echo $criterion['id']; ?>">
                                                <button type="submit" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this criterion?')">
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
                <div class="btn-list">
                    <a href="/standards/view/<?php echo $standardId; ?>" class="btn">
                        <i class="ti ti-arrow-left"></i> Back to Standard
                    </a>
                    <?php if (count($criteria) > 0): ?>
                        <a href="/applications?standard=<?php echo $standardId; ?>" class="btn">
                            <i class="ti ti-file-text"></i> View Applications
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Criterion Modal -->
<div class="modal modal-blur fade" id="addCriterionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="/standards/criteria/<?php echo $standardId; ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="modal-header">
                <h5 class="modal-title">Add New Criterion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Criterion Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Requirements</label>
                    <textarea name="requirements" class="form-control" rows="3" 
                              placeholder="Specific requirements for this criterion..."></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Aspect</label>
                            <select name="aspect" class="form-select">
                                <option value="">Select aspect...</option>
                                <?php foreach (ASPECTS as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Risk Assessment Required?</label>
                            <select name="ra" class="form-select">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    <small class="form-hint">Lower numbers appear first</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Criterion</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Criterion Modal -->
<div class="modal modal-blur fade" id="editCriterionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="/standards/criteria/<?php echo $standardId; ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="criterion_id" id="edit-criterion-id">
            
            <div class="modal-header">
                <h5 class="modal-title">Edit Criterion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Criterion Name</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Requirements</label>
                    <textarea name="requirements" id="edit-requirements" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Aspect</label>
                            <select name="aspect" id="edit-aspect" class="form-select">
                                <option value="">Select aspect...</option>
                                <?php foreach (ASPECTS as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Risk Assessment</label>
                            <select name="ra" id="edit-ra" class="form-select">
                                <option value="No">No</option>
                                <option value="Yes">Yes - Required</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit-status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="edit-sort-order" class="form-control" min="0">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCriterion(criterion) {
    // Populate edit form
    document.getElementById('edit-criterion-id').value = criterion.id;
    document.getElementById('edit-name').value = criterion.name;
    document.getElementById('edit-description').value = criterion.description || '';
    document.getElementById('edit-requirements').value = criterion.requirements || '';
    document.getElementById('edit-aspect').value = criterion.aspect || '';
    document.getElementById('edit-ra').value = criterion.ra || 'No';
    document.getElementById('edit-status').value = criterion.status || 'active';
    document.getElementById('edit-sort-order').value = criterion.sort_order || 0;
    
    // Show modal
    var modal = new bootstrap.Modal(document.getElementById('editCriterionModal'));
    modal.show();
}

// Optional: Add sortable functionality
document.addEventListener('DOMContentLoaded', function() {
    // You can implement drag-and-drop reordering here if needed
});
</script>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>