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
    "src/SignatureHelper.php",

    # Public
    "public/index.php",
    "public/demo.php",
    "public/checkout.php",
    "public/payment-success.php",
    "public/payment-failed.php",
    "public/query-order.php",
    "public/refund-order.php",
    "public/assets/css/style.css",
    "public/assets/js/payment.js",

    # Logs
    "logs/payment.log",

    # Root
    ".env",
    ".htaccess",
    "composer.json",
    "README.md"
)

foreach ($file in $files) {
    $path = Join-Path $root $file
    if (!(Test-Path $path)) {
        New-Item -ItemType File -Path $path | Out-Null
        Write-Host "Created file: $file"
    }
}

Write-Host ""
Write-Host "✅ Telebirr project structure created successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:"
Write-Host "1. Run: composer init"
Write-Host "2. Run: composer require phpseclib/phpseclib:^3.0"
Write-Host "3. Configure your .env file"
Write-Host ""
