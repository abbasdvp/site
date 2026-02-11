<?php

namespace Handlers;

use Core\Database;
use Core\TelegramAPI;
use Core\Validator;
use Models\Channel;
use Models\User;
use Repositories\ChannelRepository;
use Repositories\UserRepository;

/**
 * ChannelHandler class - Handles all channel-related operations
 */
class ChannelHandler
{
    private TelegramAPI $telegram;
    private ChannelRepository $channelRepository;
    private UserRepository $userRepository;
    private Validator $validator;

    /**
     * Constructor
     *
     * @param TelegramAPI $telegram
     */
    public function __construct(TelegramAPI $telegram)
    {
        $this->telegram = $telegram;
        $this->channelRepository = new ChannelRepository(Database::getInstance());
        $this->userRepository = new UserRepository(Database::getInstance());
        $this->validator = new Validator();
    }

    /**
     * Add a channel by forwarding a message from the channel
     *
     * @param array $message
     * @param int $channelId
     * @param string|null $channelUsername
     * @param string|null $channelTitle
     * @return bool
     */
    public function addChannelByForward(array $message, int $channelId, ?string $channelUsername, ?string $channelTitle): bool
    {
        try {
            $userId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$userId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Check if user exists, create if not
            $user = $this->userRepository->findByTelegramId($userId);
            if (!$user) {
                $user = $this->createUserFromMessage($message);
            }

            // Validate that the user is actually an admin of the channel
            if (!$this->validateChannelAdmin($channelId, $userId)) {
                $this->telegram->sendMessage($chatId, "❌ شما ادمین کانال {$channelTitle} نیستید یا ربات در کانال عضو نیست.");
                return false;
            }

            // Check if channel already exists for this user
            $existingChannel = $this->channelRepository->findByTelegramIdAndUserId($channelId, $user['id']);
            if ($existingChannel) {
                $this->telegram->sendMessage($chatId, "❌ این کانال قبلاً توسط شما اضافه شده است.");
                return false;
            }

            // Get channel info
            $channelInfo = $this->telegram->getChat($channelId);
            
            // Create new channel
            $channelData = [
                'telegram_channel_id' => $channelId,
                'username' => $channelInfo['username'] ?? $channelUsername,
                'title' => $channelInfo['title'] ?? $channelTitle,
                'description' => $channelInfo['description'] ?? null,
                'member_count' => $channelInfo['members_count'] ?? 0,
                'added_by_user_id' => $user['id'],
                'added_by_admin_id' => null,
                'status' => CHANNEL_STATUS_ACTIVE,
                'schedule_type' => SCHEDULE_TYPE_MANUAL,
                'post_repeat_mode' => REPEAT_MODE_DELETE_FROM_LIST,
                'created_at' => time(),
                'updated_at' => time()
            ];

            $channelIdCreated = $this->channelRepository->create($channelData);

            if ($channelIdCreated) {
                // Send success message with channel info
                $jalaliDate = $this->convertTimestampToJalali(time());
                $successMessage = "✅ کانال با موفقیت اضافه شد!\n\n";
                $successMessage .= "📌 عنوان: {$channelInfo['title']}\n";
                $successMessage .= "🆔 آیدی: {$channelId}\n";
                $successMessage .= "👤 یوزرنیم: @" . ($channelInfo['username'] ?? 'N/A') . "\n";
                $successMessage .= "👥 اعضا: " . number_format($channelInfo['members_count'] ?? 0) . " نفر\n";
                $successMessage .= "📅 تاریخ ثبت: {$jalaliDate['formatted']}\n\n";
                $successMessage .= "🔻 از طریق دکمه زیر وارد مدیریت کانال شوید:\n";

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🎯 ورود به مدیریت کانال', 'callback_data' => "manage_channel_{$channelIdCreated}"]
                        ]
                    ]
                ];

