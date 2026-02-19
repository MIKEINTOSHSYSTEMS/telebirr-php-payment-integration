# ================================
# Telebirr PHP Project Initializer
# ================================

Write-Host "Initializing Telebirr Project Structure..." -ForegroundColor Cyan

# Root path (current directory)
$root = Get-Location

# ================================
# Create Directories
# ================================

$directories = @(
    "config",
    "config/keys",
    "src",
    "public",
    "public/assets",
    "public/assets/css",
    "public/assets/js",
    "public/assets/images",
    "public/includes",
    "public/maintenance",
    "logs",
    "vendor",
    "tests"
)

foreach ($dir in $directories) {
    $path = Join-Path $root $dir
    if (!(Test-Path $path)) {
        New-Item -ItemType Directory -Path $path | Out-Null
        Write-Host "Created directory: $dir"
    }
}

# ================================
# Create Files
# ================================

$files = @(
    # Config
    "config/config.php",
    "config/database.php",
    "config/keys/private_key.pem",
    "config/keys/public_key.pem",

    # Src
    "src/TelebirrPayment.php",
    "src/ApplyFabricToken.php",
    "src/CreateOrder.php",
    "src/QueryOrder.php",
    "src/RefundOrder.php",
    "src/NotifyHandler.php",
    "src/Signer.php",
    "src/SignatureVerifier.php",
    "src/SignatureHelper.php",
    "src/ApiLogger.php",

    # Public
    "public/index.php",
    "public/demo.php",
    "public/checkout.php",
    "public/payment-success.php",
    "public/payment-failed.php",
    "public/query-order.php",
    "public/refund-order.php",
    "public/logs.php",
    "public/export-logs.php",
    "public/test-autoload.php",
    "public/test-token.php",
    "public/diagnostic.php",
    "public/includes/header.php",
    "public/includes/footer.php",
    "public/assets/css/style.css",
    "public/assets/js/payment.js",
    "public/assets/images/telebirr.svg",
    "public/maintenance/clean-logs.php",

    # Logs
    "logs/payment.log",
    "logs/.gitkeep",

    # Root
    ".env",
    ".env.example",
    ".htaccess",
    "composer.json",
    "README.md",
    "LICENSE",
    ".gitignore",
    "CHANGELOG.md",
    "CONTRIBUTING.md"
)

foreach ($file in $files) {
    $path = Join-Path $root $file
    if (!(Test-Path $path)) {
        New-Item -ItemType File -Path $path | Out-Null
        Write-Host "Created file: $file"
    }
}

# Create default telebirr.svg if it doesn't exist
$svgPath = Join-Path $root "public/assets/images/telebirr.svg"
if (!(Test-Path $svgPath)) {
    $svgContent = @'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
    <rect width="100" height="100" fill="#0066cc" rx="20"/>
    <text x="50" y="45" font-family="Arial" font-size="24" fill="white" text-anchor="middle" dominant-baseline="middle">Telebirr</text>
    <text x="50" y="65" font-family="Arial" font-size="14" fill="white" text-anchor="middle" dominant-baseline="middle">Payment</text>
</svg>
'@
    Set-Content -Path $svgPath -Value $svgContent
    Write-Host "Created default telebirr.svg"
}

# Create CHANGELOG.md
$changelogPath = Join-Path $root "CHANGELOG.md"
if (!(Test-Path $changelogPath)) {
    $changelogContent = @'
# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-02-20

### Added
- Initial release
- Complete C2B payment integration
- Fabric token management with caching
- Order creation and checkout URL generation
- Payment status querying
- Refund processing
- Webhook notification handling
- RSA signature generation and verification
- Database integration for transaction logging
- Comprehensive logging with Monolog and database
- Interactive demo application
- Responsive UI with dark/light mode toggle
- Comprehensive logs viewer with filtering and export
- Maintenance scripts for log cleanup
- Support for both test and production environments

### Security
- RSA-SHA256 signature verification
- Environment-based configuration
- SQL injection prevention (PDO prepared statements)
- XSS protection
- Secure key management
'@
    Set-Content -Path $changelogPath -Value $changelogContent
    Write-Host "Created CHANGELOG.md"
}

# Create CONTRIBUTING.md
$contributingPath = Join-Path $root "CONTRIBUTING.md"
if (!(Test-Path $contributingPath)) {
    $contributingContent = @'
# Contributing to Telebirr PHP Payment Integration

We love your input! We want to make contributing to this project as easy and transparent as possible.

## Development Process

1. Fork the repo and create your branch from `main`.
2. If you've added code that should be tested, add tests.
3. If you've changed APIs, update the documentation.
4. Ensure the test suite passes.
5. Make sure your code lints.
6. Issue that pull request!

## Code of Conduct

### Our Pledge

In the interest of fostering an open and welcoming environment, we as
contributors and maintainers pledge to making participation in our project and
our community a harassment-free experience for everyone.

### Our Standards

Examples of behavior that contributes to creating a positive environment
include:

* Using welcoming and inclusive language
* Being respectful of differing viewpoints and experiences
* Gracefully accepting constructive criticism
* Focusing on what is best for the community
* Showing empathy towards other community members

## Pull Request Process

1. Update the README.md with details of changes to the interface.
2. Update the CHANGELOG.md with notes on your changes.
3. The PR will be merged once you have the sign-off of maintainers.

## Any contributions you make will be under the MIT Software License

When you submit code changes, your submissions are understood to be under the same [MIT License](LICENSE) that covers the project.
'@
    Set-Content -Path $contributingPath -Value $contributingContent
    Write-Host "Created CONTRIBUTING.md"
}

Write-Host ""
Write-Host "✅ Telebirr project structure created successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "📁 Project Structure:"
Write-Host "  ├── config/          - Configuration files and RSA keys"
Write-Host "  ├── src/              - Core PHP classes"
Write-Host "  ├── public/           - Web root directory"
Write-Host "  │   ├── index.php     - Home page"
Write-Host "  │   ├── demo.php      - Payment form"
Write-Host "  │   ├── logs.php      - API logs viewer"
Write-Host "  │   ├── includes/     - Header/Footer templates"
Write-Host "  │   └── assets/       - CSS, JS, images"
Write-Host "  ├── logs/             - Log files"
Write-Host "  └── vendor/           - Composer dependencies"
Write-Host ""
Write-Host "🚀 Next steps:"
Write-Host "1. Run: composer install"
Write-Host "2. Copy .env.example to .env and configure:"
Write-Host "   cp .env.example .env"
Write-Host "3. Edit .env with your Telebirr credentials"
Write-Host "4. Place your RSA keys in config/keys/"
Write-Host "5. Create database and import schema from config/db.sql"
Write-Host "6. Access the application at: http://your-domain/merqpay/public/"
Write-Host ""
Write-Host "📚 Documentation: https://github.com/MIKEINTOSHSYSTEMS/telebirr-php-payment-integration"
Write-Host "🐛 Report issues: https://github.com/MIKEINTOSHSYSTEMS/telebirr-php-payment-integration/issues"
Write-Host "👨‍💻 Developed with ❤️ by MIKEINTOSH SYSTEMS"
Write-Host ""