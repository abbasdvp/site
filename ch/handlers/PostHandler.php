<?php

namespace Handlers;

use Core\Database;
use Core\TelegramAPI;
use Core\Validator;
use Models\Post;
use Models\User;
use Repositories\PostRepository;
use Repositories\UserRepository;
use Services\SchedulerService;
use Services\PostFormatter;

/**
 * PostHandler class - Handles all post-related operations
 */
class PostHandler
{
    private TelegramAPI $telegram;
    private PostRepository $postRepository;
    private UserRepository $userRepository;
    private SchedulerService $schedulerService;
    private PostFormatter $postFormatter;
    private Validator $validator;

    /**
     * Constructor
     *
     * @param TelegramAPI $telegram
     */
    public function __construct(TelegramAPI $telegram)
    {
        $this->telegram = $telegram;
        $this->postRepository = new PostRepository(Database::getInstance());
        $this->userRepository = new UserRepository(Database::getInstance());
        $this->schedulerService = new SchedulerService($telegram);
        $this->postFormatter = new PostFormatter();
        $this->validator = new Validator();
    }

    /**
     * Register a normal post
     *
     * @param array $message
     * @param int $channelId
     * @return bool
     */
    public function registerNormalPost(array $message, int $channelId): bool
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

            // Extract post content from message
            $postData = $this->extractPostData($message);

            // Prepare post data for database
            $postToSave = [
                'channel_id' => $channelId,
                'message_id_in_bot' => $message['message_id'],
                'file_id' => $postData['file_id'] ?? null,
                'file_unique_id' => $postData['file_unique_id'] ?? null,
                'media_type' => $postData['media_type'],
                'caption' => $postData['caption'] ?? null,
                'caption_entities' => $postData['caption_entities'] ? json_encode($postData['caption_entities']) : null,
                'parse_mode' => PARSE_MODE_HTML,
                'type' => POST_TYPE_NORMAL,
                'scheduled_time' => $this->calculateScheduledTime($channelId),
                'status' => POST_STATUS_PENDING,
                'created_by' => $user['id'],
                'created_at' => time(),
                'updated_at' => time()
            ];

            // Save post to database
            $postId = $this->postRepository->create($postToSave);

