<?php

/**
 * Cron job file - processes scheduled posts and other timed operations
 * Should be run every minute via system cron
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define necessary constants if not already defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__FILE__));
}

// Include autoloader if exists, otherwise manually include required files
$autoloaderPath = BASE_PATH . '/vendor/autoload.php';
if (file_exists($autoloaderPath)) {
    require_once $autoloaderPath;
} else {
    // Manually include our classes since we're not using Composer
    require_once BASE_PATH . '/config/constants.php';
    require_once BASE_PATH . '/core/Database.php';
    require_once BASE_PATH . '/core/TelegramAPI.php';
    require_once BASE_PATH . '/services/SchedulerService.php';
    require_once BASE_PATH . '/repositories/PostRepository.php';
    require_once BASE_PATH . '/repositories/PostQueueRepository.php';
}

use Core\Database;
use Core\TelegramAPI;
use Services\SchedulerService;

try {
    // Create lock file to prevent overlapping executions
    $lockFile = BASE_PATH . '/storage/cron.lock';
    $lockTimeout = 55; // Max execution time in seconds (slightly less than 1 minute)

    // Check if lock exists and is still valid
    if (file_exists($lockFile) && (time() - filemtime($lockFile)) < $lockTimeout) {
        // Another instance is running, exit
        exit("Cron job is already running\n");
    }

    // Create lock file
    file_put_contents($lockFile, time());

    // Initialize components
    $database = Database::getInstance();
    $telegram = new TelegramAPI(BOT_TOKEN);
    $schedulerService = new SchedulerService($telegram);

    // Process scheduled posts
    $processedCount = $schedulerService->processScheduledPosts();

    // Log successful execution
    error_log(date('Y-m-d H:i:s') . " - Cron job executed. Processed {$processedCount} posts.\n", 3, BASE_PATH . '/storage/logs/cron.log');

    // Clean up lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }

    echo "Cron job completed successfully. Processed {$processedCount} posts.\n";
} catch (Exception $e) {
    // Log error
    error_log(date('Y-m-d H:i:s') . " - Cron job error: " . $e->getMessage() . "\n", 3, BASE_PATH . '/storage/logs/cron.log');
    
    // Clean up lock file if it exists
    if (isset($lockFile) && file_exists($lockFile)) {
        unlink($lockFile);
    }

    echo "Cron job failed: " . $e->getMessage() . "\n";
    exit(1);
}