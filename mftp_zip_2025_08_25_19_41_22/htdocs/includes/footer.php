</div><!-- End page-wrapper -->
        
        <!-- Footer -->
        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl">
                <div class="row text-center align-items-center flex-row-reverse">
                    <div class="col-lg-auto ms-lg-auto">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item"><a href="/terms" class="link-secondary">Terms of Service</a></li>
                            <li class="list-inline-item"><a href="/privacy" class="link-secondary">Privacy Policy</a></li>
                            <li class="list-inline-item"><a href="/contact" class="link-secondary">Contact</a></li>
                        </ul>
                    </div>
                    <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item">
                                Copyright &copy; <?php echo date('Y'); ?>
                                <a href="/" class="link-secondary">CERTOLO</a>.
                                All rights reserved.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div><!-- End page -->
    
    <!-- Libs JS -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/assets/js/app.js?v=<?php echo time(); ?>"></script>
    
    <?php if ($isLoggedIn): ?>
    <!-- Notification polling for logged in users -->
    <script>
        // Check for new notifications every 30 seconds
        function checkNotifications() {
            fetch('/api/notifications/unread-count')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notification-count');
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
        }
        
        // Check on page load
        checkNotifications();
        
        // Check periodically
        setInterval(checkNotifications, 30000);
    </script>
    <?php endif; ?>
    
    <!-- Page-specific scripts -->
    <?php if (isset($pageScripts)): ?>
        <?php echo $pageScripts; ?>
    <?php endif; ?>
</body>
</html>