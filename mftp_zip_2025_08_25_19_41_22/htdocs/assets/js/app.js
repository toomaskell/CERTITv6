/**
 * CERTOLO Main JavaScript
 * Application-wide JavaScript functionality
 */

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // File upload handling
    initializeFileUploads();

    // Confirmation dialogs
    initializeConfirmDialogs();

    // Dynamic form elements
    initializeDynamicForms();

    // Search functionality
    initializeSearch();
});

// File upload functionality
function initializeFileUploads() {
    const uploadAreas = document.querySelectorAll('.upload-area');
    
    uploadAreas.forEach(area => {
        const input = area.querySelector('input[type="file"]');
        
        // Click to upload
        area.addEventListener('click', (e) => {
            if (e.target.tagName !== 'INPUT') {
                input.click();
            }
        });

        // Drag and drop
        area.addEventListener('dragover', (e) => {
            e.preventDefault();
            area.classList.add('dragover');
        });

        area.addEventListener('dragleave', () => {
            area.classList.remove('dragover');
        });

        area.addEventListener('drop', (e) => {
            e.preventDefault();
            area.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && input) {
                input.files = files;
                handleFileSelect(input);
            }
        });

        // File selection
        if (input) {
            input.addEventListener('change', () => {
                handleFileSelect(input);
            });
        }
    });
}

// Handle file selection
function handleFileSelect(input) {
    const files = input.files;
    const preview = input.closest('.upload-area').querySelector('.file-preview');
    
    if (preview) {
        preview.innerHTML = '';
        
        Array.from(files).forEach(file => {
            const fileInfo = document.createElement('div');
            fileInfo.className = 'file-info mt-2';
            fileInfo.innerHTML = `
                <i class="ti ti-file"></i> ${file.name} 
                <small class="text-muted">(${formatFileSize(file.size)})</small>
            `;
            preview.appendChild(fileInfo);
        });
    }
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Initialize confirmation dialogs
function initializeConfirmDialogs() {
    document.addEventListener('click', function(e) {
        const confirmLink = e.target.closest('[data-confirm]');
        if (confirmLink) {
            e.preventDefault();
            const message = confirmLink.getAttribute('data-confirm');
            
            if (confirm(message)) {
                if (confirmLink.hasAttribute('data-method')) {
                    // Handle non-GET requests
                    const method = confirmLink.getAttribute('data-method');
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = confirmLink.href;
                    
                    // Add CSRF token
                    const csrfToken = document.querySelector('input[name="csrf_token"]');
                    if (csrfToken) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'csrf_token';
                        input.value = csrfToken.value;
                        form.appendChild(input);
                    }
                    
                    // Add method override for DELETE, PUT, etc.
                    if (method !== 'POST') {
                        const methodInput = document.createElement('input');
                        methodInput.type = 'hidden';
                        methodInput.name = '_method';
                        methodInput.value = method;
                        form.appendChild(methodInput);
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                } else {
                    // Regular GET request
                    window.location.href = confirmLink.href;
                }
            }
        }
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible notification-toast position-fixed top-0 end-0 m-3`;
    notification.style.zIndex = '9999';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Copy to clipboard
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copied to clipboard!', 'success');
        }).catch(() => {
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

function fallbackCopyToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showNotification('Copied to clipboard!', 'success');
    } catch (err) {
        showNotification('Failed to copy', 'danger');
    }
    
    document.body.removeChild(textArea);
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('et-EE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

// Format datetime
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('et-EE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Initialize dynamic forms
function initializeDynamicForms() {
    // Dynamic criteria addition
    const addCriteriaBtn = document.getElementById('add-criteria');
    if (addCriteriaBtn) {
        addCriteriaBtn.addEventListener('click', function() {
            const container = document.getElementById('criteria-container');
            const template = document.getElementById('criteria-template');
            const clone = template.content.cloneNode(true);
            container.appendChild(clone);
        });
    }

    // Remove criteria
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-criteria')) {
            e.target.closest('.criteria-item').remove();
        }
    });
}

// Initialize search functionality
function initializeSearch() {
    const searchInputs = document.querySelectorAll('[data-search]');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const targetSelector = this.getAttribute('data-search');
            const items = document.querySelectorAll(targetSelector);
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
}

// AJAX form submission
function submitFormAjax(form, callback) {
    const formData = new FormData(form);
    const action = form.action;
    const method = form.method || 'POST';
    
    // Show loading
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    
    fetch(action, {
        method: method,
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Operation successful', 'success');
            if (callback) callback(data);
        } else {
            showNotification(data.message || 'An error occurred', 'danger');
        }
    })
    .catch(error => {
        showNotification('Network error. Please try again.', 'danger');
        console.error('Error:', error);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Print functionality
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', 'PRINT', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Print</title>');
    printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">');
    printWindow.document.write('<link rel="stylesheet" href="/assets/css/custom.css">');
    printWindow.document.write('</head><body>');
    printWindow.document.write(element.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };
}

// Export table to CSV
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        
        cols.forEach(col => {
            let data = col.textContent.trim();
            // Escape quotes
            data = data.replace(/"/g, '""');
            // Wrap in quotes if contains comma
            if (data.includes(',')) {
                data = `"${data}"`;
            }
            rowData.push(data);
        });
        
        csv.push(rowData.join(','));
    });
    
    // Download CSV
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Loading overlay
function showLoading() {
    const overlay = document.createElement('div');
    overlay.className = 'spinner-overlay';
    overlay.innerHTML = '<div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>';
    document.body.appendChild(overlay);
}

function hideLoading() {
    const overlay = document.querySelector('.spinner-overlay');
    if (overlay) {
        overlay.remove();
    }
}

// Character counter for textareas
document.addEventListener('input', function(e) {
    if (e.target.hasAttribute('data-max-length')) {
        const maxLength = parseInt(e.target.getAttribute('data-max-length'));
        const currentLength = e.target.value.length;
        const counter = document.getElementById(e.target.id + '-counter');
        
        if (counter) {
            counter.textContent = `${currentLength} / ${maxLength}`;
            
            if (currentLength > maxLength * 0.9) {
                counter.classList.add('text-danger');
            } else {
                counter.classList.remove('text-danger');
            }
        }
    }
});

// Debounce function for search inputs
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Auto-save draft functionality
let autoSaveTimer;
function enableAutoSave(formId, interval = 30000) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    // Save draft every interval
    autoSaveTimer = setInterval(() => {
        const formData = new FormData(form);
        formData.append('auto_save', 'true');
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Draft saved automatically', 'info');
            }
        })
        .catch(error => {
            console.error('Auto-save error:', error);
        });
    }, interval);
}

// Disable auto-save
function disableAutoSave() {
    if (autoSaveTimer) {
        clearInterval(autoSaveTimer);
    }
}

// Global error handler
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    // You can add error reporting here
});

// Make functions available globally
window.CERTOLO = {
    showNotification,
    copyToClipboard,
    formatDate,
    formatDateTime,
    submitFormAjax,
    printElement,
    exportTableToCSV,
    showLoading,
    hideLoading,
    enableAutoSave,
    disableAutoSave
};