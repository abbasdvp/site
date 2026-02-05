<?php
// Telegram Media Bot with Admin Panel
// Token: 8566004755:AAHrtatqhtUdF8AtsgtdYH9O5Q5nN4vxWUc
// Admin IDs: 8386214226, 6596073646

// Configuration
define('BOT_TOKEN', '8566004755:AAHrtatqhtUdF8AtsgtdYH9O5Q5nN4vxWUc');
define('ADMIN_ID_1', 8386214226);
define('ADMIN_ID_2', 6596073646);
define('BASE_URL', 'https://api.telegram.org/bot' . BOT_TOKEN);

// Data storage files
define('MEDIA_FILE', __DIR__ . '/media.json');
define('ADMINS_FILE', __DIR__ . '/admins.json');
define('USERS_FILE', __DIR__ . '/users.json');
define('SETTINGS_FILE', __DIR__ . '/settings.json');

// Initialize data files if they don't exist
if (!file_exists(MEDIA_FILE)) file_put_contents(MEDIA_FILE, json_encode([]));
if (!file_exists(ADMINS_FILE)) {
    $initial_admins = [
        ADMIN_ID_1 => ['id' => ADMIN_ID_1, 'username' => 'admin1', 'joined_at' => time()],
        ADMIN_ID_2 => ['id' => ADMIN_ID_2, 'username' => 'admin2', 'joined_at' => time()]
    ];
    file_put_contents(ADMINS_FILE, json_encode($initial_admins));
}
if (!file_exists(USERS_FILE)) file_put_contents(USERS_FILE, json_encode([]));
if (!file_exists(SETTINGS_FILE)) {
    $default_settings = [
        'channels' => [
            'archive' => null,
            'main' => null,
            'backup' => null,
            'notification' => null
        ],
        'join_channels' => [],
        'default_cover' => null,
        'admin_invite_codes' => []
    ];
    file_put_contents(SETTINGS_FILE, json_encode($default_settings));
}

