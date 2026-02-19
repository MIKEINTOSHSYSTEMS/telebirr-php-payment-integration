<?php

/**
 * Header Include File
 * Contains common header, navigation, and branding for all pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page name for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Telebirr Payment Gateway Integration - Complete C2B Web Payment Solution for PHP">
    <meta name="keywords" content="Telebirr, Payment Gateway, PHP, Ethiopia, Mobile Money, C2B">
    <meta name="author" content="MIKEINTOSH SYSTEMS">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/images/telebirr.svg">
    <link rel="apple-touch-icon" href="assets/images/telebirr.svg">

    <title><?php echo $page_title ?? 'Telebirr Payment Gateway'; ?> | MIKEINTOSH SYSTEMS</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Dark Mode Preference -->
    <script>
        // Check for saved theme preference or use system preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
</head>

<body>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo-container">
                    <a href="index.php" class="logo-link">
                        <img src="assets/images/telebirr.svg" alt="Telebirr Logo" class="logo">
                        <span class="logo-text">Telebirr Payment Gateway</span>
                    </a>
                </div>

                <div class="header-actions">
                    <!-- Theme Toggle -->
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                        <svg class="sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                        <svg class="moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </button>

                    <!-- Navigation -->
                    <nav class="main-nav">
                        <ul>
                            <li><a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">Home</a></li>
                            <li><a href="demo.php" class="<?php echo $current_page == 'demo.php' ? 'active' : ''; ?>">Make Payment</a></li>
                            <li><a href="query-order.php" class="<?php echo $current_page == 'query-order.php' ? 'active' : ''; ?>">Query</a></li>
                            <li><a href="refund-order.php" class="<?php echo $current_page == 'refund-order.php' ? 'active' : ''; ?>">Refund</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">