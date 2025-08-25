<?php
/**
 * CERTOLO - Submit Application
 * Submit draft application for review
 */

// Check if user is applicant
if ($userRole !== ROLE_APPLICANT) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Get application ID
$applicationId = $id ?? $_GET['id'] ?? null;

if (!$applicationId) {
    header('Location: /applications');
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get application details
    $stmt = $db->query(
        "SELECT a.*, s.name as standard_name, u.email as certifier_email, u.company_name as certifier_name
         FROM applications a
         JOIN standards s ON a.standard_id = s.id
         JOIN users u ON a.certifier_id = u.id
         WHERE a.id = :id AND a.applicant_id = :user_id AND a.status = 'draft'",
        ['id' => $applicationId, 'user_id' => $userId]
    );
    
    $application = $stmt->fetch();
    
    if (!$application) {
        $_SESSION['error_message'] = 'Application not found or already submitted.';
        header('Location: /applications');
        exit;
    }
    
    // Check if form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error_message'] = 'Invalid security token. Please refresh and try again.';
            header('Location: /applications/view/' . $applicationId);
            exit;
        }
        
        try {
            $db->beginTransaction();
            
            // Update application status
            $updateStmt = $db->query(
                "UPDATE applications 
                 SET status = 'submitted', 
                     submitted_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id",
                ['id' => $applicationId]
            );
            
            // Create notification for certifier
            $notifyStmt = $db->query(
                "INSERT INTO notifications (user_id, type, title, message, data) 
                 VALUES (:user_id, :type, :title, :message, :data)",
                [
                    'user_id' => $application['certifier_id'],
                    'type' => 'application_submitted',
                    'title' => 'New Application Submitted',
                    'message' => 'A new application for ' . $application['standard_name'] . ' has been submitted.',
                    'data' => json_encode([
                        'application_id' => $applicationId,
                        'application_number' => $application['application_number'],
                        'standard_name' => $application['standard_name']
                    ])
                ]
            );
            
            // Log activity
            $logStmt = $db->query(
                "INSERT INTO activity_logs (user_id, action, module, record_id, record_type, ip_address) 
                 VALUES (:user_id, :action, :module, :record_id, :record_type, :ip)",
                [
                    'user_id' => $userId,
                    'action' => 'submit',
                    'module' => 'applications',
                    'record_id' => $applicationId,
                    'record_type' => 'application',
                    'ip' => Security::getClientIP()
                ]
            );
            
            // Queue email notification
            $emailStmt = $db->query(
                "INSERT INTO email_logs (to_email, subject, template, data) 
                 VALUES (:email, :subject, :template, :data)",
                [
                    'email' => $application['certifier_email'],
                    'subject' => 'New Application Submitted - ' . $application['application_number'],
                    'template' => 'application_submitted',
                    'data' => json_encode([
                        'certifier_name' => $application['certifier_name'],
                        'application_number' => $application['application_number'],
                        'standard_name' => $application['standard_name'],
                        'applicant_company' => $_SESSION['company_name'],
                        'view_link' => SITE_URL . '/applications/review/' . $applicationId
                    ])
                ]
            );
            
            $db->commit();
            
            $_SESSION['success_message'] = 'Application submitted successfully! The certifier will review it soon.';
            header('Location: /applications/view/' . $applicationId);
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            error_log('Submit application error: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to submit application. Please try again.';
            header('Location: /applications/view/' . $applicationId);
            exit;
        }
    } else {
        // This page should only be accessed via POST
        header('Location: /applications/view/' . $applicationId);
        exit;
    }
    
} catch (Exception $e) {
    error_log('Submit page error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred. Please try again.';
    header('Location: /applications');
    exit;
}