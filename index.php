<?php
// Telegram Bot Webhook Handler

// Load the main bot class
require_once __DIR__ . '/telegram_video_bot.php';

// Initialize and run the bot
$botToken = '8296306367:AAHh2cS7PW1MfYrL-VsOOpPHbu29csvcEGU';
$bot = new TelegramVideoBot($botToken);
$bot->handleUpdate();