                $this->telegram->sendMessage($chatId, $successMessage, replyMarkup: $keyboard);
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ذخیره کانال در دیتابیس.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in addChannelByForward: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در افزودن کانال رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Add a channel by username
     *
     * @param array $message
     * @param string $username
     * @return bool
     */
    public function addChannelByUsername(array $message, string $username): bool
    {
        try {
            $userId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$userId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Normalize username
            if (strpos($username, '@') === 0) {
                $username = substr($username, 1);
            }

            // Check if user exists, create if not
            $user = $this->userRepository->findByTelegramId($userId);
            if (!$user) {
                $user = $this->createUserFromMessage($message);
            }

            // Get chat info by username
            try {
                $chatInfo = $this->telegram->getChat("@{$username}");
            } catch (\Exception $e) {
                $this->telegram->sendMessage($chatId, "❌ کانال با یوزرنیم @{$username} یافت نشد.");
                return false;
            }

            // Validate it's a channel
            if (($chatInfo['type'] ?? '') !== 'channel') {
                $this->telegram->sendMessage($chatId, "❌ یوزرنیم وارد شده مربوط به یک کانال نیست.");
                return false;
            }

            $channelId = $chatInfo['id'];

            // Validate that the user is actually an admin of the channel
            if (!$this->validateChannelAdmin($channelId, $userId)) {
                $this->telegram->sendMessage($chatId, "❌ شما ادمین کانال {$chatInfo['title']} نیستید یا ربات در کانال عضو نیست.");
                return false;
            }

            // Check if channel already exists for this user
            $existingChannel = $this->channelRepository->findByTelegramIdAndUserId($channelId, $user['id']);
            if ($existingChannel) {
                $this->telegram->sendMessage($chatId, "❌ این کانال قبلاً توسط شما اضافه شده است.");
                return false;
            }

            // Create new channel
            $channelData = [
                'telegram_channel_id' => $channelId,
                'username' => $chatInfo['username'] ?? $username,
                'title' => $chatInfo['title'] ?? '',
                'description' => $chatInfo['description'] ?? null,
                'member_count' => $chatInfo['members_count'] ?? 0,
                'added_by_user_id' => $user['id'],
                'added_by_admin_id' => null,
                'status' => CHANNEL_STATUS_ACTIVE,
                'schedule_type' => SCHEDULE_TYPE_MANUAL,
                'post_repeat_mode' => REPEAT_MODE_DELETE_FROM_LIST,
                'created_at' => time(),
                'updated_at' => time()
            ];

            $channelIdCreated = $this->channelRepository->create($channelData);

            if ($channelIdCreated) {
                // Send success message with channel info
                $jalaliDate = $this->convertTimestampToJalali(time());
                $successMessage = "✅ کانال با موفقیت اضافه شد!\n\n";
                $successMessage .= "📌 عنوان: {$chatInfo['title']}\n";
                $successMessage .= "🆔 آیدی: {$channelId}\n";
                $successMessage .= "👤 یوزرنیم: @" . ($chatInfo['username'] ?? 'N/A') . "\n";
                $successMessage .= "👥 اعضا: " . number_format($chatInfo['members_count'] ?? 0) . " نفر\n";
                $successMessage .= "📅 تاریخ ثبت: {$jalaliDate['formatted']}\n\n";
                $successMessage .= "🔻 از طریق دکمه زیر وارد مدیریت کانال شوید:\n";

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🎯 ورود به مدیریت کانال', 'callback_data' => "manage_channel_{$channelIdCreated}"]
                        ]
                    ]
                ];

                $this->telegram->sendMessage($chatId, $successMessage, replyMarkup: $keyboard);
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ذخیره کانال در دیتابیس.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in addChannelByUsername: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در افزودن کانال رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Add a channel by numeric ID
     *
     * @param array $message
     * @param int $numericId
     * @return bool
     */
    public function addChannelById(array $message, int $numericId): bool
    {
        try {
            $userId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$userId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Check if user exists, create if not
            $user = $this->userRepository->findByTelegramId($userId);
            if (!$user) {
                $user = $this->createUserFromMessage($message);
            }

            // Get chat info by ID
            try {
                $chatInfo = $this->telegram->getChat($numericId);
            } catch (\Exception $e) {
                $this->telegram->sendMessage($chatId, "❌ کانال با آیدی {$numericId} یافت نشد یا ربات در آن عضو نیست.");
                return false;
            }

            // Validate it's a channel
            if (($chatInfo['type'] ?? '') !== 'channel') {
                $this->telegram->sendMessage($chatId, "❌ آیدی وارد شده مربوط به یک کانال نیست.");
                return false;
            }

            $channelId = $chatInfo['id'];

            // Validate that the user is actually an admin of the channel
            if (!$this->validateChannelAdmin($channelId, $userId)) {
                $this->telegram->sendMessage($chatId, "❌ شما ادمین کانال {$chatInfo['title']} نیستید یا ربات در کانال عضو نیست.");
                return false;
            }

            // Check if channel already exists for this user
            $existingChannel = $this->channelRepository->findByTelegramIdAndUserId($channelId, $user['id']);
            if ($existingChannel) {
                $this->telegram->sendMessage($chatId, "❌ این کانال قبلاً توسط شما اضافه شده است.");
                return false;
            }

            // Create new channel
            $channelData = [
                'telegram_channel_id' => $channelId,
                'username' => $chatInfo['username'] ?? null,
                'title' => $chatInfo['title'] ?? '',
                'description' => $chatInfo['description'] ?? null,
                'member_count' => $chatInfo['members_count'] ?? 0,
                'added_by_user_id' => $user['id'],
                'added_by_admin_id' => null,
                'status' => CHANNEL_STATUS_ACTIVE,
                'schedule_type' => SCHEDULE_TYPE_MANUAL,
                'post_repeat_mode' => REPEAT_MODE_DELETE_FROM_LIST,
                'created_at' => time(),
                'updated_at' => time()
            ];

            $channelIdCreated = $this->channelRepository->create($channelData);

            if ($channelIdCreated) {
                // Send success message with channel info
                $jalaliDate = $this->convertTimestampToJalali(time());
                $successMessage = "✅ کانال با موفقیت اضافه شد!\n\n";
                $successMessage .= "📌 عنوان: {$chatInfo['title']}\n";
                $successMessage .= "🆔 آیدی: {$channelId}\n";
                $successMessage .= "👤 یوزرنیم: @" . ($chatInfo['username'] ?? 'N/A') . "\n";
                $successMessage .= "👥 اعضا: " . number_format($chatInfo['members_count'] ?? 0) . " نفر\n";
                $successMessage .= "📅 تاریخ ثبت: {$jalaliDate['formatted']}\n\n";
                $successMessage .= "🔻 از طریق دکمه زیر وارد مدیریت کانال شوید:\n";

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🎯 ورود به مدیریت کانال', 'callback_data' => "manage_channel_{$channelIdCreated}"]
                        ]
                    ]
                ];

