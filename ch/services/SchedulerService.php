<?php

namespace Services;

use Core\Database;
use Core\TelegramAPI;
use Models\Post;
use Repositories\PostRepository;
use Repositories\PostQueueRepository;

/**
 * SchedulerService class - Handles all scheduling operations
 */
class SchedulerService
{
    private TelegramAPI $telegram;
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
        $this->postRepository = new PostRepository(Database::getInstance());
        $this->queueRepository = new PostQueueRepository(Database::getInstance());
    }

    /**
     * Calculate next scheduled time for periodic posts
     *
     * @param int $channelId
     * @param int|null $lastScheduledTime
     * @param int $periodicMinutes
     * @return int
     */
    public function calculateNextPeriodicTime(int $channelId, ?int $lastScheduledTime, int $periodicMinutes): int
    {
        if ($lastScheduledTime) {
            $nextTime = $lastScheduledTime + ($periodicMinutes * 60);
        } else {
            // If no last scheduled time, use current time
            $nextTime = time();
        }

        // If calculated time is in the past, adjust to future time
        if ($nextTime < time()) {
            // Calculate how many periods we missed and add one more
            $missedPeriods = floor((time() - $nextTime) / ($periodicMinutes * 60));
            $nextTime = time() + ($periodicMinutes * 60);
        }

        return $nextTime;
    }

    /**
     * Calculate next scheduled time for fixed times
     *
     * @param int $channelId
     * @param array $fixedTimes
     * @param array|null $daysOfWeek
     * @return int
     */
    public function calculateNextFixedTime(int $channelId, array $fixedTimes, ?array $daysOfWeek = null): int
    {
        if (empty($fixedTimes)) {
            throw new \InvalidArgumentException("Fixed times cannot be empty");
        }

        // Sort times in ascending order
        sort($fixedTimes);

        // Get current date and time
        $currentTimestamp = time();
        $currentTime = date('H:i', $currentTimestamp);
        $currentDate = date('Y-m-d', $currentTimestamp);
        $currentDayOfWeek = date('w', $currentTimestamp); // 0 (Sunday) through 6 (Saturday)

        // Filter times based on days of week if provided
        $filteredTimes = $fixedTimes;
        if ($daysOfWeek !== null) {
            $filteredTimes = array_filter($fixedTimes, function($time) use ($daysOfWeek, $currentDayOfWeek) {
                return in_array($currentDayOfWeek, $daysOfWeek);
            });
        }

        if (empty($filteredTimes)) {
            // If no times are valid for today, find the next valid day
            $nextValidDay = $this->findNextValidDay($currentDayOfWeek, $daysOfWeek);
            $nextDate = date('Y-m-d', strtotime("+{$nextValidDay} days"));
            return strtotime($nextDate . ' ' . $fixedTimes[0]);
        }

        // Find the next time today
        foreach ($filteredTimes as $time) {
            if ($time > $currentTime) {
                $nextTime = strtotime($currentDate . ' ' . $time);
                // Only return if it's still today and in the future
                if ($nextTime > $currentTimestamp) {
                    return $nextTime;
                }
            }
        }

        // If no time left today, use first time tomorrow
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        return strtotime($tomorrow . ' ' . $fixedTimes[0]);
    }

    /**
     * Find next valid day based on days of week
     *
     * @param int $currentDayOfWeek
     * @param array|null $daysOfWeek
     * @return int Days until next valid day
     */
    private function findNextValidDay(int $currentDayOfWeek, ?array $daysOfWeek = null): int
    {
        if ($daysOfWeek === null) {
            return 1; // Tomorrow
        }

        $dayDiff = 0;
        $checkDay = $currentDayOfWeek;

        while (true) {
            $checkDay = ($checkDay + 1) % 7;
            $dayDiff++;
            
            if (in_array($checkDay, $daysOfWeek)) {
                return $dayDiff;
            }

            // Safety check to avoid infinite loop
            if ($dayDiff > 7) {
                return 1; // Default to tomorrow
            }
        }
    }

    /**
     * Schedule a post for sending
     *
     * @param int $postId
     * @param int $scheduledTime
     * @return bool
     */
    public function schedulePost(int $postId, int $scheduledTime): bool
    {
        try {
            $post = $this->postRepository->findById($postId);
            if (!$post) {
                return false;
            }

            // Add to queue
            $queueData = [
                'post_id' => $postId,
                'channel_id' => $post['channel_id'],
                'scheduled_time' => $scheduledTime,
                'actual_scheduled_time' => $scheduledTime,
                'status' => QUEUE_STATUS_WAITING,
                'created_at' => time()
            ];

            $queueId = $this->queueRepository->create($queueData);

            if ($queueId) {
                // Update post scheduled time
                $this->postRepository->update($postId, [
                    'scheduled_time' => $scheduledTime,
                    'updated_at' => time()
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            error_log("Error scheduling post: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule auto deletion of a post after sending
     *
     * @param int $channelId
     * @param int $messageId
     * @param int $minutesAfterSend
     * @return bool
     */
    public function scheduleAutoDelete(int $channelId, int $messageId, int $minutesAfterSend): bool
    {
        try {
            $scheduledTime = time() + ($minutesAfterSend * 60);

            // Store deletion task in a separate table or queue
            // For now, we'll simulate by just logging the task
            error_log("Scheduled deletion for message {$messageId} in channel {$channelId} at " . date('Y-m-d H:i:s', $scheduledTime));

            return true;
        } catch (\Exception $e) {
            error_log("Error scheduling auto delete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule downer reshoot (delete and resend)
     *
     * @param array $post
     * @return bool
     */
    public function scheduleDownerReshoot(array $post): bool
    {
        try {
            // For downer posts, we need to schedule both deletion and re-sending
            $downerMinutes = $post['downer_minutes'] ?? 5; // Default to 5 minutes

            // Schedule deletion after specified minutes
            $deleteTime = $post['sent_time'] + ($downerMinutes * 60);

            // Schedule re-sending after deletion
            $resendTime = $deleteTime + 60; // Add 1 minute after deletion

            // In a real implementation, we would store these tasks in a scheduler
            error_log("Scheduled downer reshoot for post {$post['id']}: delete at " . date('Y-m-d H:i:s', $deleteTime) . ", resend at " . date('Y-m-d H:i:s', $resendTime));

            return true;
        } catch (\Exception $e) {
            error_log("Error scheduling downer reshoot: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process scheduled posts for sending
     *
     * @return int Number of posts processed
     */
    public function processScheduledPosts(): int
    {
        $processedCount = 0;
        $now = time();

        // Get posts that are ready to be sent
        $readyPosts = $this->queueRepository->getReadyToSend($now);

        foreach ($readyPosts as $queueItem) {
            try {
                $post = $this->postRepository->findById($queueItem['post_id']);
                
                if (!$post) {
                    // Mark queue item as failed if post doesn't exist
                    $this->queueRepository->update($queueItem['id'], [
                        'status' => QUEUE_STATUS_FAILED,
                        'last_attempt_time' => $now
                    ]);
                    continue;
                }

                // Update queue status to processing
                $this->queueRepository->update($queueItem['id'], [
                    'status' => QUEUE_STATUS_PROCESSING,
                    'last_attempt_time' => $now
                ]);

                // Send the post
                $sendSuccess = $this->sendPostToChannel($post);

                if ($sendSuccess) {
                    // Update post status to sent
                    $this->postRepository->update($post['id'], [
                        'status' => POST_STATUS_SENT,
                        'sent_time' => $now,
                        'updated_at' => $now
                    ]);

                    // Update queue status to sent
                    $this->queueRepository->update($queueItem['id'], [
                        'status' => QUEUE_STATUS_SENT,
                        'last_attempt_time' => $now
                    ]);

                    // If auto-delete is enabled, schedule it
                    if ($post['auto_delete_status']) {
                        $this->scheduleAutoDelete(
                            $post['channel_id'],
                            $post['message_id_in_channel'],
                            $post['auto_delete_minutes']
                        );
                    }

                    // If downer is active, schedule reshoot
                    if ($post['is_downer_active']) {
                        $this->scheduleDownerReshoot($post);
                    }

                    $processedCount++;
                } else {
                    // Increment attempt count
                    $attemptCount = $queueItem['attempt_count'] + 1;
                    
                    // Update queue status based on retry count
                    if ($attemptCount >= MAX_RETRY_COUNT) {
                        $status = QUEUE_STATUS_FAILED;
                    } else {
                        $status = QUEUE_STATUS_WAITING;
                    }

                    $this->queueRepository->update($queueItem['id'], [
                        'status' => $status,
                        'attempt_count' => $attemptCount,
                        'last_attempt_time' => $now
                    ]);

                    // Update post error message if failed permanently
                    if ($status === QUEUE_STATUS_FAILED) {
                        $this->postRepository->update($post['id'], [
                            'status' => POST_STATUS_FAILED,
                            'error_message' => 'Max retry attempts exceeded',
                            'updated_at' => $now
                        ]);
                    }
                }
            } catch (\Exception $e) {
                error_log("Error processing queue item {$queueItem['id']}: " . $e->getMessage());
                
                // Update queue status to failed
                $this->queueRepository->update($queueItem['id'], [
                    'status' => QUEUE_STATUS_FAILED,
                    'last_attempt_time' => $now
                ]);
            }
        }

        return $processedCount;
    }

    /**
     * Send a post to its channel
     *
     * @param array $post
     * @return bool
     */
    private function sendPostToChannel(array $post): bool
    {
        try {
            $channelId = $post['channel_id'];
            
            switch ($post['media_type']) {
                case MEDIA_TYPE_TEXT:
                    $result = $this->telegram->sendMessage(
                        $channelId,
                        $post['caption'] ?? '',
                        parseMode: $post['parse_mode'] ?? PARSE_MODE_HTML
                    );
                    break;
                    
                case MEDIA_TYPE_PHOTO:
                    $result = $this->telegram->sendPhoto(
                        $channelId,
                        $post['file_id'],
                        caption: $post['caption'] ?? null,
                        parseMode: $post['parse_mode'] ?? PARSE_MODE_HTML
                    );
                    break;
                    
                case MEDIA_TYPE_VIDEO:
                    $result = $this->telegram->sendVideo(
                        $channelId,
                        $post['file_id'],
                        caption: $post['caption'] ?? null,
                        parseMode: $post['parse_mode'] ?? PARSE_MODE_HTML
                    );
                    break;
                    
                case MEDIA_TYPE_DOCUMENT:
                    $result = $this->telegram->sendDocument(
                        $channelId,
                        $post['file_id'],
                        caption: $post['caption'] ?? null,
                        parseMode: $post['parse_mode'] ?? PARSE_MODE_HTML
                    );
                    break;
                    
                case MEDIA_TYPE_AUDIO:
                    $result = $this->telegram->sendAudio(
                        $channelId,
                        $post['file_id'],
                        caption: $post['caption'] ?? null,
                        parseMode: $post['parse_mode'] ?? PARSE_MODE_HTML
                    );
                    break;
                    
                case MEDIA_TYPE_VOICE:
                    $result = $this->telegram->sendVoice(
                        $channelId,
                        $post['file_id'],
                        caption: $post['caption'] ?? null,
                        parseMode: $post['parse_mode'] ?? PARSE_MODE_HTML
                    );
                    break;
                    
                case MEDIA_TYPE_ANIMATION:
                    $result = $this->telegram->sendAnimation(
                        $channelId,
                        $post['file_id'],
                        caption: $post['caption'] ?? null,
                        parseMode: $post['parse_mode'] ?? PARSE_MODE_HTML
                    );
                    break;
                    
                default:
                    // For unsupported types, send as text
                    $result = $this->telegram->sendMessage(
                        $channelId,
                        $post['caption'] ?? "Post type: {$post['media_type']}",
                        parseMode: $post['parse_mode'] ?? PARSE_MODE_HTML
                    );
                    break;
            }

            // Update the post with the message ID in the channel
            $this->postRepository->update($post['id'], [
                'message_id_in_channel' => $result['message_id'] ?? null,
                'sent_time' => time(),
                'updated_at' => time()
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("Error sending post {$post['id']} to channel {$post['channel_id']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get upcoming scheduled posts
     *
     * @param int $channelId
     * @param int $limit
     * @return array
     */
    public function getUpcomingScheduledPosts(int $channelId, int $limit = 10): array
    {
        return $this->postRepository->getUpcomingScheduledPosts($channelId, $limit);
    }

    /**
     * Cancel a scheduled post
     *
     * @param int $postId
     * @return bool
     */
    public function cancelScheduledPost(int $postId): bool
    {
        try {
            $post = $this->postRepository->findById($postId);
            if (!$post) {
                return false;
            }

            // Update post status to cancelled
            $this->postRepository->update($postId, [
                'status' => POST_STATUS_DELETED,
                'deleted_time' => time(),
                'updated_at' => time()
            ]);

            // Also remove from queue if it's there
            $this->queueRepository->updateWhere([
                'status' => QUEUE_STATUS_FAILED,
                'last_attempt_time' => time()
            ], 'post_id = ? AND status = ?', [$postId, QUEUE_STATUS_WAITING]);

            return true;
        } catch (\Exception $e) {
            error_log("Error cancelling scheduled post: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reschedule a post to a new time
     *
     * @param int $postId
     * @param int $newScheduledTime
     * @return bool
     */
    public function reschedulePost(int $postId, int $newScheduledTime): bool
    {
        try {
            $post = $this->postRepository->findById($postId);
            if (!$post) {
                return false;
            }

            // Update post scheduled time
            $this->postRepository->update($postId, [
                'scheduled_time' => $newScheduledTime,
                'updated_at' => time()
            ]);

            // Update queue item if it exists
            $queueItems = $this->queueRepository->findByPostId($postId);
            foreach ($queueItems as $queueItem) {
                $this->queueRepository->update($queueItem['id'], [
                    'scheduled_time' => $newScheduledTime,
                    'actual_scheduled_time' => $newScheduledTime,
                    'updated_at' => time()
                ]);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error rescheduling post: " . $e->getMessage());
            return false;
        }
    }
}