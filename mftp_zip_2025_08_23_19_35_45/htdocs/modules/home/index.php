<?php
/**
 * CERTOLO - Home Page (Landing Page)
 * Public landing page for the certification system
 */

// Set page title
$pageTitle = 'Welcome';

// Include header
include INCLUDES_PATH . 'header.php';
?>

<!-- Hero Section -->
<div class="page-header d-print-none" style="background: linear-gradient(135deg, <?php echo COLOR_PRIMARY_DARK; ?> 0%, <?php echo COLOR_PRIMARY_MEDIUM; ?> 100%);">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col-lg-6 py-6">
                <h1 class="text-white display-4 fw-bold mb-4">
                    Streamline Your Certification Process with CERTOLO
                </h1>
                <p class="text-white-50 fs-3 mb-4">
                    The modern platform for managing certification applications, reviews, and issuance - all in one secure place.
                </p>
                <div class="btn-list">
                    <a href="/register" class="btn btn-accent btn-lg">
                        <i class="ti ti-rocket"></i> Get Started
                    </a>
                    <a href="#how-it-works" class="btn btn-light btn-lg">
                        <i class="ti ti-info-circle"></i> Learn More
                    </a>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block text-center">
                <img src="/assets/images/certification-hero.svg" alt="Certification Process" class="img-fluid" style="max-height: 400px;">
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="page-body" id="features">
    <div class="container-xl">
        <div class="row justify-content-center text-center mb-6">
            <div class="col-lg-8">
                <h2 class="display-5 mb-3">Why Choose CERTOLO?</h2>
                <p class="lead text-muted">
                    Built specifically for certification bodies and companies seeking certification, 
                    CERTOLO makes the entire process efficient, transparent, and secure.
                </p>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- For Applicants -->
            <div class="col-lg-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-5">
                        <div class="mb-4">
                            <span class="avatar avatar-xl rounded-circle" style="background-color: <?php echo COLOR_PRIMARY_LIGHT; ?>;">
                                <i class="ti ti-building" style="font-size: 2rem; color: <?php echo COLOR_PRIMARY_DARK; ?>;"></i>
                            </span>
                        </div>
                        <h3 class="card-title mb-3">For Companies Seeking Certification</h3>
                        <div class="mb-4">
                            <div class="d-flex align-items-start mb-3">
                                <i class="ti ti-check text-success me-3 mt-1"></i>
                                <div>
                                    <strong>Easy Application Process</strong>
                                    <div class="text-muted">Submit applications online with guided forms and document uploads</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <i class="ti ti-check text-success me-3 mt-1"></i>
                                <div>
                                    <strong>Real-time Status Tracking</strong>
                                    <div class="text-muted">Monitor your application progress and receive instant notifications</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <i class="ti ti-check text-success me-3 mt-1"></i>
                                <div>
                                    <strong>Digital Certificate Management</strong>
                                    <div class="text-muted">Access and download your certificates anytime, anywhere</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <i class="ti ti-check text-success me-3 mt-1"></i>
                                <div>
                                    <strong>Secure Communication</strong>
                                    <div class="text-muted">Direct messaging with certifiers for clarifications and updates</div>
                                </div>
                            </div>
                        </div>
                        <a href="/register" class="btn btn-primary">
                            <i class="ti ti-user-plus"></i> Apply for Certification
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- For Certifiers -->
            <div class="col-lg-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-5">
                        <div class="mb-4">
                            <span class="avatar avatar-xl rounded-circle" style="background-color: <?php echo COLOR_ACCENT; ?>;">
                                <i class="ti ti-certificate" style="font-size: 2rem; color: white;"></i>
                            </span>
                        </div>
                        <h3 class="card-title mb-3">For Certification Authorities</h3>
                        <div class="mb-4">
                            <div class="d-flex align-items-start mb-3">
                                <i class="ti ti-check text-success me-3 mt-1"></i>
                                <div>
                                    <strong>Streamlined Review Process</strong>
                                    <div class="text-muted">Efficiently manage and review multiple applications</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <i class="ti ti-check text-success me-3 mt-1"></i>
                                <div>
                                    <strong>Standards Management</strong>
                                    <div class="text-muted">Create and manage certification standards with detailed criteria</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-3">
                                <i class="ti ti-check text-success me-3 mt-1"></i>
                                <div>
                                    <strong>Automated Certificate Generation</strong>
                                    <div class="text-muted">Issue certificates with unique verification codes automatically</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <i class="ti ti-check text-success me-3 mt-1"></i>
                                <div>
                                    <strong>Customer Relationship Management</strong>
                                    <div class="text-muted">Track and manage all your certification clients in one place</div>
                                </div>
                            </div>
                        </div>
                        <a href="/register" class="btn btn-accent text-white">
                            <i class="ti ti-award"></i> Become a Certifier
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<div class="container-xl py-6" id="how-it-works">
    <div class="row justify-content-center text-center mb-6">
        <div class="col-lg-8">
            <h2 class="display-5 mb-3">How CERTOLO Works</h2>
            <p class="lead text-muted">
                Simple, transparent, and efficient certification process
            </p>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Step 1 -->
        <div class="col-md-3 text-center">
            <div class="mb-3">
                <span class="avatar avatar-xl rounded-circle bg-primary text-white">1</span>
            </div>
            <h4>Register</h4>
            <p class="text-muted">Create your account as an applicant or certifier</p>
        </div>
        
        <!-- Step 2 -->
        <div class="col-md-3 text-center">
            <div class="mb-3">
                <span class="avatar avatar-xl rounded-circle bg-primary text-white">2</span>
            </div>
            <h4>Apply or Create</h4>
            <p class="text-muted">Submit applications or create certification standards</p>
        </div>
        
        <!-- Step 3 -->
        <div class="col-md-3 text-center">
            <div class="mb-3">
                <span class="avatar avatar-xl rounded-circle bg-primary text-white">3</span>
            </div>
            <h4>Review</h4>
            <p class="text-muted">Applications are reviewed against criteria</p>
        </div>
        
        <!-- Step 4 -->
        <div class="col-md-3 text-center">
            <div class="mb-3">
                <span class="avatar avatar-xl rounded-circle bg-primary text-white">4</span>
            </div>
            <h4>Certify</h4>
            <p class="text-muted">Receive or issue digital certificates</p>
        </div>
    </div>