            if ($postId) {
                // Format scheduled time
                $jalaliTime = $this->convertTimestampToJalali($postToSave['scheduled_time']);
                
                // Send confirmation message
                $confirmationMessage = "✅ پست با موفقیت ثبت شد!\n";
                $confirmationMessage .= "📌 کانال: " . $this->getChannelTitle($channelId) . "\n";
                $confirmationMessage .= "🆔 کد پست: {$postId}\n";
                $confirmationMessage .= "⏰ زمان ارسال: {$jalaliTime['formatted']}\n";
                $confirmationMessage .= "📊 جایگاه در صف: " . $this->getPostPositionInQueue($postId, $channelId) . "\n";
                $confirmationMessage .= "🔄 وضعیت: در انتظار ارسال\n\n";
                $confirmationMessage .= "💬 برای تغییر زمان، روی این پیام ریپلای کنید و زمان دلخواه را به فرمت زیر بنویسید:\n";
                $confirmationMessage .= "time YYYY-MM-DD HH:MM";

                $this->telegram->sendMessage($chatId, $confirmationMessage, replyToMessageId: $message['message_id']);
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ذخیره پست در دیتابیس.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in registerNormalPost: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در ثبت پست رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Register a forwarded post
     *
     * @param array $message
     * @param int $channelId
     * @return bool
     */
    public function registerForwardedPost(array $message, int $channelId): bool
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

            // Extract post content from forwarded message
            $originalMessage = $message['forward_from_message'] ?? $message;
            $postData = $this->extractPostData($originalMessage);

            // Prepare post data for database
            $postToSave = [
                'channel_id' => $channelId,
                'message_id_in_bot' => $message['message_id'],
                'message_id_in_channel' => $originalMessage['message_id'] ?? null,
                'file_id' => $postData['file_id'] ?? null,
                'file_unique_id' => $postData['file_unique_id'] ?? null,
                'media_type' => $postData['media_type'],
                'caption' => $postData['caption'] ?? null,
                'caption_entities' => $postData['caption_entities'] ? json_encode($postData['caption_entities']) : null,
                'parse_mode' => PARSE_MODE_HTML,
                'type' => POST_TYPE_FORWARDED,
                'scheduled_time' => $this->calculateScheduledTime($channelId),
                'status' => POST_STATUS_PENDING,
                'created_by' => $user['id'],
                'created_at' => time(),
                'updated_at' => time()
            ];

            // Save post to database
            $postId = $this->postRepository->create($postToSave);

            if ($postId) {
                // Format scheduled time
                $jalaliTime = $this->convertTimestampToJalali($postToSave['scheduled_time']);
                
                // Send confirmation message
                $confirmationMessage = "✅ پست فوروارد شده با موفقیت ثبت شد!\n";
                $confirmationMessage .= "📌 کانال: " . $this->getChannelTitle($channelId) . "\n";
                $confirmationMessage .= "🆔 کد پست: {$postId}\n";
                $confirmationMessage .= "⏰ زمان ارسال: {$jalaliTime['formatted']}\n";
                $confirmationMessage .= "📊 جایگاه در صف: " . $this->getPostPositionInQueue($postId, $channelId) . "\n";
                $confirmationMessage .= "🔄 وضعیت: در انتظار ارسال\n\n";
                $confirmationMessage .= "💬 برای تغییر زمان، روی این پیام ریپلای کنید و زمان دلخواه را به فرمت زیر بنویسید:\n";
                $confirmationMessage .= "time YYYY-MM-DD HH:MM";

                $this->telegram->sendMessage($chatId, $confirmationMessage, replyToMessageId: $message['message_id']);
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ذخیره پست در دیتابیس.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in registerForwardedPost: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در ثبت پست رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Register a last post (will be sent last in queue)
     *
     * @param array $message
     * @param int $channelId
     * @return bool
     */
    public function registerLastPost(array $message, int $channelId): bool
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

            // Extract post content from message
            $postData = $this->extractPostData($message);

            // Calculate scheduled time to be last in queue
            $scheduledTime = $this->calculateLastScheduledTime($channelId);

            // Prepare post data for database
            $postToSave = [
                'channel_id' => $channelId,
                'message_id_in_bot' => $message['message_id'],
                'file_id' => $postData['file_id'] ?? null,
                'file_unique_id' => $postData['file_unique_id'] ?? null,
                'media_type' => $postData['media_type'],
                'caption' => $postData['caption'] ?? null,
                'caption_entities' => $postData['caption_entities'] ? json_encode($postData['caption_entities']) : null,
                'parse_mode' => PARSE_MODE_HTML,
                'type' => POST_TYPE_LAST,
                'is_last_in_queue' => 1,
                'scheduled_time' => $scheduledTime,
                'status' => POST_STATUS_PENDING,
                'created_by' => $user['id'],
                'created_at' => time(),
                'updated_at' => time()
            ];

            // Save post to database
            $postId = $this->postRepository->create($postToSave);

            if ($postId) {
                // Format scheduled time
                $jalaliTime = $this->convertTimestampToJalali($postToSave['scheduled_time']);
                
                // Send confirmation message
                $confirmationMessage = "✅ پست آخر با موفقیت ثبت شد!\n";
                $confirmationMessage .= "📌 کانال: " . $this->getChannelTitle($channelId) . "\n";
                $confirmationMessage .= "🆔 کد پست: {$postId}\n";
                $confirmationMessage .= "⏰ زمان ارسال: {$jalaliTime['formatted']} (آخرین پست)\n";
                $confirmationMessage .= "🔄 وضعیت: در انتظار ارسال\n\n";
                $confirmationMessage .= "💬 این پست در انتهای صف ارسال خواهد شد.";

                $this->telegram->sendMessage($chatId, $confirmationMessage, replyToMessageId: $message['message_id']);
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ذخیره پست در دیتابیس.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in registerLastPost: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در ثبت پست رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Register a downer post (will be deleted and resent after X minutes)
     *
     * @param array $message
     * @param int $channelId
     * @param int $minutesAfterSend
     * @return bool
     */
    public function registerDownerPost(array $message, int $channelId, int $minutesAfterSend): bool
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

            // Extract post content from message
            $postData = $this->extractPostData($message);

            // Prepare post data for database
            $postToSave = [
                'channel_id' => $channelId,
                'message_id_in_bot' => $message['message_id'],
                'file_id' => $postData['file_id'] ?? null,
                'file_unique_id' => $postData['file_unique_id'] ?? null,
                'media_type' => $postData['media_type'],
                'caption' => $postData['caption'] ?? null,
                'caption_entities' => $postData['caption_entities'] ? json_encode($postData['caption_entities']) : null,
                'parse_mode' => PARSE_MODE_HTML,
                'type' => POST_TYPE_DOWNER,
                'downer_minutes' => $minutesAfterSend,
                'downer_mode' => POST_TYPE_NORMAL, // Since it's not forwarded
                'is_downer_active' => 1,
                'scheduled_time' => $this->calculateScheduledTime($channelId),
                'status' => POST_STATUS_PENDING,
                'created_by' => $user['id'],
                'created_at' => time(),
                'updated_at' => time()
            ];

            // Save post to database
            $postId = $this->postRepository->create($postToSave);

            if ($postId) {
                // Format scheduled time
                $jalaliTime = $this->convertTimestampToJalali($postToSave['scheduled_time']);
                
                // Send confirmation message
                $confirmationMessage = "🔄 پست دانر با موفقیت ثبت شد!\n";
                $confirmationMessage .= "📌 کانال: " . $this->getChannelTitle($channelId) . "\n";
                $confirmationMessage .= "🆔 کد پست: {$postId}\n";
                $confirmationMessage .= "⏰ زمان ارسال: {$jalaliTime['formatted']}\n";
                $confirmationMessage .= "⏱ زمان حذف و ارسال مجدد: {$minutesAfterSend} دقیقه پس از ارسال\n";
                $confirmationMessage .= "🔄 وضعیت: در انتظار ارسال\n\n";
                $confirmationMessage .= "💬 این پست پس از ارسال، {$minutesAfterSend} دقیقه بعد حذف و مجدداً ارسال خواهد شد.";

                $this->telegram->sendMessage($chatId, $confirmationMessage, replyToMessageId: $message['message_id']);
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ذخیره پست در دیتابیس.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in registerDownerPost: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در ثبت پست رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Handle custom time reply
     *
     * @param array $message
     * @param string $time
     * @return bool
     */
    public function handleCustomTimeReply(array $message, string $time): bool
    {
        try {
            $userId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;
            $replyToMessageId = $message['reply_to_message']['message_id'] ?? null;

            if (!$userId || !$chatId || !$replyToMessageId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات ناقص است.");
                return false;
            }

            // Validate time format (YYYY-MM-DD HH:MM)
            if (!$this->validateDateTimeFormat($time)) {
                $this->telegram->sendMessage($chatId, "❌ فرمت زمان نامعتبر است. لطفاً از فرمت YYYY-MM-DD HH:MM استفاده کنید.");
                return false;
            }

            // Convert time to timestamp
            $timestamp = strtotime($time);
            if ($timestamp === false || $timestamp <= time()) {
                $this->telegram->sendMessage($chatId, "❌ زمان باید در آینده باشد و حداکثر ۲۴ ساعت بعد از الان.");
                return false;
            }

            // Check if it's within 24 hours
            if ($timestamp > (time() + ONE_DAY)) {
                $this->telegram->sendMessage($chatId, "❌ زمان نمی‌تواند بیشتر از ۲۴ ساعت آینده باشد.");
                return false;
            }

            // Find the post associated with the replied message
            $post = $this->postRepository->findByBotMessageId($replyToMessageId);

            if (!$post) {
                $this->telegram->sendMessage($chatId, "❌ پست مرتبط یافت نشد.");
                return false;
            }

            // Update the post's scheduled time
            $updateResult = $this->postRepository->update($post['id'], [
                'scheduled_time' => $timestamp,
                'updated_at' => time()
            ]);

            if ($updateResult > 0) {
                $jalaliTime = $this->convertTimestampToJalali($timestamp);
                $successMessage = "🕒 زمان ارسال پست با موفقیت تغییر کرد!\n";
                $successMessage .= "📆 تاریخ جدید: {$jalaliTime['formatted']}";

                $this->telegram->sendMessage($chatId, $successMessage, replyToMessageId: $replyToMessageId);
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در به‌روزرسانی زمان پست.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in handleCustomTimeReply: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در تغییر زمان پست رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Finalize post registration session
     *
     * @param int $userId
     * @param int $channelId
     * @return bool
     */
    public function finalizeSession(int $userId, int $channelId): bool
    {
        try {
            // Get all pending posts for this user and channel
            $pendingPosts = $this->postRepository->getPendingPostsByUserAndChannel($userId, $channelId);

            if (empty($pendingPosts)) {
                $this->telegram->sendMessage($userId, "❌ پستی برای ثبت نهایی وجود ندارد.");
                return false;
            }

            // Count total posts
            $totalPosts = count($pendingPosts);

            // Get first and last scheduled times
            $firstPost = min($pendingPosts, fn($a, $b) => $a['scheduled_time'] <=> $b['scheduled_time']);
            $lastPost = max($pendingPosts, fn($a, $b) => $a['scheduled_time'] <=> $b['scheduled_time']);

            // Calculate average interval
            $interval = 0;
            if ($totalPosts > 1) {
                $interval = ($lastPost['scheduled_time'] - $firstPost['scheduled_time']) / ($totalPosts - 1);
            }

            // Format times
            $firstJalali = $this->convertTimestampToJalali($firstPost['scheduled_time']);
            $lastJalali = $this->convertTimestampToJalali($lastPost['scheduled_time']);

            // Send summary
            $summaryMessage = "📊 گزارش نهایی ثبت پست:\n";
            $summaryMessage .= "✅ تعداد کل پست‌ها: {$totalPosts}\n";
            $summaryMessage .= "🕒 اولین ارسال: {$firstJalali['formatted']}\n";
            $summaryMessage .= "🕒 آخرین ارسال: {$lastJalali['formatted']}\n";

            if ($interval > 0) {
                $intervalInMinutes = round($interval / 60);
                $summaryMessage .= "⏱ فاصله بین پست‌ها: {$intervalInMinutes} دقیقه\n";
            }

            $summaryMessage .= "📋 حالت ارسال: عادی\n\n";
            $summaryMessage .= "🔻 برای مشاهده لیست پست‌ها:\n";
            $summaryMessage .= "[📋 لیست پست‌ها]";

            $this->telegram->sendMessage($userId, $summaryMessage);

            return true;
        } catch (\Exception $e) {
            error_log("Error in finalizeSession: " . $e->getMessage());
            if ($userId) {
                $this->telegram->sendMessage($userId, "❌ خطایی در نهایی‌سازی جلسه ثبت پست رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Extract post data from message
     *
     * @param array $message
     * @return array
     */
    private function extractPostData(array $message): array
    {
        $result = [
            'media_type' => MEDIA_TYPE_TEXT,
            'file_id' => null,
            'file_unique_id' => null,
            'caption' => $message['caption'] ?? null,
            'caption_entities' => $message['caption_entities'] ?? null
        ];

        // Check for different media types
        if (isset($message['photo'])) {
            $photos = $message['photo'];
            // Get the highest quality photo (last in array)
            $photo = end($photos);
            $result['media_type'] = MEDIA_TYPE_PHOTO;
            $result['file_id'] = $photo['file_id'];
            $result['file_unique_id'] = $photo['file_unique_id'];
        } elseif (isset($message['video'])) {
            $video = $message['video'];
            $result['media_type'] = MEDIA_TYPE_VIDEO;
            $result['file_id'] = $video['file_id'];
            $result['file_unique_id'] = $video['file_unique_id'];
        } elseif (isset($message['document'])) {
            $document = $message['document'];
            $result['media_type'] = MEDIA_TYPE_DOCUMENT;
            $result['file_id'] = $document['file_id'];
            $result['file_unique_id'] = $document['file_unique_id'];
        } elseif (isset($message['audio'])) {
            $audio = $message['audio'];
            $result['media_type'] = MEDIA_TYPE_AUDIO;
            $result['file_id'] = $audio['file_id'];
            $result['file_unique_id'] = $audio['file_unique_id'];
        } elseif (isset($message['voice'])) {
            $voice = $message['voice'];
            $result['media_type'] = MEDIA_TYPE_VOICE;
            $result['file_id'] = $voice['file_id'];
            $result['file_unique_id'] = $voice['file_unique_id'];
        } elseif (isset($message['animation'])) {
            $animation = $message['animation'];
            $result['media_type'] = MEDIA_TYPE_ANIMATION;
            $result['file_id'] = $animation['file_id'];
            $result['file_unique_id'] = $animation['file_unique_id'];
        } elseif (isset($message['sticker'])) {
            $sticker = $message['sticker'];
            $result['media_type'] = MEDIA_TYPE_STICKER;
            $result['file_id'] = $sticker['file_id'];
            $result['file_unique_id'] = $sticker['file_unique_id'];
        } elseif (isset($message['poll'])) {
            $result['media_type'] = MEDIA_TYPE_POLL;
        } elseif (isset($message['location'])) {
            $result['media_type'] = MEDIA_TYPE_LOCATION;
        } elseif (isset($message['contact'])) {
            $result['media_type'] = MEDIA_TYPE_CONTACT;
        } else {
            // Text message
            $result['media_type'] = MEDIA_TYPE_TEXT;
        }

        return $result;
    }

    /**
     * Calculate scheduled time based on channel settings
     *
     * @param int $channelId
     * @return int
     */
    private function calculateScheduledTime(int $channelId): int
    {
        // For simplicity, we'll schedule for 1 minute from now
        // In a real implementation, this would use the scheduler service
        // to respect channel-specific scheduling rules
        return time() + ONE_MINUTE;
    }

    /**
     * Calculate scheduled time to be last in queue
     *
     * @param int $channelId
     * @return int
     */
    private function calculateLastScheduledTime(int $channelId): int
    {
        // Get the latest scheduled time for this channel
        $latestPost = $this->postRepository->getLatestScheduledPost($channelId);
        
        if ($latestPost) {
            // Schedule 1 hour after the latest post
            return $latestPost['scheduled_time'] + ONE_HOUR;
        } else {
            // If no posts exist, schedule for 1 minute from now
            return time() + ONE_MINUTE;
        }
    }

    /**
     * Get position of post in queue
     *
     * @param int $postId
     * @param int $channelId
     * @return int
     */
    private function getPostPositionInQueue(int $postId, int $channelId): int
    {
        return $this->postRepository->getPositionInQueue($postId, $channelId);
    }

    /**
     * Get channel title by ID
     *
     * @param int $channelId
     * @return string
     */
    private function getChannelTitle(int $channelId): string
    {
        // This would normally fetch from database or cache
        // For now, returning a placeholder
        return "کانال تست";
    }

    /**
     * Validate date time format
     *
     * @param string $dateTime
     * @return bool
     */
    private function validateDateTimeFormat(string $dateTime): bool
    {
        $pattern = '/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/';
        return preg_match($pattern, $dateTime) === 1;
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