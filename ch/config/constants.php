<?php
/**
 * Constants for Telegram Channel Manager Bot
 */

// Bot Configuration
define('BOT_TOKEN', '8367197417:AAGpDA2-7LC-AV0J-RrZBxcGDoi-w8Ie9ZY');
define('WEBHOOK_URL', 'https://www.nyfer.hostme.top/ch/index.php');

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'telegram_bot');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);

// Paths
define('BASE_PATH', dirname(__DIR__));
define('CORE_PATH', BASE_PATH . '/core');
define('HANDLERS_PATH', BASE_PATH . '/handlers');
define('MODELS_PATH', BASE_PATH . '/models');
define('REPOSITORIES_PATH', BASE_PATH . '/repositories');
define('SERVICES_PATH', BASE_PATH . '/services');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LOGS_PATH', STORAGE_PATH . '/logs');
define('CACHE_PATH', STORAGE_PATH . '/cache');

// Time constants
define('TIMEOUT', 30);
define('ONE_MINUTE', 60);
define('ONE_HOUR', 3600);
define('ONE_DAY', 86400);
define('ONE_WEEK', 604800);

// Limits
define('MAX_RETRY_COUNT', 3);
define('RATE_LIMIT_REQUESTS', 30); // per minute
define('RATE_LIMIT_WINDOW', ONE_MINUTE);

// File sizes (in bytes)
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

// Post types
define('POST_TYPE_NORMAL', 'normal');
define('POST_TYPE_FORWARDED', 'forwarded');
define('POST_TYPE_LAST', 'last');
define('POST_TYPE_DOWNER', 'downer');

// Channel statuses
define('CHANNEL_STATUS_ACTIVE', 'active');
define('CHANNEL_STATUS_INACTIVE', 'inactive');
define('CHANNEL_STATUS_BANNED', 'banned');
define('CHANNEL_STATUS_PENDING', 'pending');

// Post statuses
define('POST_STATUS_PENDING', 'pending');
define('POST_STATUS_SENT', 'sent');
define('POST_STATUS_FAILED', 'failed');
define('POST_STATUS_DELETED', 'deleted');
define('POST_STATUS_EXPIRED', 'expired');

// Queue statuses
define('QUEUE_STATUS_WAITING', 'waiting');
define('QUEUE_STATUS_PROCESSING', 'processing');
define('QUEUE_STATUS_SENT', 'sent');
define('QUEUE_STATUS_FAILED', 'failed');

// Schedule types
define('SCHEDULE_TYPE_PERIODIC', 'periodic');
define('SCHEDULE_TYPE_FIXED', 'fixed');
define('SCHEDULE_TYPE_MANUAL', 'manual');

// Post repeat modes
define('REPEAT_MODE_DELETE_FROM_LIST', 'delete_from_list');
define('REPEAT_MODE_REQUEUE', 'requeue');

// Admin roles
define('ADMIN_ROLE_SUPER', 'super');
define('ADMIN_ROLE_FULL', 'full');
define('ADMIN_ROLE_LIMITED', 'limited');

// Operation types
define('OPERATION_COPY_POSTS', 'copy_posts');
define('OPERATION_COPY_SCHEDULE', 'copy_schedule');
define('OPERATION_SEND_NEW_POST', 'send_new_post');

// Group operation statuses
define('GROUP_OP_PENDING', 'pending');
define('GROUP_OP_PROCESSING', 'processing');
define('GROUP_OP_COMPLETED', 'completed');
define('GROUP_OP_FAILED', 'failed');

// Media types
define('MEDIA_TYPE_TEXT', 'text');
define('MEDIA_TYPE_PHOTO', 'photo');
define('MEDIA_TYPE_VIDEO', 'video');
define('MEDIA_TYPE_AUDIO', 'audio');
define('MEDIA_TYPE_DOCUMENT', 'document');
define('MEDIA_TYPE_ANIMATION', 'animation');
define('MEDIA_TYPE_VOICE', 'voice');
define('MEDIA_TYPE_STICKER', 'sticker');
define('MEDIA_TYPE_POLL', 'poll');
define('MEDIA_TYPE_LOCATION', 'location');
define('MEDIA_TYPE_CONTACT', 'contact');

// Channel admin roles
define('CHANNEL_ROLE_OWNER', 'owner');
define('CHANNEL_ROLE_ADMIN', 'admin');
define('CHANNEL_ROLE_EDITOR', 'editor');

// Downer modes
define('DOWNER_MODE_NORMAL', 'normal');
define('DOWNER_MODE_FORWARDED', 'forwarded');

// Parse mode
define('PARSE_MODE_HTML', 'HTML');
define('PARSE_MODE_MARKDOWN', 'Markdown');
define('PARSE_MODE_MARKDOWN_V2', 'MarkdownV2');