                $this->telegram->sendMessage($chatId, $successMessage, replyMarkup: $keyboard);
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ذخیره کانال در دیتابیس.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in addChannelById: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در افزودن کانال رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Validate if a user is an admin of a channel
     *
     * @param int $channelId
     * @param int $userId
     * @return bool
     */
    public function validateChannelAdmin(int $channelId, int $userId): bool
    {
        try {
            // First check if bot is in the channel
            try {
                $this->telegram->getChatMember($channelId, BOT_ID);
            } catch (\Exception $e) {
                // Bot is not in the channel
                return false;
            }

            // Get chat administrators
            $administrators = $this->telegram->getChatAdministrators($channelId);

            // Check if the user is in the administrators list
            foreach ($administrators as $admin) {
                if ($admin['user']['id'] == $userId) {
                    // Additional check: ensure the user is not restricted
                    if (isset($admin['can_post_messages']) && $admin['can_post_messages'] == true) {
                        return true;
                    } else if (!isset($admin['can_post_messages']) && 
                               isset($admin['can_edit_messages']) && 
                               $admin['can_edit_messages'] == true) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            error_log("Error validating channel admin: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get channel info
     *
     * @param int $channelId
     * @return array|null
     */
    public function getChannelInfo(int $channelId): ?array
    {
        return $this->channelRepository->findById($channelId);
    }

    /**
     * Get all channels for a user
     *
     * @param int $userId
     * @return array
     */
    public function getUserChannels(int $userId): array
    {
        return $this->channelRepository->findByUserId($userId);
    }

    /**
     * Update channel settings
     *
     * @param int $channelId
     * @param array $settings
     * @return bool
     */
    public function updateChannelSettings(int $channelId, array $settings): bool
    {
        try {
            $channel = $this->channelRepository->findById($channelId);
            if (!$channel) {
                return false;
            }

            // Prepare update data
            $updateData = [];
            if (isset($settings['status'])) {
                $updateData['status'] = $settings['status'];
            }
            if (isset($settings['schedule_type'])) {
                $updateData['schedule_type'] = $settings['schedule_type'];
            }
            if (isset($settings['post_repeat_mode'])) {
                $updateData['post_repeat_mode'] = $settings['post_repeat_mode'];
            }
            if (isset($settings['auto_delete_status'])) {
                $updateData['auto_delete_status'] = $settings['auto_delete_status'];
            }
            if (isset($settings['auto_delete_minutes'])) {
                $updateData['auto_delete_minutes'] = $settings['auto_delete_minutes'];
            }
            if (isset($settings['periodic_minutes'])) {
                $updateData['periodic_minutes'] = $settings['periodic_minutes'];
            }
            if (isset($settings['fixed_times'])) {
                $updateData['fixed_times'] = json_encode($settings['fixed_times']);
            }
            
            $updateData['updated_at'] = time();

            return $this->channelRepository->update($channelId, $updateData) > 0;
        } catch (\Exception $e) {
            error_log("Error updating channel settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove a channel
     *
     * @param int $channelId
     * @param int $userId
     * @return bool
     */
    public function removeChannel(int $channelId, int $userId): bool
    {
        try {
            // Verify that the user owns this channel
            $channel = $this->channelRepository->findByTelegramIdAndUserId($channelId, $userId);
            if (!$channel) {
                return false;
            }

            // Update status to inactive instead of deleting
            return $this->channelRepository->update($channel['id'], [
                'status' => CHANNEL_STATUS_INACTIVE,
                'updated_at' => time()
            ]) > 0;
        } catch (\Exception $e) {
            error_log("Error removing channel: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create user from message if doesn't exist
     *
     * @param array $message
     * @return array
     */
    private function createUserFromMessage(array $message): array
    {
        $user = $message['from'] ?? [];
        $userData = [
            'telegram_id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'created_at' => time(),
            'last_activity' => time()
        ];

        return $this->userRepository->create($userData);
    }

    /**
     * Convert timestamp to Jalali date
     *
     * @param int $timestamp
     * @return array
     */
    private function convertTimestampToJalali(int $timestamp): array
    {
        // This is a simplified implementation
        // In a real application, you would use a proper Jalali conversion library
        $date = date('Y/m/d H:i', $timestamp);
        return [
            'formatted' => $date
        ];
    }
}