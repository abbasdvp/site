<?php
/**
 * Configuration file for Telegram Video Bot
 * This file allows customization of the bot's default settings
 */

// Default configuration values
return [
    'default_intro_video' => 'https://nyfer.hostme.top/default_intro.mp4',
    'default_outro_video' => 'https://nyfer.hostme.top/default_outro.mp4',
    'default_watermark_text' => 'User ID: {USER_ID}',
    'default_watermark_size' => 24,
    'default_watermark_color' => 'white',
    'default_watermark_position' => 'bottom-right', // top-left, top-right, bottom-left, bottom-right, center
    'max_file_size' => 50 * 1024 * 1024, // 50MB in bytes
    'supported_formats' => ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm'],
    'temp_dir' => sys_get_temp_dir(),
];