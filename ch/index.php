<?php

/**
 * Main webhook handler for Telegram Channel Manager Bot
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
    require_once BASE_PATH . '/core/BotCore.php';
    require_once BASE_PATH . '/handlers/ChannelHandler.php';
    require_once BASE_PATH . '/handlers/PostHandler.php';
    require_once BASE_PATH . '/handlers/AdminHandler.php';
    require_once BASE_PATH . '/handlers/GroupActionHandler.php';
}

use Core\TelegramAPI;
use Core\BotCore;
use Handlers\ChannelHandler;
use Handlers\PostHandler;
use Handlers\AdminHandler;
use Handlers\GroupActionHandler;

try {
    // Initialize main components
    $telegram = new TelegramAPI(BOT_TOKEN);
    $botCore = new BotCore($telegram);

    // Initialize handlers
    $channelHandler = new ChannelHandler($telegram);
    $postHandler = new PostHandler($telegram);
    $adminHandler = new AdminHandler($telegram);
    $groupActionHandler = new GroupActionHandler($telegram);

    // Register handlers with bot core
    $botCore->addHandler(ChannelHandler::class, $channelHandler);
    $botCore->addHandler(PostHandler::class, $postHandler);
    $botCore->addHandler(AdminHandler::class, $adminHandler);
    $botCore->addHandler(GroupActionHandler::class, $groupActionHandler);

    // Register command handlers
    $botCore->registerCommand('/start', function($message) use ($telegram) {
        $chatId = $message['chat']['id'];
        $welcomeMessage = "🎉 به ربات مدیریت کانال‌های تلگرام خوش آمدید!\n\n";
        $welcomeMessage .= "این ربات امکانات زیر را فراهم می‌کند:\n";
        $welcomeMessage .= "🔹 افزودن و مدیریت کانال‌ها\n";
        $welcomeMessage .= "🔹 زمان‌بندی ارسال پست‌ها\n";
        $welcomeMessage .= "🔹 حالت دانر و ریشات\n";
        $welcomeMessage .= "🔹 کپی پست‌ها بین کانال‌ها\n";
        $welcomeMessage .= "🔹 مدیریت ادمین‌ها\n\n";
        $welcomeMessage .= "برای شروع، از دکمه‌های زیر استفاده کنید یا دستورات را وارد نمایید.";
        
        // Simple keyboard with main options
        $keyboard = [
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
            'keyboard' => [
                [
                    ['text' => '➕ افزودن کانال'],
                    ['text' => '📋 مدیریت کانال‌ها']
                ],
                [
                    ['text' => '⚙️ تنظیمات'],
                    ['text' => '❓ راهنما']
                ]
            ]
        ];
        
        $telegram->sendMessage($chatId, $welcomeMessage, replyMarkup: $keyboard);
    });

    $botCore->registerCommand('/help', function($message) use ($telegram) {
        $chatId = $message['chat']['id'];
        $helpMessage = "📚 راهنمای ربات مدیریت کانال:\n\n";
        $helpMessage .= "🔹 /start - شروع کار با ربات\n";
        $helpMessage .= "🔹 /help - نمایش این پیام\n";
        $helpMessage .= "🔹 /channels - مشاهده کانال‌های ثبت شده\n";
        $helpMessage .= "🔹 /add_channel - افزودن کانال جدید\n\n";
        $helpMessage .= "برای افزودن کانال، می‌توانید یک پیام از کانال مورد نظر را فوروارد کنید یا یوزرنیم/آیدی آن را ارسال نمایید.";
        
        $telegram->sendMessage($chatId, $helpMessage);
    });

    $botCore->registerCommand('/channels', function($message) use ($telegram, $channelHandler) {
        $userId = $message['from']['id'];
        $channels = $channelHandler->getUserChannels($userId);
        
        $chatId = $message['chat']['id'];
        if (empty($channels)) {
            $telegram->sendMessage($chatId, "❌ کانالی ثبت نشده است. برای افزودن کانال جدید، پیامی از کانال مورد نظر را فوروارد کنید یا از دکمه منو استفاده کنید.");
        } else {
            $channelList = "📋 لیست کانال‌های شما:\n\n";
            foreach ($channels as $channel) {
                $channelList .= "🔹 {$channel['title']}\n";
                $channelList .= "   آیدی: {$channel['telegram_channel_id']}\n";
                $channelList .= "   وضعیت: {$channel['status']}\n\n";
            }
            
            // Add inline buttons for each channel
            $inlineKeyboard = [
                'inline_keyboard' => []
            ];
            
            foreach ($channels as $channel) {
                $inlineKeyboard['inline_keyboard'][] = [
                    ['text' => $channel['title'], 'callback_data' => "manage_channel_{$channel['id']}"]
                ];
            }
            
            $telegram->sendMessage($chatId, $channelList, replyMarkup: $inlineKeyboard);
        }
    });

    // Register callback handlers
    $botCore->registerCallback('/^manage_channel_(\d+)$/', function($callbackQuery) use ($telegram) {
        $channelId = $callbackQuery['data'];
        $channelId = intval(substr($channelId, strlen('manage_channel_')));
        
        $message = $callbackQuery['message'];
        $chatId = $message['chat']['id'];
        $messageId = $message['message_id'];
        
        $responseMessage = "🔧 مدیریت کانال:\n";
        $responseMessage .= "کانال شناسه: {$channelId}\n\n";
        $responseMessage .= "انتخاب عملیات مورد نظر:";
        
        // Channel management options
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📝 ثبت پست', 'callback_data' => "post_normal_{$channelId}"],
                    ['text' => '🔄 دانر', 'callback_data' => "post_downer_{$channelId}"]
                ],
                [
                    ['text' => '📋 لیست پست‌ها', 'callback_data' => "list_posts_{$channelId}"],
                    ['text' => '⚙️ تنظیمات', 'callback_data' => "settings_{$channelId}"]
                ],
                [
                    ['text' => '❌ حذف کانال', 'callback_data' => "remove_channel_{$channelId}"]
                ]
            ]
        ];
        
        $telegram->editMessageText(
            $responseMessage,
            chatId: $chatId,
            messageId: $messageId,
            replyMarkup: $keyboard
        );
        
        // Answer the callback query
        $telegram->answerCallbackQuery($callbackQuery['id']);
    });

    // Handle text messages for channel addition
    $botCore->registerMessageHandler('/^(@[a-zA-Z0-9_]{5,32}|[a-zA-Z0-9_]{5,32})$/', function($message) use ($channelHandler) {
        $text = $message['text'];
        $channelHandler->addChannelByUsername($message, $text);
    });

    $botCore->registerMessageHandler('/^(-\d{10,})$/', function($message) use ($channelHandler) {
        $text = $message['text'];
        $channelId = intval($text);
        $channelHandler->addChannelById($message, $channelId);
    });

    // Handle start command with invite code
    $botCore->registerMessageHandler('/^\/start\s+invite_(\w+)$/', function($message) use ($adminHandler) {
        $matches = [];
        if (preg_match('/^\/start\s+invite_(\w+)$/', $message['text'], $matches)) {
            $inviteCode = $matches[1];
            $adminHandler->processInviteActivation($message, $inviteCode);
        }
    });

    // Process the incoming update
    $botCore->handleUpdate();
    
    // Success response for webhook
    http_response_code(200);
    echo "OK";
} catch (Exception $e) {
    // Log error
    error_log(date('Y-m-d H:i:s') . " - Webhook error: " . $e->getMessage() . "\n", 3, BASE_PATH . '/storage/logs/webhook.log');
    
    // Return error response
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}