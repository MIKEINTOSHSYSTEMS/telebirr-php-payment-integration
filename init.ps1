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
    "logs",
    "vendor"
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
    "public/test-autoload.php",
    "public/test-token.php",
    "public/diagnostic.php",
    "public/includes/header.php",
    "public/includes/footer.php",
    "public/assets/css/style.css",
    "public/assets/js/payment.js",
    "public/assets/images/telebirr.svg",

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
    ".gitignore"
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

Write-Host ""
Write-Host "✅ Telebirr project structure created successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:"
Write-Host "1. Run: composer install"
Write-Host "2. Run: composer require vlucas/phpdotenv monolog/monolog phpseclib/phpseclib"
Write-Host "3. Configure your .env file with your credentials"
Write-Host "4. Place your private and public keys in config/keys/"
Write-Host "5. Access the application at: http://your-domain/merqpay/public/"
Write-Host ""
Write-Host "📚 Documentation: https://github.com/MIKEINTOSHSYSTEMS/telebirr-php-payment-integration"
Write-Host "👨‍💻 Developed by MIKEINTOSH SYSTEMS"
Write-Host ""