// Utility functions
function get_json_data($file) {
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

function save_json_data($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function apiRequest($method, $parameters) {
    $url = BASE_URL . '/' . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    return apiRequest('sendMessage', $params);
}

function sendPhoto($chat_id, $photo, $caption = '', $keyboard = null) {
    $params = [
        'chat_id' => $chat_id,
        'photo' => $photo,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    return apiRequest('sendPhoto', $params);
}

function forwardMessage($from_chat_id, $to_chat_id, $message_id) {
    return apiRequest('forwardMessage', [
        'chat_id' => $to_chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function getUserStatusInChannel($user_id, $channel_username) {
    $params = [
        'chat_id' => $channel_username,
        'user_id' => $user_id
    ];
    
    $result = apiRequest('getChatMember', $params);
    return isset($result['result']['status']) ? $result['result']['status'] : false;
}

// Get incoming updates
$update_response = file_get_contents('php://input');
$update = json_decode($update_response, true);

if (!$update) {
    exit;
}

// Extract update data
$message = $update['message'] ?? null;
$callback_query = $update['callback_query'] ?? null;
$inline_query = $update['inline_query'] ?? null;

if ($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';
    $message_id = $message['message_id'];
    
    // Check if user is admin
    $admins = get_json_data(ADMINS_FILE);
    $is_admin = isset($admins[$user_id]);
    
    // Add user to database if not exists
    $users = get_json_data(USERS_FILE);
    if (!isset($users[$user_id])) {
        $users[$user_id] = [
            'id' => $user_id,
            'first_name' => $message['from']['first_name'] ?? '',
            'username' => $message['from']['username'] ?? '',
            'joined_at' => time(),
            'last_seen' => time()
        ];
        save_json_data(USERS_FILE, $users);
    } else {
        $users[$user_id]['last_seen'] = time();
        save_json_data(USERS_FILE, $users);
    }
    
    // User states storage
    $states_file = __DIR__ . '/user_states.json';
    if (!file_exists($states_file)) {
        file_put_contents($states_file, json_encode([]));
    }
    
    $user_states = get_json_data($states_file);
    
    // Handle commands
    if ($text === '/start') {
        // Check if user has referral code
        $referral_code = explode(' ', $text)[1] ?? null;
        
        if ($referral_code) {
            // Check if referral code exists and add user as admin
            $settings = get_json_data(SETTINGS_FILE);
            if (isset($settings['admin_invite_codes'][$referral_code])) {
                $admins[$user_id] = [
                    'id' => $user_id,
                    'username' => $message['from']['username'] ?? '',
                    'joined_at' => time()
                ];
                save_json_data(ADMINS_FILE, $admins);
                
                // Send admin panel
                $admin_keyboard = [
                    'keyboard' => [
                        [['text' => '📤 انتشار مدیای جدید']],
                        [['text' => '⚙️ تنظیمات']],
                        [['text' => '📊 آمار']],
                        [['text' => '👥 مدیریت ادمین‌ها']]
                    ],
                    'resize_keyboard' => true
                ];
                
                sendMessage($chat_id, "✅ شما با موفقیت به عنوان ادمین اضافه شدید!\n\nپنل ادمین را مشاهده کنید:", $admin_keyboard);
            } else {
                $keyboard = [
                    'keyboard' => [
                        [['text' => '🎲 مدیای رندوم']]
                    ],
                    'resize_keyboard' => true
                ];
                
                sendMessage($chat_id, "سلام! خوش آمدید.\n\nبرای دریافت یک فیلم یا سریال تصادفی روی دکمه زیر کلیک کنید:", $keyboard);
            }
        } else {
            if ($is_admin) {
                $admin_keyboard = [
                    'keyboard' => [
                        [['text' => '📤 انتشار مدیای جدید']],
                        [['text' => '⚙️ تنظیمات']],
                        [['text' => '📊 آمار']],
                        [['text' => '👥 مدیریت ادمین‌ها']]
                    ],
                    'resize_keyboard' => true
                ];
                
                sendMessage($chat_id, "پنل ادمین را مشاهده کنید:", $admin_keyboard);
            } else {
                $keyboard = [
                    'keyboard' => [
                        [['text' => '🎲 مدیای رندوم']]
                    ],
                    'resize_keyboard' => true
                ];
                
                sendMessage($chat_id, "سلام! خوش آمدید.\n\nبرای دریافت یک فیلم یا سریال تصادفی روی دکمه زیر کلیک کنید:", $keyboard);
            }
        }
    } elseif ($text === '🎲 مدیای رندوم') {
        $media_list = get_json_data(MEDIA_FILE);
        
        if (empty($media_list)) {
            sendMessage($chat_id, "متاسفانه هنوز هیچ فیلم یا سریالی منتشر نشده است.");
        } else {
            $random_key = array_rand($media_list);
            $media = $media_list[$random_key];
            
            // Create inline keyboard for download
            $inline_keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📥 دریافت فیلم', 'callback_data' => 'download_' . $random_key]
                    ]
                ]
            ];
            
            // Send cover photo
            if (isset($media['cover'])) {
                sendPhoto($chat_id, $media['cover'], $media['description'], $inline_keyboard);
            } else {
                // Use default cover if available
                $settings = get_json_data(SETTINGS_FILE);
                if ($settings['default_cover']) {
                    sendPhoto($chat_id, $settings['default_cover'], $media['description'], $inline_keyboard);
                } else {
                    sendMessage($chat_id, $media['description'], $inline_keyboard);
                }
            }
            
            // Schedule media deletion after 30 seconds
            $delete_info = [
                'chat_id' => $chat_id,
                'message_ids' => [], // Will be updated when media is sent
                'scheduled_time' => time() + 30
            ];
            
            $deletion_file = __DIR__ . '/scheduled_deletions.json';
            $deletions = get_json_data($deletion_file);
            $deletions[] = $delete_info;
            save_json_data($deletion_file, $deletions);
        }
    } elseif ($is_admin && $text === '📤 انتشار مدیای جدید') {
        $user_states[$user_id]['state'] = 'waiting_for_media';
        save_json_data($states_file, $user_states);
        
        sendMessage($chat_id, " awesome! لطفاً فیلم یا سریال مورد نظر خود را ارسال کنید (می‌توانید فوروارد کنید):");
    } elseif ($is_admin && $text === '⚙️ تنظیمات') {
        $settings = get_json_data(SETTINGS_FILE);
        
        $settings_text = "🔧 تنظیمات ربات:\n\n";
        $settings_text .= "چنل بایگانی: " . ($settings['channels']['archive'] ?? 'تنظیم نشده') . "\n";
        $settings_text .= "چنل اصلی: " . ($settings['channels']['main'] ?? 'تنظیم نشده') . "\n";
        $settings_text .= "چنل پشتیبان: " . ($settings['channels']['backup'] ?? 'تنظیم نشده') . "\n";
        $settings_text .= "چنل اعلانات: " . ($settings['channels']['notification'] ?? 'تنظیم نشده') . "\n";
        $settings_text .= "کاور پیش‌فرض: " . ($settings['default_cover'] ? 'تنظیم شده' : 'تنظیم نشده') . "\n\n";
        
        $settings_text .= "چنل‌های عضویت اجباری:\n";
        foreach ($settings['join_channels'] as $channel) {
            $settings_text .= "- {$channel['title']}: {$channel['id']}\n";
        }
        
        $settings_keyboard = [
            'keyboard' => [
                [['text' => '🔄 به‌روزرسانی چنل بایگانی']],
                [['text' => '🔄 به‌روزرسانی چنل اصلی']],
                [['text' => '🔄 به‌روزرسانی چنل پشتیبان']],
                [['text' => '🔄 به‌روزرسانی چنل اعلانات']],
                [['text' => '🖼 تنظیم کاور پیش‌فرض']],
                [['text' => '➕ افزودن چنل عضویت اجباری']],
                [['text' => '🗑 حذف چنل عضویت اجباری']],
                [['text' => '🏠 بازگشت به منو']]
            ],
            'resize_keyboard' => true
        ];
        
        sendMessage($chat_id, $settings_text, $settings_keyboard);
    } elseif ($is_admin && $text === '📊 آمار') {
        $users = get_json_data(USERS_FILE);
        $media = get_json_data(MEDIA_FILE);
        $admins = get_json_data(ADMINS_FILE);
        
        $stats_text = "📈 آمار ربات:\n\n";
        $stats_text .= "👥 کل کاربران: " . count($users) . "\n";
        $stats_text .= "🎬 کل فیلم‌ها/سریال‌ها: " . count($media) . "\n";
        $stats_text .= "👑 کل ادمین‌ها: " . count($admins) . "\n";
        
        sendMessage($chat_id, $stats_text);
    } elseif ($is_admin && $text === '👥 مدیریت ادمین‌ها') {
        if ($user_id == ADMIN_ID_1 || $user_id == ADMIN_ID_2) {
            $admin_manage_keyboard = [
                'keyboard' => [
                    [['text' => '🔗 ایجاد لینک دعوت ادمین']],
                    [['text' => '🗑 حذف ادمین']],
                    [['text' => '📋 لیست ادمین‌ها']],
                    [['text' => '🏠 بازگشت به منو']]
                ],
                'resize_keyboard' => true
            ];
            
            sendMessage($chat_id, "مدیریت ادمین‌ها:", $admin_manage_keyboard);
        } else {
            sendMessage($chat_id, "❌ فقط ادمین‌های اصلی می‌توانند از این قابلیت استفاده کنند.");
        }
    } elseif ($is_admin && $text === '🏠 بازگشت به منو') {
        $admin_keyboard = [
            'keyboard' => [
                [['text' => '📤 انتشار مدیای جدید']],
                [['text' => '⚙️ تنظیمات']],
                [['text' => '📊 آمار']],
                [['text' => '👥 مدیریت ادمین‌ها']]
            ],
            'resize_keyboard' => true
        ];
        
        sendMessage($chat_id, "منوی ادمین:", $admin_keyboard);
    } elseif ($is_admin && $text === '🔄 به‌روزرسانی چنل بایگانی') {
        $user_states[$user_id]['state'] = 'waiting_for_archive_channel';
        save_json_data($states_file, $user_states);
        
        sendMessage($chat_id, "لطفاً چنل بایگانی را فوروارد کنید یا لینک آن را ارسال کنید:");
    } elseif ($is_admin && $text === '🔄 به‌روزرسانی چنل اصلی') {
        $user_states[$user_id]['state'] = 'waiting_for_main_channel';
        save_json_data($states_file, $user_states);
        
        sendMessage($chat_id, "لطفاً چنل اصلی را فوروارد کنید یا لینک آن را ارسال کنید:");
    } elseif ($is_admin && $text === '🔄 به‌روزرسانی چنل پشتیبان') {
        $user_states[$user_id]['state'] = 'waiting_for_backup_channel';
        save_json_data($states_file, $user_states);
        
        sendMessage($chat_id, "لطفاً چنل پشتیبان را فوروارد کنید یا لینک آن را ارسال کنید:");
    } elseif ($is_admin && $text === '🔄 به‌روزرسانی چنل اعلانات') {
        $user_states[$user_id]['state'] = 'waiting_for_notification_channel';
        save_json_data($states_file, $user_states);
        
        sendMessage($chat_id, "لطفاً چنل اعلانات را فوروارد کنید یا لینک آن را ارسال کنید:");
    } elseif ($is_admin && $text === '🖼 تنظیم کاور پیش‌فرض') {
        $user_states[$user_id]['state'] = 'waiting_for_default_cover';
        save_json_data($states_file, $user_states);
        
        sendMessage($chat_id, "لطفاً عکس کاور پیش‌فرض را ارسال کنید:");
    } elseif ($is_admin && $text === '➕ افزودن چنل عضویت اجباری') {
        $user_states[$user_id]['state'] = 'waiting_for_join_channel';
        save_json_data($states_file, $user_states);
        
        sendMessage($chat_id, "لطفاً چنل عضویت اجباری را فوروارد کنید یا لینک آن را ارسال کنید و سپس عنوان آن را وارد کنید:");
    } elseif ($is_admin && $text === '🔗 ایجاد لینک دعوت ادمین') {
        // Generate random invite code
        $invite_code = bin2hex(random_bytes(8));
        
        $settings = get_json_data(SETTINGS_FILE);
        $settings['admin_invite_codes'][$invite_code] = [
            'created_by' => $user_id,
            'created_at' => time()
        ];
        save_json_data(SETTINGS_FILE, $settings);
        
        // Get bot username for the invite link
        $getMe = apiRequest('getMe', []);
        $bot_username = $getMe['result']['username'] ?? 'username_not_found';
        $invite_link = "https://t.me/" . $bot_username . "?start=" . $invite_code;
        
        sendMessage($chat_id, "✅ لینک دعوت ادمین ایجاد شد:\n{$invite_link}\n\nاین لینک فقط یک بار قابل استفاده است.");
    } elseif ($is_admin && $text === '📋 لیست ادمین‌ها') {
        $admins = get_json_data(ADMINS_FILE);
        $admin_list = "👥 لیست ادمین‌ها:\n\n";
        
        foreach ($admins as $admin) {
            $admin_list .= "• {$admin['username']} ({$admin['id']})\n";
        }
        
        sendMessage($chat_id, $admin_list);
    } else {
        // Handle user states
        if (isset($user_states[$user_id])) {
            $state = $user_states[$user_id]['state'] ?? null;
            
            switch ($state) {
                case 'waiting_for_media':
                    if (isset($message['video']) || isset($message['document'])) {
                        $user_states[$user_id]['media'] = $message;
                        $user_states[$user_id]['state'] = 'waiting_for_description';
                        save_json_data($states_file, $user_states);
                        
                        sendMessage($chat_id, "عالی! حالا لطفاً توضیحات مربوط به این فیلم/سریال را ارسال کنید:");
                    } elseif (isset($message['forward_date'])) {
                        $user_states[$user_id]['media'] = $message;
                        $user_states[$user_id]['state'] = 'waiting_for_description';
                        save_json_data($states_file, $user_states);
                        
                        sendMessage($chat_id, "عالی! حالا لطفاً توضیحات مربوط به این فیلم/سریال را ارسال کنید:");
                    } else {
                        sendMessage($chat_id, "لطفاً یک فیلم یا فایل ویدیویی ارسال کنید یا آن را فوروارد کنید.");
                    }
                    break;
                    
                case 'waiting_for_description':
                    $user_states[$user_id]['description'] = $text;
                    $user_states[$user_id]['state'] = 'waiting_for_cover';
                    save_json_data($states_file, $user_states);
                    
                    // Create keyboard for cover selection
                    $cover_keyboard = [
                        'keyboard' => [
                            [['text' => '🖼 استفاده از کاور پیش‌فرض']],
                            [['text' => '🏠 لغو']]
                        ],
                        'resize_keyboard' => true
                    ];
                    
                    sendMessage($chat_id, "حالا لطفاً عکس کاور مربوط به این فیلم/سریال را ارسال کنید یا از کاور پیش‌فرض استفاده کنید:", $cover_keyboard);
                    break;
                    
                case 'waiting_for_cover':
                    if ($text === '🖼 استفاده از کاور پیش‌فرض') {
                        $settings = get_json_data(SETTINGS_FILE);
                        
                        if ($settings['default_cover']) {
                            // Save media to database
                            $media_list = get_json_data(MEDIA_FILE);
                            $media_id = count($media_list) + 1;
                            
                            $media_list[$media_id] = [
                                'id' => $media_id,
                                'media' => $user_states[$user_id]['media'],
                                'description' => $user_states[$user_id]['description'],
                                'cover' => $settings['default_cover'],
                                'uploaded_at' => time()
                            ];
                            
                            save_json_data(MEDIA_FILE, $media_list);
                            
                            // Send to channels
                            sendMediaToChannels($media_id, $media_list[$media_id], $settings);
                            
                            // Reset state
                            unset($user_states[$user_id]);
                            save_json_data($states_file, $user_states);
                            
                            sendMessage($chat_id, "✅ فیلم/سریال با موفقیت اضافه شد!");
                        } else {
                            sendMessage($chat_id, "❌ کاور پیش‌فرض تنظیم نشده است. لطفاً یک عکس ارسال کنید:");
                        }
                    } elseif (isset($message['photo'])) {
                        // Get largest photo size
                        $photo = end($message['photo']);
                        
                        // Save media to database
                        $media_list = get_json_data(MEDIA_FILE);
                        $media_id = count($media_list) + 1;
                        
                        $media_list[$media_id] = [
                            'id' => $media_id,
                            'media' => $user_states[$user_id]['media'],
                            'description' => $user_states[$user_id]['description'],
                            'cover' => $photo['file_id'],
                            'uploaded_at' => time()
                        ];
                        
                        save_json_data(MEDIA_FILE, $media_list);
                        
                        // Send to channels
                        sendMediaToChannels($media_id, $media_list[$media_id], get_json_data(SETTINGS_FILE));
                        
                        // Reset state
                        unset($user_states[$user_id]);
                        save_json_data($states_file, $user_states);
                        
                        sendMessage($chat_id, "✅ فیلم/سریال با موفقیت اضافه شد!");
                    } else {
                        sendMessage($chat_id, "لطفاً یک عکس ارسال کنید یا از کاور پیش‌فرض استفاده کنید.");
                    }
                    break;
                    
                case 'waiting_for_archive_channel':
                    // Process channel info
                    if (isset($message['forward_from_chat']) && $message['forward_from_chat']['type'] === 'channel') {
                        $channel_id = $message['forward_from_chat']['id'];
                        $settings = get_json_data(SETTINGS_FILE);
                        $settings['channels']['archive'] = $channel_id;
                        save_json_data(SETTINGS_FILE, $settings);
                        
                        unset($user_states[$user_id]);
                        save_json_data($states_file, $user_states);
                        
                        sendMessage($chat_id, "✅ چنل بایگانی با موفقیت تنظیم شد.");
                    } else {
                        // Assume it's a channel link
                        if (preg_match('/^https?:\/\/t\.me\/(.+)$/', $text, $matches)) {
                            $channel_username = $matches[1];
                            $settings = get_json_data(SETTINGS_FILE);
                            $settings['channels']['archive'] = '@' . $channel_username;
                            save_json_data(SETTINGS_FILE, $settings);
                            
                            unset($user_states[$user_id]);
                            save_json_data($states_file, $user_states);
                            
                            sendMessage($chat_id, "✅ چنل بایگانی با موفقیت تنظیم شد.");
                        } else {
                            sendMessage($chat_id, "لطفاً چنل را فوروارد کنید یا لینک آن را ارسال کنید.");
                        }
                    }
                    break;
                    
                case 'waiting_for_main_channel':
                    if (isset($message['forward_from_chat']) && $message['forward_from_chat']['type'] === 'channel') {
                        $channel_id = $message['forward_from_chat']['id'];
                        $settings = get_json_data(SETTINGS_FILE);
                        $settings['channels']['main'] = $channel_id;
                        save_json_data(SETTINGS_FILE, $settings);
                        
                        unset($user_states[$user_id]);
                        save_json_data($states_file, $user_states);
                        
                        sendMessage($chat_id, "✅ چنل اصلی با موفقیت تنظیم شد.");
                    } else {
                        if (preg_match('/^https?:\/\/t\.me\/(.+)$/', $text, $matches)) {
                            $channel_username = $matches[1];
                            $settings = get_json_data(SETTINGS_FILE);
                            $settings['channels']['main'] = '@' . $channel_username;
                            save_json_data(SETTINGS_FILE, $settings);
                            
                            unset($user_states[$user_id]);
                            save_json_data($states_file, $user_states);
                            
                            sendMessage($chat_id, "✅ چنل اصلی با موفقیت تنظیم شد.");
                        } else {
                            sendMessage($chat_id, "لطفاً چنل را فوروارد کنید یا لینک آن را ارسال کنید.");
                        }
                    }
                    break;
                    
                case 'waiting_for_backup_channel':
                    if (isset($message['forward_from_chat']) && $message['forward_from_chat']['type'] === 'channel') {
                        $channel_id = $message['forward_from_chat']['id'];
                        $settings = get_json_data(SETTINGS_FILE);
                        $settings['channels']['backup'] = $channel_id;
                        save_json_data(SETTINGS_FILE, $settings);
                        
                        unset($user_states[$user_id]);
                        save_json_data($states_file, $user_states);
                        
                        sendMessage($chat_id, "✅ چنل پشتیبان با موفقیت تنظیم شد.");
                    } else {
                        if (preg_match('/^https?:\/\/t\.me\/(.+)$/', $text, $matches)) {
                            $channel_username = $matches[1];
                            $settings = get_json_data(SETTINGS_FILE);
                            $settings['channels']['backup'] = '@' . $channel_username;
                            save_json_data(SETTINGS_FILE, $settings);
                            
                            unset($user_states[$user_id]);
                            save_json_data($states_file, $user_states);
                            
                            sendMessage($chat_id, "✅ چنل پشتیبان با موفقیت تنظیم شد.");
                        } else {
                            sendMessage($chat_id, "لطفاً چنل را فوروارد کنید یا لینک آن را ارسال کنید.");
                        }
                    }
                    break;
                    
                case 'waiting_for_notification_channel':
                    if (isset($message['forward_from_chat']) && $message['forward_from_chat']['type'] === 'channel') {
                        $channel_id = $message['forward_from_chat']['id'];
                        $settings = get_json_data(SETTINGS_FILE);
                        $settings['channels']['notification'] = $channel_id;
                        save_json_data(SETTINGS_FILE, $settings);
                        
                        unset($user_states[$user_id]);
                        save_json_data($states_file, $user_states);
                        
                        sendMessage($chat_id, "✅ چنل اعلانات با موفقیت تنظیم شد.");
                    } else {
                        if (preg_match('/^https?:\/\/t\.me\/(.+)$/', $text, $matches)) {
                            $channel_username = $matches[1];
                            $settings = get_json_data(SETTINGS_FILE);
                            $settings['channels']['notification'] = '@' . $channel_username;
                            save_json_data(SETTINGS_FILE, $settings);
                            
                            unset($user_states[$user_id]);
                            save_json_data($states_file, $user_states);
                            
                            sendMessage($chat_id, "✅ چنل اعلانات با موفقیت تنظیم شد.");
                        } else {
                            sendMessage($chat_id, "لطفاً چنل را فوروارد کنید یا لینک آن را ارسال کنید.");
                        }
                    }
                    break;
                    
                case 'waiting_for_default_cover':
                    if (isset($message['photo'])) {
                        $photo = end($message['photo']);
                        $settings = get_json_data(SETTINGS_FILE);
                        $settings['default_cover'] = $photo['file_id'];
                        save_json_data(SETTINGS_FILE, $settings);
                        
                        unset($user_states[$user_id]);
                        save_json_data($states_file, $user_states);
                        
                        sendMessage($chat_id, "✅ کاور پیش‌فرض با موفقیت تنظیم شد.");
                    } else {
                        sendMessage($chat_id, "لطفاً یک عکس ارسال کنید.");
                    }
                    break;
                    
                case 'waiting_for_join_channel':
                    if (isset($message['forward_from_chat']) && $message['forward_from_chat']['type'] === 'channel') {
                        $user_states[$user_id]['temp_channel'] = $message['forward_from_chat'];
                        $user_states[$user_id]['state'] = 'waiting_for_join_channel_title';
                        save_json_data($states_file, $user_states);
                        
                        sendMessage($chat_id, "حالا لطفاً عنوان چنل را وارد کنید:");
                    } else {
                        if (preg_match('/^https?:\/\/t\.me\/(.+)$/', $text, $matches)) {
                            $user_states[$user_id]['temp_channel_link'] = '@' . $matches[1];
                            $user_states[$user_id]['state'] = 'waiting_for_join_channel_title';
                            save_json_data($states_file, $user_states);
                            
                            sendMessage($chat_id, "حالا لطفاً عنوان چنل را وارد کنید:");
                        } else {
                            sendMessage($chat_id, "لطفاً چنل را فوروارد کنید یا لینک آن را ارسال کنید.");
                        }
                    }
                    break;
                    
                case 'waiting_for_join_channel_title':
                    $settings = get_json_data(SETTINGS_FILE);
                    
                    if (isset($user_states[$user_id]['temp_channel'])) {
                        $channel = $user_states[$user_id]['temp_channel'];
                        $settings['join_channels'][] = [
                            'id' => $channel['id'],
                            'title' => $text,
                            'username' => $channel['username'] ?? null
                        ];
                    } elseif (isset($user_states[$user_id]['temp_channel_link'])) {
                        $settings['join_channels'][] = [
                            'id' => $user_states[$user_id]['temp_channel_link'],
                            'title' => $text,
                            'username' => str_replace('@', '', $user_states[$user_id]['temp_channel_link'])
                        ];
                    }
                    
                    save_json_data(SETTINGS_FILE, $settings);
                    
                    unset($user_states[$user_id]);
                    save_json_data($states_file, $user_states);
                    
                    sendMessage($chat_id, "✅ چنل عضویت اجباری با موفقیت اضافه شد.");
                    break;
            }
        }
    }
} elseif ($callback_query) {
    $user_id = $callback_query['from']['id'];
    $chat_id = $callback_query['message']['chat']['id'];
    $data = $callback_query['data'];
    $message_id = $callback_query['message']['message_id'];
    
    // Check if user is admin
    $admins = get_json_data(ADMINS_FILE);
    $is_admin = isset($admins[$user_id]);
    
    if (strpos($data, 'download_') === 0) {
        $media_id = substr($data, 9); // Remove 'download_' prefix
        $media_list = get_json_data(MEDIA_FILE);
        
        if (isset($media_list[$media_id])) {
            $media = $media_list[$media_id];
            
            // Check if user joined required channels
            $settings = get_json_data(SETTINGS_FILE);
            $required_channels = $settings['join_channels'];
            
            $not_joined = [];
            foreach ($required_channels as $channel) {
                $status = getUserStatusInChannel($user_id, $channel['id']);
                if (!$status || !in_array($status, ['member', 'administrator', 'creator'])) {
                    $not_joined[] = $channel;
                }
            }
            
            if (!empty($not_joined)) {
                // Create join channel buttons
                $inline_keyboard = [
                    'inline_keyboard' => []
                ];
                
                foreach ($not_joined as $channel) {
                    $inline_keyboard['inline_keyboard'][] = [
                        ['text' => "عضویت در {$channel['title']}", 'url' => "https://t.me/{$channel['username']}"]
                    ];
                }
                
                $inline_keyboard['inline_keyboard'][] = [
                    ['text' => '✅ عضو شدم', 'callback_data' => 'check_join_' . $media_id]
                ];
                
                $channels_list = implode("\n", array_map(function($ch) { return "• {$ch['title']}"; }, $not_joined));
                
                apiRequest('editMessageText', [
                    'chat_id' => $chat_id,
                    'message_id' => $message_id,
                    'text' => "❌ برای دریافت این فیلم باید در چنل‌های زیر عضو شوید:\n\n{$channels_list}\n\nبعد از عضویت روی دکمه 'عضو شدم' بزنید:",
                    'reply_markup' => json_encode($inline_keyboard)
                ]);
            } else {
                // User joined all channels, send message to go to bot for video
                $getMe = apiRequest('getMe', []);
                $bot_username = $getMe['result']['username'] ?? 'username_not_found';
                
                $go_to_bot_keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📥 دریافت فیلم از ربات', 'url' => "https://t.me/{$bot_username}"]
                        ]
                    ]
                ];

                apiRequest('editMessageText', [
                    'chat_id' => $chat_id,
                    'message_id' => $message_id,
                    'text' => "✅ شما در تمام چنل‌های الزامی عضو شده‌اید!\n\nاکنون می‌توانید فیلم را از طریق ربات دریافت کنید:",
                    'reply_markup' => json_encode($go_to_bot_keyboard)
                ]);
            }
        } else {
            sendMessage($chat_id, "❌ فیلم مورد نظر یافت نشد.");
        }
    } elseif (strpos($data, 'check_join_') === 0) {
        $media_id = substr($data, 11); // Remove 'check_join_' prefix
        $media_list = get_json_data(MEDIA_FILE);
        
        if (isset($media_list[$media_id])) {
            // Check if user joined required channels
            $settings = get_json_data(SETTINGS_FILE);
            $required_channels = $settings['join_channels'];
            
            $not_joined = [];
            foreach ($required_channels as $channel) {
                $status = getUserStatusInChannel($user_id, $channel['id']);
                if (!$status || !in_array($status, ['member', 'administrator', 'creator'])) {
                    $not_joined[] = $channel;
                }
            }
            
            if (empty($not_joined)) {
                // User joined all channels, send media
                $media_content = $media_list[$media_id]['media'];
                
                if (isset($media_content['video'])) {
                    apiRequest('sendVideo', [
                        'chat_id' => $chat_id,
                        'video' => $media_content['video']['file_id'],
                        'caption' => '📥 فیلم درخواستی شما'
                    ]);
                } elseif (isset($media_content['document'])) {
                    apiRequest('sendDocument', [
                        'chat_id' => $chat_id,
                        'document' => $media_content['document']['file_id'],
                        'caption' => '📥 فیلم درخواستی شما'
                    ]);
                } else {
                    sendMessage($chat_id, "❌ متاسفانه فایل ویدیویی یافت نشد.");
                }
                
                // User joined all channels, send message to go to bot for video
                $getMe = apiRequest('getMe', []);
                $bot_username = $getMe['result']['username'] ?? 'username_not_found';
                
                $go_to_bot_keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📥 دریافت فیلم از ربات', 'url' => "https://t.me/{$bot_username}"]
                        ]
                    ]
                ];

                // Update the original message
                apiRequest('editMessageText', [
                    'chat_id' => $chat_id,
                    'message_id' => $message_id,
                    'text' => "✅ عضویت شما تأیید شد! اکنون می‌توانید فیلم را از طریق ربات دریافت کنید:",
                    'reply_markup' => json_encode($go_to_bot_keyboard)
                ]);
            } else {
                $channels_list = implode("\n", array_map(function($ch) { return "• {$ch['title']}"; }, $not_joined));
                
                apiRequest('answerCallbackQuery', [
                    'callback_query_id' => $callback_query['id'],
                    'text' => "هنوز در همه چنل‌ها عضو نشده‌اید: {$channels_list}",
                    'show_alert' => true
                ]);
            }
        } else {
            apiRequest('answerCallbackQuery', [
                'callback_query_id' => $callback_query['id'],
                'text' => "فیلم مورد نظر یافت نشد.",
                'show_alert' => true
            ]);
        }
    }
}

