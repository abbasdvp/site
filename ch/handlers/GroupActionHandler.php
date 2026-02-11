<?php

namespace Handlers;

use Core\Database;
use Core\TelegramAPI;
use Models\GroupOperation;
use Repositories\GroupOperationRepository;
use Repositories\ChannelRepository;
use Repositories\PostRepository;
use Repositories\PostQueueRepository;

/**
 * GroupActionHandler class - Handles all group operations (copy posts, sync settings, etc.)
 */
class GroupActionHandler
{
    private TelegramAPI $telegram;
    private GroupOperationRepository $groupOpRepository;
    private ChannelRepository $channelRepository;
    private PostRepository $postRepository;
    private PostQueueRepository $queueRepository;

    /**
     * Constructor
     *
     * @param TelegramAPI $telegram
     */
    public function __construct(TelegramAPI $telegram)
    {
        $this->telegram = $telegram;
        $this->groupOpRepository = new GroupOperationRepository(Database::getInstance());
        $this->channelRepository = new ChannelRepository(Database::getInstance());
        $this->postRepository = new PostRepository(Database::getInstance());
        $this->queueRepository = new PostQueueRepository(Database::getInstance());
    }

    /**
     * Copy posts from source channel to multiple destination channels
     *
     * @param int $userId
     * @param int $sourceChannelId
     * @param array $destinationChannelIds
     * @return bool
     */
    public function copyPostsToChannels(int $userId, int $sourceChannelId, array $destinationChannelIds): bool
    {
        try {
            // Verify user has access to source channel
            $sourceChannel = $this->channelRepository->findByTelegramIdAndUserId($sourceChannelId, $userId);
            if (!$sourceChannel) {
                return false;
            }

            // Verify user has access to all destination channels
            foreach ($destinationChannelIds as $destChannelId) {
                $destChannel = $this->channelRepository->findByTelegramIdAndUserId($destChannelId, $userId);
                if (!$destChannel) {
                    return false; // User doesn't have access to this channel
                }
            }

            // Get all posts from source channel
            $sourcePosts = $this->postRepository->getByChannelId($sourceChannelId);

            if (empty($sourcePosts)) {
                return false; // No posts to copy
            }

            // Create group operation record
            $operationData = [
                'user_id' => $userId,
                'selected_channels' => json_encode($destinationChannelIds),
                'source_channel_id' => $sourceChannelId,
                'operation_type' => OPERATION_COPY_POSTS,
                'status' => GROUP_OP_PENDING,
                'total_posts' => count($sourcePosts),
                'created_at' => time()
            ];

            $operationId = $this->groupOpRepository->create($operationData);

            if (!$operationId) {
                return false;
            }

            // Process copying for each destination channel
            $successfulCopies = 0;
            $failedCopies = 0;

            foreach ($destinationChannelIds as $destChannelId) {
                foreach ($sourcePosts as $sourcePost) {
                    try {
                        // Copy post to destination channel
                        $copiedPostId = $this->copySinglePost($sourcePost, $destChannelId);

                        if ($copiedPostId) {
                            // Schedule copied post according to destination channel settings
                            $this->scheduleCopiedPost($copiedPostId, $destChannelId);
                            $successfulCopies++;
                        } else {
                            $failedCopies++;
                        }
                    } catch (\Exception $e) {
                        error_log("Error copying post {$sourcePost['id']} to channel {$destChannelId}: " . $e->getMessage());
                        $failedCopies++;
                    }
                }
            }

            // Update operation status
            $this->groupOpRepository->update($operationId, [
                'status' => GROUP_OP_COMPLETED,
                'successful_posts' => $successfulCopies,
                'failed_posts' => $failedCopies,
                'completed_at' => time(),
                'updated_at' => time()
            ]);

            // Send completion notification
            $this->sendOperationCompletionNotification($userId, $operationId, $successfulCopies, $failedCopies);

            return true;
        } catch (\Exception $e) {
            error_log("Error in copyPostsToChannels: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copy schedule settings from one channel to multiple channels
     *
     * @param int $userId
     * @param int $sourceChannelId
     * @param array $destinationChannelIds
     * @return bool
     */
    public function copyScheduleSettings(int $userId, int $sourceChannelId, array $destinationChannelIds): bool
    {
        try {
            // Verify user has access to source channel
            $sourceChannel = $this->channelRepository->findByTelegramIdAndUserId($sourceChannelId, $userId);
            if (!$sourceChannel) {
                return false;
            }

            // Verify user has access to all destination channels
            foreach ($destinationChannelIds as $destChannelId) {
                $destChannel = $this->channelRepository->findByTelegramIdAndUserId($destChannelId, $userId);
                if (!$destChannel) {
                    return false; // User doesn't have access to this channel
                }
            }

            // Get source channel schedule settings
            $sourceSettings = [
                'schedule_type' => $sourceChannel['schedule_type'],
                'periodic_minutes' => $sourceChannel['periodic_minutes'],
                'fixed_times' => $sourceChannel['fixed_times'],
                'post_repeat_mode' => $sourceChannel['post_repeat_mode'],
                'auto_delete_status' => $sourceChannel['auto_delete_status'],
                'auto_delete_minutes' => $sourceChannel['auto_delete_minutes']
            ];

            // Update schedule settings for all destination channels
            $successfulUpdates = 0;
            $failedUpdates = 0;

            foreach ($destinationChannelIds as $destChannelId) {
                try {
                    $updateResult = $this->channelRepository->update($destChannelId, array_merge($sourceSettings, [
                        'updated_at' => time()
                    ]));

                    if ($updateResult > 0) {
                        $successfulUpdates++;
                    } else {
                        $failedUpdates++;
                    }
                } catch (\Exception $e) {
                    error_log("Error updating schedule settings for channel {$destChannelId}: " . $e->getMessage());
                    $failedUpdates++;
                }
            }

            // Create operation record for audit trail
            $operationData = [
                'user_id' => $userId,
                'selected_channels' => json_encode($destinationChannelIds),
                'source_channel_id' => $sourceChannelId,
                'operation_type' => OPERATION_COPY_SCHEDULE,
                'status' => GROUP_OP_COMPLETED,
                'total_posts' => 0, // No posts involved in this operation
                'successful_posts' => $successfulUpdates,
                'failed_posts' => $failedUpdates,
                'completed_at' => time(),
                'created_at' => time(),
                'updated_at' => time()
            ];

            $this->groupOpRepository->create($operationData);

            // Send notification
            $notificationMessage = "🔄 تنظیمات زمان‌بندی با موفقیت از کانال منبع به {$successfulUpdates} کانال مقصد کپی شد.";
            if ($failedUpdates > 0) {
                $notificationMessage .= "\n❌ {$failedUpdates} کانال به دلیل مشکل به‌روزرسانی نشدند.";
            }

            $this->telegram->sendMessage($userId, $notificationMessage);

            return true;
        } catch (\Exception $e) {
            error_log("Error in copyScheduleSettings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a post to multiple channels simultaneously
     *
     * @param array $message
     * @param array $channelIds
     * @return bool
     */
    public function sendPostToMultipleChannels(array $message, array $channelIds): bool
    {
        try {
            $userId = $message['from']['id'] ?? null;
            if (!$userId) {
                return false;
            }

            // Verify user has access to all channels
            foreach ($channelIds as $channelId) {
                $channel = $this->channelRepository->findByTelegramIdAndUserId($channelId, $userId);
                if (!$channel) {
                    return false; // User doesn't have access to this channel
                }
            }

            // Create operation record
            $operationData = [
                'user_id' => $userId,
                'selected_channels' => json_encode($channelIds),
                'operation_type' => OPERATION_SEND_NEW_POST,
                'status' => GROUP_OP_PENDING,
                'total_posts' => count($channelIds), // One post per channel
                'created_at' => time()
            ];

            $operationId = $this->groupOpRepository->create($operationData);

            if (!$operationId) {
                return false;
            }

            // Extract post data from message
            $postData = $this->extractPostData($message);

            // Send post to each channel
            $successfulSends = 0;
            $failedSends = 0;

            foreach ($channelIds as $channelId) {
                try {
                    // Prepare post data for this specific channel
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
                        'created_by' => $userId,
                        'created_at' => time(),
                        'updated_at' => time()
                    ];

                    // Save post to database
                    $postId = $this->postRepository->create($postToSave);

                    if ($postId) {
                        // Schedule the post
                        $this->schedulePostForChannel($postId, $channelId);
                        $successfulSends++;
                    } else {
                        $failedSends++;
                    }
                } catch (\Exception $e) {
                    error_log("Error sending post to channel {$channelId}: " . $e->getMessage());
                    $failedSends++;
                }
            }

            // Update operation status
            $this->groupOpRepository->update($operationId, [
                'status' => GROUP_OP_COMPLETED,
                'successful_posts' => $successfulSends,
                'failed_posts' => $failedSends,
                'completed_at' => time(),
                'updated_at' => time()
            ]);

            // Send completion notification
            $this->sendOperationCompletionNotification($userId, $operationId, $successfulSends, $failedSends, "ارسال گروهی پست");

            return true;
        } catch (\Exception $e) {
            error_log("Error in sendPostToMultipleChannels: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copy a single post to another channel
     *
     * @param array $sourcePost
     * @param int $destinationChannelId
     * @return int|false New post ID or false on failure
     */
    private function copySinglePost(array $sourcePost, int $destinationChannelId)
    {
        // Prepare new post data with updated channel ID
        $newPostData = $sourcePost;
        unset($newPostData['id']); // Remove original ID
        $newPostData['channel_id'] = $destinationChannelId;
        $newPostData['scheduled_time'] = $this->calculateScheduledTime($destinationChannelId);
        $newPostData['status'] = POST_STATUS_PENDING;
        $newPostData['created_at'] = time();
        $newPostData['updated_at'] = time();

        // Save the copied post
        return $this->postRepository->create($newPostData);
    }

    /**
     * Schedule a copied post according to destination channel settings
     *
     * @param int $postId
     * @param int $channelId
     * @return bool
     */
    private function scheduleCopiedPost(int $postId, int $channelId): bool
    {
        try {
            // Get channel settings to determine scheduling
            $channel = $this->channelRepository->findById($channelId);
            if (!$channel) {
                return false;
            }

            $scheduledTime = $this->calculateScheduledTime($channelId);

            // Update post with new scheduled time
            $this->postRepository->update($postId, [
                'scheduled_time' => $scheduledTime,
                'updated_at' => time()
            ]);

            // Add to queue
            $queueData = [
                'post_id' => $postId,
                'channel_id' => $channelId,
                'scheduled_time' => $scheduledTime,
                'actual_scheduled_time' => $scheduledTime,
                'status' => QUEUE_STATUS_WAITING,
                'created_at' => time()
            ];

            $queueId = $this->queueRepository->create($queueData);

            return $queueId !== false;
        } catch (\Exception $e) {
            error_log("Error scheduling copied post: " . $e->getMessage());
            return false;
        }
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
        // In a real implementation, this would consider channel-specific scheduling rules
        return time() + ONE_MINUTE;
    }

    /**
     * Schedule a post for a specific channel
     *
     * @param int $postId
     * @param int $channelId
     * @return bool
     */
    private function schedulePostForChannel(int $postId, int $channelId): bool
    {
        try {
            $scheduledTime = $this->calculateScheduledTime($channelId);

            // Update post with scheduled time
            $this->postRepository->update($postId, [
                'scheduled_time' => $scheduledTime,
                'updated_at' => time()
            ]);

            // Add to queue
            $queueData = [
                'post_id' => $postId,
                'channel_id' => $channelId,
                'scheduled_time' => $scheduledTime,
                'actual_scheduled_time' => $scheduledTime,
                'status' => QUEUE_STATUS_WAITING,
                'created_at' => time()
            ];

            $queueId = $this->queueRepository->create($queueData);

            return $queueId !== false;
        } catch (\Exception $e) {
            error_log("Error scheduling post for channel: " . $e->getMessage());
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
     * Send operation completion notification
     *
     * @param int $userId
     * @param int $operationId
     * @param int $successfulCount
     * @param int $failedCount
     * @param string $operationType
     */
    private function sendOperationCompletionNotification(int $userId, int $operationId, int $successfulCount, int $failedCount, string $operationType = "عملیات گروهی"): void
    {
        $notificationMessage = "✅ {$operationType} به پایان رسید!\n\n";
        $notificationMessage .= "📊 وضعیت:\n";
        $notificationMessage .= "✅ موفق: {$successfulCount}\n";
        $notificationMessage .= "❌ ناموفق: {$failedCount}\n";
        $notificationMessage .= "🔗 کد عملیات: {$operationId}\n\n";
        $notificationMessage .= "📅 زمان تکمیل: " . $this->convertTimestampToJalali(time())['formatted'];

        try {
            $this->telegram->sendMessage($userId, $notificationMessage);
        } catch (\Exception $e) {
            error_log("Error sending operation completion notification: " . $e->getMessage());
        }
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

    /**
     * Get group operation status
     *
     * @param int $operationId
     * @return array|null
     */
    public function getOperationStatus(int $operationId): ?array
    {
        return $this->groupOpRepository->findById($operationId);
    }

    /**
     * Get user's recent group operations
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserOperations(int $userId, int $limit = 10): array
    {
        return $this->groupOpRepository->getByUserId($userId, $limit);
    }
}