</div>

<!-- Statistics Section -->
<div class="bg-light py-6">
    <div class="container-xl">
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3">
                <h3 class="display-6 fw-bold text-primary mb-1">500+</h3>
                <p class="text-muted mb-0">Active Companies</p>
            </div>
            <div class="col-6 col-md-3">
                <h3 class="display-6 fw-bold text-primary mb-1">50+</h3>
                <p class="text-muted mb-0">Certification Bodies</p>
            </div>
            <div class="col-6 col-md-3">
                <h3 class="display-6 fw-bold text-primary mb-1">1,000+</h3>
                <p class="text-muted mb-0">Certificates Issued</p>
            </div>
            <div class="col-6 col-md-3">
                <h3 class="display-6 fw-bold text-primary mb-1">99.9%</h3>
                <p class="text-muted mb-0">Uptime</p>
            </div>
        </div>
    </div>
</div>

<!-- CTA Section -->
<div class="container-xl py-6 text-center" id="contact">
    <h2 class="display-5 mb-3">Ready to Get Started?</h2>
    <p class="lead text-muted mb-4">
        Join CERTOLO today and streamline your certification process
    </p>
    <div class="btn-list justify-content-center">
        <a href="/register" class="btn btn-primary btn-lg">
            <i class="ti ti-rocket"></i> Start Free Trial
        </a>
        <a href="mailto:info@certit.ee" class="btn btn-outline-primary btn-lg">
            <i class="ti ti-mail"></i> Contact Us
        </a>
    </div>
</div>

<!-- Add smooth scrolling for anchor links -->
<script>
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

<?php
// Include footer
include INCLUDES_PATH . 'footer.php';
?>