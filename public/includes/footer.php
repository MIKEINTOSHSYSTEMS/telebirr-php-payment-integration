<?php

/**
 * Footer Include File
 * Contains common footer with copyright and branding
 */

// Load configuration for company info
$config = require __DIR__ . '/../../config/config.php';

$company_name = $config['app']['company_name'] ?? 'MIKEINTOSH SYSTEMS';
$company_url = $config['app']['company_url'] ?? 'https://mikeintoshs.com';
$github_repo = $config['app']['github_repo'] ?? 'https://github.com/MIKEINTOSHSYSTEMS/telebirr-php-payment-integration.git';
$current_year = date('Y');
?>
</main>

<footer class="main-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h4>About Telebirr Gateway</h4>
                <p>Complete C2B Web Payment Integration for PHP. Seamlessly integrate Telebirr payments into your applications.</p>
            </div>

            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="demo.php">Make Payment</a></li>
                    <li><a href="query-order.php">Query Order</a></li>
                    <li><a href="refund-order.php">Process Refund</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Resources</h4>
                <ul>
                    <li><a href="#" onclick="window.open('https://github.com/MIKEINTOSHSYSTEMS/telebirr-php-payment-integration', '_blank')">GitHub Repository</a></li>
                    <li><a href="#" onclick="window.open('<?php echo $company_url; ?>', '_blank')">MIKEINTOSH SYSTEMS</a></li>
                    <li><a href="test-autoload.php">Test Autoload</a></li>
                    <li><a href="test-token.php">Test Token</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="copyright">
                &copy; <?php echo $current_year; ?> <a href="<?php echo $company_url; ?>" target="_blank"><?php echo $company_name; ?></a>.
                All rights reserved. Designed and Developed with ❤️ by MIKEINTOSH SYSTEMS
            </div>
            <div class="footer-links">
                <a href="#" onclick="window.open('<?php echo $github_repo; ?>', '_blank')">GitHub</a>
                <a href="#" onclick="window.open('<?php echo $company_url; ?>', '_blank')">Website</a>
                <a href="LICENSE">License</a>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript -->
<script src="assets/js/payment.js"></script>

<!-- Theme Toggle Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggle = document.getElementById('themeToggle');
        const sunIcon = document.querySelector('.sun-icon');
        const moonIcon = document.querySelector('.moon-icon');
        const htmlElement = document.documentElement;

        function setTheme(theme) {
            htmlElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);

            if (theme === 'dark') {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            } else {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            }
        }

        // Initialize icons based on current theme
        const currentTheme = htmlElement.getAttribute('data-theme');
        setTheme(currentTheme);

        themeToggle.addEventListener('click', function() {
            const newTheme = htmlElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
        });
    });
</script>
</body>

</html>