// Function to send media to channels
function sendMediaToChannels($media_id, $media, $settings) {
    // Create inline keyboard for download
    $inline_keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 دریافت فیلم', 'callback_data' => 'download_' . $media_id]
            ]
        ]
    ];
    
    // Send to archive channel
    if ($settings['channels']['archive']) {
        if (isset($media['cover'])) {
            sendPhoto($settings['channels']['archive'], $media['cover'], $media['description'], $inline_keyboard);
        } else {
            $default_cover = $settings['default_cover'] ?? null;
            if ($default_cover) {
                sendPhoto($settings['channels']['archive'], $default_cover, $media['description'], $inline_keyboard);
            } else {
                sendMessage($settings['channels']['archive'], $media['description'], $inline_keyboard);
            }
        }
        
        // Also send the actual media file
        $media_content = $media['media'];
        if (isset($media_content['video'])) {
            apiRequest('sendVideo', [
                'chat_id' => $settings['channels']['archive'],
                'video' => $media_content['video']['file_id']
            ]);
        } elseif (isset($media_content['document'])) {
            apiRequest('sendDocument', [
                'chat_id' => $settings['channels']['archive'],
                'document' => $media_content['document']['file_id']
            ]);
        }
    }
    
    // Send to main channel
    if ($settings['channels']['main']) {
        if (isset($media['cover'])) {
            sendPhoto($settings['channels']['main'], $media['cover'], $media['description'], $inline_keyboard);
        } else {
            $default_cover = $settings['default_cover'] ?? null;
            if ($default_cover) {
                sendPhoto($settings['channels']['main'], $default_cover, $media['description'], $inline_keyboard);
            } else {
                sendMessage($settings['channels']['main'], $media['description'], $inline_keyboard);
            }
        }
    }
    
    // Send to notification channel
    if ($settings['channels']['notification']) {
        if (isset($media['cover'])) {
            sendPhoto($settings['channels']['notification'], $media['cover'], $media['description'], $inline_keyboard);
        } else {
            $default_cover = $settings['default_cover'] ?? null;
            if ($default_cover) {
                sendPhoto($settings['channels']['notification'], $default_cover, $media['description'], $inline_keyboard);
            } else {
                sendMessage($settings['channels']['notification'], $media['description'], $inline_keyboard);
            }
        }
    }
    
    // Forward to backup channel
    if ($settings['channels']['backup'] && $settings['channels']['main']) {
        // We would need to track the message ID from sending to main channel
        // For now, we'll skip forwarding since we don't have the message ID
    }
}

