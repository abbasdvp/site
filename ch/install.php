<?php

/**
 * Installation script for Telegram Channel Manager Bot
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base path
define('BASE_PATH', dirname(__FILE__));

// Define default configuration
$defaultConfig = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'telegram_bot',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_PORT' => '3306',
    'BOT_TOKEN' => '8367197417:AAGpDA2-7LC-AV0J-RrZBxcGDoi-w8Ie9ZY',
    'WEBHOOK_URL' => 'https://www.nyfer.hostme.top/ch/index.php'
];

echo "🚀 Telegram Channel Manager Bot - Installation Script\n\n";

// Check PHP version
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die("❌ Error: This script requires PHP 8.1 or higher. You are running PHP " . PHP_VERSION . "\n");
}

echo "✅ PHP version check passed (" . PHP_VERSION . ")\n";

// Check required extensions
$requiredExtensions = ['pdo', 'mysqli', 'curl', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die("❌ Missing required extensions: " . implode(', ', $missingExtensions) . "\n");
}

echo "✅ Required extensions check passed\n";

// Check writable directories
$writableDirs = [
    BASE_PATH . '/storage/logs',
    BASE_PATH . '/storage/cache',
    BASE_PATH . '/config'
];

foreach ($writableDirs as $dir) {
    if (!is_writable(dirname($dir)) && !is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die("❌ Cannot create directory: {$dir}\n");
        }
    }
    
    if (!is_writable($dir)) {
        die("❌ Directory not writable: {$dir}\n");
    }
}

echo "✅ Writable directories check passed\n";

// Get database configuration from user or use defaults
echo "\n📋 Database Configuration:\n";

$dbHost = readline("Enter database host (default: {$defaultConfig['DB_HOST']}): ");
$dbHost = empty($dbHost) ? $defaultConfig['DB_HOST'] : $dbHost;

$dbName = readline("Enter database name (default: {$defaultConfig['DB_NAME']}): ");
$dbName = empty($dbName) ? $defaultConfig['DB_NAME'] : $dbName;

$dbUser = readline("Enter database user (default: {$defaultConfig['DB_USER']}): ");
$dbUser = empty($dbUser) ? $defaultConfig['DB_USER'] : $dbUser;

$dbPass = readline("Enter database password: ");
$dbPass = empty($dbPass) ? $defaultConfig['DB_PASS'] : $dbPass;

$dbPort = readline("Enter database port (default: {$defaultConfig['DB_PORT']}): ");
$dbPort = empty($dbPort) ? $defaultConfig['DB_PORT'] : $dbPort;

// Test database connection
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `{$dbName}`;");
    
    echo "✅ Database connection established and database '{$dbName}' created/verified\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Read SQL schema
$sqlSchemaPath = BASE_PATH . '/config/database.sql';
if (!file_exists($sqlSchemaPath)) {
    die("❌ Database schema file not found: {$sqlSchemaPath}\n");
}

$sqlSchema = file_get_contents($sqlSchemaPath);

// Execute SQL schema
try {
    $pdo->exec($sqlSchema);
    echo "✅ Database tables created successfully\n";
} catch (Exception $e) {
    die("❌ Failed to create database tables: " . $e->getMessage() . "\n");
}

// Create config file
$configContent = "<?php\n";
$configContent .= "// Auto-generated configuration file\n";
$configContent .= "define('DB_HOST', '" . addslashes($dbHost) . "');\n";
$configContent .= "define('DB_NAME', '" . addslashes($dbName) . "');\n";
$configContent .= "define('DB_USER', '" . addslashes($dbUser) . "');\n";
$configContent .= "define('DB_PASS', '" . addslashes($dbPass) . "');\n";
$configContent .= "define('DB_PORT', " . intval($dbPort) . ");\n";
$configContent .= "define('BOT_TOKEN', '" . addslashes($defaultConfig['BOT_TOKEN']) . "');\n";
$configContent .= "define('WEBHOOK_URL', '" . addslashes($defaultConfig['WEBHOOK_URL']) . "');\n";

$configFilePath = BASE_PATH . '/config/config.php';
file_put_contents($configFilePath, $configContent);

echo "✅ Configuration file created: {$configFilePath}\n";

// Set webhook
echo "\n🌐 Setting up webhook...\n";

$webhookUrl = $defaultConfig['WEBHOOK_URL'];
$botToken = $defaultConfig['BOT_TOKEN'];

$webhookApiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url={$webhookUrl}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Failed to set webhook. HTTP Code: {$httpCode}\n";
    echo "Response: {$response}\n";
} else {
    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['ok']) && $responseData['ok']) {
        echo "✅ Webhook set successfully\n";
    } else {
        echo "❌ Failed to set webhook. Response: " . print_r($responseData, true) . "\n";
    }
}

// Create sample admin user
echo "\n👤 Creating sample admin user...\n";

try {
    // Insert sample admin user (replace with actual bot owner ID)
    $botOwnerId = 123456789; // Replace with actual bot owner ID
    $stmt = $pdo->prepare("
        INSERT INTO users (telegram_id, username, first_name, last_name, is_bot_owner, is_super_admin, created_at, last_activity) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $botOwnerId, 
        'botowner', 
        'Bot', 
        'Owner', 
        1, 
        1, 
        time(), 
        time()
    ]);
    
    // Insert admin record
    $stmt = $pdo->prepare("
        INSERT INTO admins (user_id, telegram_id, role, created_at) 
        VALUES (?, ?, ?, ?)
    ");
    $userId = $pdo->lastInsertId();
    $stmt->execute([$userId, $botOwnerId, 'super', time()]);
    
    echo "✅ Sample admin user created\n";
} catch (Exception $e) {
    echo "⚠️ Could not create sample admin user: " . $e->getMessage() . "\n";
}

// Provide instructions for cron setup
echo "\n⏰ Cron Job Setup Instructions:\n";
echo "Add the following line to your crontab to run the scheduler every minute:\n\n";
echo "* * * * * cd " . BASE_PATH . " && php cron.php >> storage/logs/cron.log 2>&1\n\n";

echo "✅ Installation completed successfully!\n";
echo "\nThe bot is now installed and configured.\n";
echo "- Webhook URL: {$webhookUrl}\n";
echo "- Config file: {$configFilePath}\n";
echo "- Database: {$dbName}\n";
echo "- Don't forget to set up the cron job for scheduled posts!\n";