// Clean up scheduled deletions
$deletion_file = __DIR__ . '/scheduled_deletions.json';
if (file_exists($deletion_file)) {
    $deletions = get_json_data($deletion_file);
    $remaining_deletions = [];
    
    foreach ($deletions as $deletion) {
        if ($deletion['scheduled_time'] <= time()) {
            // Delete messages
            foreach ($deletion['message_ids'] as $msg_id) {
                apiRequest('deleteMessage', [
                    'chat_id' => $deletion['chat_id'],
                    'message_id' => $msg_id
                ]);
            }
            
            // Send reminder message
            sendMessage(
                $deletion['chat_id'],
                "⚠️ پیام شما به دلایل امنیتی پس از 30 ثانیه حذف شد.\n\nاگر نیاز به دریافت مجدد دارید، می‌توانید از طریق دکمه‌های روبات درخواست کنید.\nهمچنین توصیه می‌شود فیلم را در فضای ذخیره‌سازی خود ذخیره کنید.",
                [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔄 دریافت مجدد', 'callback_data' => 'download_' . rand(1, 100)]
                        ]
                    ]
                ]
            );
        } else {
            $remaining_deletions[] = $deletion;
        }
    }
    
    save_json_data($deletion_file, $remaining_deletions);
}

echo "OK";
?>