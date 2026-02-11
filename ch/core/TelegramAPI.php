<?php

namespace Core;

use Exception;

/**
 * Telegram API class - handles all Telegram API methods with retry mechanism
 */
class TelegramAPI
{
    private string $token;
    private string $apiUrl;
    private int $timeout;

    /**
     * Constructor
     *
     * @param string $token
     * @param int $timeout
     */
    public function __construct(string $token, int $timeout = TIMEOUT)
    {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
        $this->timeout = $timeout;
    }

    /**
     * Make API request
     *
     * @param string $method
     * @param array $params
     * @param bool $retryOnFailure
     * @return array
     * @throws Exception
     */
    private function makeRequest(string $method, array $params = [], bool $retryOnFailure = true): array
    {
        $url = $this->apiUrl . $method;
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            if ($retryOnFailure) {
                // Retry once after a short delay
                usleep(500000); // 0.5 second delay
                return $this->makeRequest($method, $params, false);
            }
            throw new Exception("Curl error: {$error}");
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }

        if (!$decodedResponse['ok']) {
            if ($retryOnFailure && $this->shouldRetry($decodedResponse['error_code'])) {
                // Retry once after a short delay
                usleep(500000); // 0.5 second delay
                return $this->makeRequest($method, $params, false);
            }
            throw new Exception("Telegram API error: {$decodedResponse['description']} (Code: {$decodedResponse['error_code']})");
        }

        return $decodedResponse['result'];
    }

    /**
     * Determine if request should be retried based on error code
     *
     * @param int $errorCode
     * @return bool
     */
    private function shouldRetry(int $errorCode): bool
    {
        // Retry on server errors and rate limiting
        return in_array($errorCode, [429, 500, 502, 503, 504]);
    }

    /**
     * Get updates from webhook
     *
     * @return array
     */
    public function getWebhookUpdates(): array
    {
        $rawInput = file_get_contents('php://input');
        return json_decode($rawInput, true) ?: [];
    }

    /**
     * Set webhook
     *
     * @param string $url
     * @param string|null $certificate
     * @param string|null $ipAddress
     * @param int|null $maxConnections
     * @param array|null $allowedUpdates
     * @return array
     * @throws Exception
     */
    public function setWebhook(
        string $url,
        ?string $certificate = null,
        ?string $ipAddress = null,
        ?int $maxConnections = null,
        ?array $allowedUpdates = null
    ): array {
        $params = ['url' => $url];
        if ($certificate !== null) $params['certificate'] = $certificate;
        if ($ipAddress !== null) $params['ip_address'] = $ipAddress;
        if ($maxConnections !== null) $params['max_connections'] = $maxConnections;
        if ($allowedUpdates !== null) $params['allowed_updates'] = $allowedUpdates;

        return $this->makeRequest('setWebhook', $params);
    }

    /**
     * Delete webhook
     *
     * @param bool|null $dropPendingUpdates
     * @return array
     * @throws Exception
     */
    public function deleteWebhook(?bool $dropPendingUpdates = null): array
    {
        $params = [];
        if ($dropPendingUpdates !== null) $params['drop_pending_updates'] = $dropPendingUpdates;

        return $this->makeRequest('deleteWebhook', $params);
    }

    /**
     * Get webhook info
     *
     * @return array
     * @throws Exception
     */
    public function getWebhookInfo(): array
    {
        return $this->makeRequest('getWebhookInfo');
    }

    /**
     * Send message
     *
     * @param int|string $chatId
     * @param string $text
     * @param string|null $parseMode
     * @param bool|null $disableWebPagePreview
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?string $parseMode = null,
        ?bool $disableWebPagePreview = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'text' => $text
        ];

        if ($parseMode !== null) $params['parse_mode'] = $parseMode;
        if ($disableWebPagePreview !== null) $params['disable_web_page_preview'] = $disableWebPagePreview;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendMessage', $params);
    }

    /**
     * Forward message
     *
     * @param int|string $chatId
     * @param int|string $fromChatId
     * @param int $messageId
     * @param bool|null $disableNotification
     * @return array
     * @throws Exception
     */
    public function forwardMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        ?bool $disableNotification = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ];

        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;

        return $this->makeRequest('forwardMessage', $params);
    }

    /**
     * Copy message
     *
     * @param int|string $chatId
     * @param int|string $fromChatId
     * @param int $messageId
     * @param string|null $caption
     * @param string|null $parseMode
     * @param array|null $captionEntities
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function copyMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        ?string $caption = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ];

        if ($caption !== null) $params['caption'] = $caption;
        if ($parseMode !== null) $params['parse_mode'] = $parseMode;
        if ($captionEntities !== null) $params['caption_entities'] = json_encode($captionEntities);
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('copyMessage', $params);
    }

    /**
     * Send photo
     *
     * @param int|string $chatId
     * @param string $photo
     * @param string|null $caption
     * @param string|null $parseMode
     * @param array|null $captionEntities
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendPhoto(
        int|string $chatId,
        string $photo,
        ?string $caption = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'photo' => $photo
        ];

        if ($caption !== null) $params['caption'] = $caption;
        if ($parseMode !== null) $params['parse_mode'] = $parseMode;
        if ($captionEntities !== null) $params['caption_entities'] = json_encode($captionEntities);
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendPhoto', $params);
    }

    /**
     * Send video
     *
     * @param int|string $chatId
     * @param string $video
     * @param int|null $duration
     * @param int|null $width
     * @param int|null $height
     * @param string|null $thumb
     * @param string|null $caption
     * @param string|null $parseMode
     * @param array|null $captionEntities
     * @param bool|null $supportsStreaming
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendVideo(
        int|string $chatId,
        string $video,
        ?int $duration = null,
        ?int $width = null,
        ?int $height = null,
        ?string $thumb = null,
        ?string $caption = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $supportsStreaming = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'video' => $video
        ];

        if ($duration !== null) $params['duration'] = $duration;
        if ($width !== null) $params['width'] = $width;
        if ($height !== null) $params['height'] = $height;
        if ($thumb !== null) $params['thumb'] = $thumb;
        if ($caption !== null) $params['caption'] = $caption;
        if ($parseMode !== null) $params['parse_mode'] = $parseMode;
        if ($captionEntities !== null) $params['caption_entities'] = json_encode($captionEntities);
        if ($supportsStreaming !== null) $params['supports_streaming'] = $supportsStreaming;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendVideo', $params);
    }

    /**
     * Send document
     *
     * @param int|string $chatId
     * @param string $document
     * @param string|null $thumb
     * @param string|null $caption
     * @param string|null $parseMode
     * @param array|null $captionEntities
     * @param bool|null $disableContentTypeDetection
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendDocument(
        int|string $chatId,
        string $document,
        ?string $thumb = null,
        ?string $caption = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $disableContentTypeDetection = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'document' => $document
        ];

        if ($thumb !== null) $params['thumb'] = $thumb;
        if ($caption !== null) $params['caption'] = $caption;
        if ($parseMode !== null) $params['parse_mode'] = $parseMode;
        if ($captionEntities !== null) $params['caption_entities'] = json_encode($captionEntities);
        if ($disableContentTypeDetection !== null) $params['disable_content_type_detection'] = $disableContentTypeDetection;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendDocument', $params);
    }

    /**
     * Send audio
     *
     * @param int|string $chatId
     * @param string $audio
     * @param int|null $duration
     * @param string|null $performer
     * @param string|null $title
     * @param string|null $thumb
     * @param string|null $caption
     * @param string|null $parseMode
     * @param array|null $captionEntities
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendAudio(
        int|string $chatId,
        string $audio,
        ?int $duration = null,
        ?string $performer = null,
        ?string $title = null,
        ?string $thumb = null,
        ?string $caption = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'audio' => $audio
        ];

        if ($duration !== null) $params['duration'] = $duration;
        if ($performer !== null) $params['performer'] = $performer;
        if ($title !== null) $params['title'] = $title;
        if ($thumb !== null) $params['thumb'] = $thumb;
        if ($caption !== null) $params['caption'] = $caption;
        if ($parseMode !== null) $params['parse_mode'] = $parseMode;
        if ($captionEntities !== null) $params['caption_entities'] = json_encode($captionEntities);
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendAudio', $params);
    }

    /**
     * Send voice
     *
     * @param int|string $chatId
     * @param string $voice
     * @param string|null $caption
     * @param string|null $parseMode
     * @param array|null $captionEntities
     * @param int|null $duration
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendVoice(
        int|string $chatId,
        string $voice,
        ?string $caption = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?int $duration = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'voice' => $voice
        ];

        if ($caption !== null) $params['caption'] = $caption;
        if ($parseMode !== null) $params['parse_mode'] = $parseMode;
        if ($captionEntities !== null) $params['caption_entities'] = json_encode($captionEntities);
        if ($duration !== null) $params['duration'] = $duration;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendVoice', $params);
    }

    /**
     * Send video note
     *
     * @param int|string $chatId
     * @param string $videoNote
     * @param int|null $duration
     * @param int|null $length
     * @param string|null $thumb
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendVideoNote(
        int|string $chatId,
        string $videoNote,
        ?int $duration = null,
        ?int $length = null,
        ?string $thumb = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'video_note' => $videoNote
        ];

        if ($duration !== null) $params['duration'] = $duration;
        if ($length !== null) $params['length'] = $length;
        if ($thumb !== null) $params['thumb'] = $thumb;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendVideoNote', $params);
    }

    /**
     * Send media group
     *
     * @param int|string $chatId
     * @param array $media
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @return array
     * @throws Exception
     */
    public function sendMediaGroup(
        int|string $chatId,
        array $media,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'media' => json_encode($media)
        ];

        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;

        return $this->makeRequest('sendMediaGroup', $params);
    }

    /**
     * Send location
     *
     * @param int|string $chatId
     * @param float $latitude
     * @param float $longitude
     * @param int|null $livePeriod
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendLocation(
        int|string $chatId,
        float $latitude,
        float $longitude,
        ?int $livePeriod = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude
        ];

        if ($livePeriod !== null) $params['live_period'] = $livePeriod;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendLocation', $params);
    }

    /**
     * Edit message live location
     *
     * @param float $latitude
     * @param float $longitude
     * @param int|string|null $chatId
     * @param int|null $messageId
     * @param string|null $inlineMessageId
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function editMessageLiveLocation(
        float $latitude,
        float $longitude,
        ?int|string $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];

        if ($chatId !== null) $params['chat_id'] = $chatId;
        if ($messageId !== null) $params['message_id'] = $messageId;
        if ($inlineMessageId !== null) $params['inline_message_id'] = $inlineMessageId;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('editMessageLiveLocation', $params);
    }

    /**
     * Stop message live location
     *
     * @param int|string|null $chatId
     * @param int|null $messageId
     * @param string|null $inlineMessageId
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function stopMessageLiveLocation(
        ?int|string $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null,
        ?array $replyMarkup = null
    ): array {
        $params = [];

        if ($chatId !== null) $params['chat_id'] = $chatId;
        if ($messageId !== null) $params['message_id'] = $messageId;
        if ($inlineMessageId !== null) $params['inline_message_id'] = $inlineMessageId;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('stopMessageLiveLocation', $params);
    }

    /**
     * Send venue
     *
     * @param int|string $chatId
     * @param float $latitude
     * @param float $longitude
     * @param string $title
     * @param string $address
     * @param string|null $foursquareId
     * @param string|null $foursquareType
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendVenue(
        int|string $chatId,
        float $latitude,
        float $longitude,
        string $title,
        string $address,
        ?string $foursquareId = null,
        ?string $foursquareType = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address
        ];

        if ($foursquareId !== null) $params['foursquare_id'] = $foursquareId;
        if ($foursquareType !== null) $params['foursquare_type'] = $foursquareType;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendVenue', $params);
    }

    /**
     * Send contact
     *
     * @param int|string $chatId
     * @param string $phoneNumber
     * @param string $firstName
     * @param string|null $lastName
     * @param string|null $vcard
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendContact(
        int|string $chatId,
        string $phoneNumber,
        string $firstName,
        ?string $lastName = null,
        ?string $vcard = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName
        ];

        if ($lastName !== null) $params['last_name'] = $lastName;
        if ($vcard !== null) $params['vcard'] = $vcard;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendContact', $params);
    }

    /**
     * Send poll
     *
     * @param int|string $chatId
     * @param string $question
     * @param array $options
     * @param bool|null $isAnonymous
     * @param string|null $type
     * @param bool|null $allowsMultipleAnswers
     * @param int|null $correctOptionId
     * @param bool|null $isClosed
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendPoll(
        int|string $chatId,
        string $question,
        array $options,
        ?bool $isAnonymous = null,
        ?string $type = null,
        ?bool $allowsMultipleAnswers = null,
        ?int $correctOptionId = null,
        ?bool $isClosed = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'question' => $question,
            'options' => json_encode($options)
        ];

        if ($isAnonymous !== null) $params['is_anonymous'] = $isAnonymous;
        if ($type !== null) $params['type'] = $type;
        if ($allowsMultipleAnswers !== null) $params['allows_multiple_answers'] = $allowsMultipleAnswers;
        if ($correctOptionId !== null) $params['correct_option_id'] = $correctOptionId;
        if ($isClosed !== null) $params['is_closed'] = $isClosed;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendPoll', $params);
    }

    /**
     * Send dice
     *
     * @param int|string $chatId
     * @param string|null $emoji
     * @param bool|null $disableNotification
     * @param int|null $replyToMessageId
     * @param bool|null $allowSendingWithoutReply
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function sendDice(
        int|string $chatId,
        ?string $emoji = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?bool $allowSendingWithoutReply = null,
        ?array $replyMarkup = null
    ): array {
        $params = [
            'chat_id' => $chatId
        ];

        if ($emoji !== null) $params['emoji'] = $emoji;
        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;
        if ($replyToMessageId !== null) $params['reply_to_message_id'] = $replyToMessageId;
        if ($allowSendingWithoutReply !== null) $params['allow_sending_without_reply'] = $allowSendingWithoutReply;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('sendDice', $params);
    }

    /**
     * Send chat action
     *
     * @param int|string $chatId
     * @param string $action
     * @return array
     * @throws Exception
     */
    public function sendChatAction(int|string $chatId, string $action): array
    {
        $params = [
            'chat_id' => $chatId,
            'action' => $action
        ];

        return $this->makeRequest('sendChatAction', $params);
    }

    /**
     * Get user profile photos
     *
     * @param int $userId
     * @param int|null $offset
     * @param int|null $limit
     * @return array
     * @throws Exception
     */
    public function getUserProfilePhotos(int $userId, ?int $offset = null, ?int $limit = null): array
    {
        $params = ['user_id' => $userId];

        if ($offset !== null) $params['offset'] = $offset;
        if ($limit !== null) $params['limit'] = $limit;

        return $this->makeRequest('getUserProfilePhotos', $params);
    }

    /**
     * Get file
     *
     * @param string $fileId
     * @return array
     * @throws Exception
     */
    public function getFile(string $fileId): array
    {
        $params = ['file_id' => $fileId];

        return $this->makeRequest('getFile', $params);
    }

    /**
     * Ban chat member
     *
     * @param int|string $chatId
     * @param int $userId
     * @param int|null $untilDate
     * @param bool|null $revokeMessages
     * @return array
     * @throws Exception
     */
    public function banChatMember(
        int|string $chatId,
        int $userId,
        ?int $untilDate = null,
        ?bool $revokeMessages = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];

        if ($untilDate !== null) $params['until_date'] = $untilDate;
        if ($revokeMessages !== null) $params['revoke_messages'] = $revokeMessages;

        return $this->makeRequest('banChatMember', $params);
    }

    /**
     * Unban chat member
     *
     * @param int|string $chatId
     * @param int $userId
     * @param bool|null $onlyIfBanned
     * @return array
     * @throws Exception
     */
    public function unbanChatMember(int|string $chatId, int $userId, ?bool $onlyIfBanned = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];

        if ($onlyIfBanned !== null) $params['only_if_banned'] = $onlyIfBanned;

        return $this->makeRequest('unbanChatMember', $params);
    }

    /**
     * Restrict chat member
     *
     * @param int|string $chatId
     * @param int $userId
     * @param array $permissions
     * @param int|null $untilDate
     * @return array
     * @throws Exception
     */
    public function restrictChatMember(
        int|string $chatId,
        int $userId,
        array $permissions,
        ?int $untilDate = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => json_encode($permissions)
        ];

        if ($untilDate !== null) $params['until_date'] = $untilDate;

        return $this->makeRequest('restrictChatMember', $params);
    }

    /**
     * Promote chat member
     *
     * @param int|string $chatId
     * @param int $userId
     * @param bool|null $isAnonymous
     * @param bool|null $canManageChat
     * @param bool|null $canPostMessages
     * @param bool|null $canEditMessages
     * @param bool|null $canDeleteMessages
     * @param bool|null $canManageVideoChats
     * @param bool|null $canRestrictMembers
     * @param bool|null $canPromoteMembers
     * @param bool|null $canChangeInfo
     * @param bool|null $canInviteUsers
     * @param bool|null $canPinMessages
     * @param bool|null $canManageTopics
     * @return array
     * @throws Exception
     */
    public function promoteChatMember(
        int|string $chatId,
        int $userId,
        ?bool $isAnonymous = null,
        ?bool $canManageChat = null,
        ?bool $canPostMessages = null,
        ?bool $canEditMessages = null,
        ?bool $canDeleteMessages = null,
        ?bool $canManageVideoChats = null,
        ?bool $canRestrictMembers = null,
        ?bool $canPromoteMembers = null,
        ?bool $canChangeInfo = null,
        ?bool $canInviteUsers = null,
        ?bool $canPinMessages = null,
        ?bool $canManageTopics = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];

        if ($isAnonymous !== null) $params['is_anonymous'] = $isAnonymous;
        if ($canManageChat !== null) $params['can_manage_chat'] = $canManageChat;
        if ($canPostMessages !== null) $params['can_post_messages'] = $canPostMessages;
        if ($canEditMessages !== null) $params['can_edit_messages'] = $canEditMessages;
        if ($canDeleteMessages !== null) $params['can_delete_messages'] = $canDeleteMessages;
        if ($canManageVideoChats !== null) $params['can_manage_video_chats'] = $canManageVideoChats;
        if ($canRestrictMembers !== null) $params['can_restrict_members'] = $canRestrictMembers;
        if ($canPromoteMembers !== null) $params['can_promote_members'] = $canPromoteMembers;
        if ($canChangeInfo !== null) $params['can_change_info'] = $canChangeInfo;
        if ($canInviteUsers !== null) $params['can_invite_users'] = $canInviteUsers;
        if ($canPinMessages !== null) $params['can_pin_messages'] = $canPinMessages;
        if ($canManageTopics !== null) $params['can_manage_topics'] = $canManageTopics;

        return $this->makeRequest('promoteChatMember', $params);
    }

    /**
     * Set chat administrator custom title
     *
     * @param int|string $chatId
     * @param int $userId
     * @param string $customTitle
     * @return array
     * @throws Exception
     */
    public function setChatAdministratorCustomTitle(int|string $chatId, int $userId, string $customTitle): array
    {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'custom_title' => $customTitle
        ];

        return $this->makeRequest('setChatAdministratorCustomTitle', $params);
    }

    /**
     * Ban sender chat
     *
     * @param int|string $chatId
     * @param int $senderChatId
     * @return array
     * @throws Exception
     */
    public function banChatSenderChat(int|string $chatId, int $senderChatId): array
    {
        $params = [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId
        ];

        return $this->makeRequest('banChatSenderChat', $params);
    }

    /**
     * Unban sender chat
     *
     * @param int|string $chatId
     * @param int $senderChatId
     * @return array
     * @throws Exception
     */
    public function unbanChatSenderChat(int|string $chatId, int $senderChatId): array
    {
        $params = [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId
        ];

        return $this->makeRequest('unbanChatSenderChat', $params);
    }

    /**
     * Set chat permissions
     *
     * @param int|string $chatId
     * @param array $permissions
     * @return array
     * @throws Exception
     */
    public function setChatPermissions(int|string $chatId, array $permissions): array
    {
        $params = [
            'chat_id' => $chatId,
            'permissions' => json_encode($permissions)
        ];

        return $this->makeRequest('setChatPermissions', $params);
    }

    /**
     * Export chat invite link
     *
     * @param int|string $chatId
     * @return string
     * @throws Exception
     */
    public function exportChatInviteLink(int|string $chatId): string
    {
        $params = ['chat_id' => $chatId];
        $result = $this->makeRequest('exportChatInviteLink', $params);
        return $result['invite_link'];
    }

    /**
     * Create chat invite link
     *
     * @param int|string $chatId
     * @param string|null $name
     * @param int|null $expireDate
     * @param int|null $memberLimit
     * @param bool|null $createsJoinRequest
     * @return array
     * @throws Exception
     */
    public function createChatInviteLink(
        int|string $chatId,
        ?string $name = null,
        ?int $expireDate = null,
        ?int $memberLimit = null,
        ?bool $createsJoinRequest = null
    ): array {
        $params = ['chat_id' => $chatId];

        if ($name !== null) $params['name'] = $name;
        if ($expireDate !== null) $params['expire_date'] = $expireDate;
        if ($memberLimit !== null) $params['member_limit'] = $memberLimit;
        if ($createsJoinRequest !== null) $params['creates_join_request'] = $createsJoinRequest;

        return $this->makeRequest('createChatInviteLink', $params);
    }

    /**
     * Edit chat invite link
     *
     * @param int|string $chatId
     * @param string $inviteLink
     * @param string|null $name
     * @param int|null $expireDate
     * @param int|null $memberLimit
     * @param bool|null $createsJoinRequest
     * @return array
     * @throws Exception
     */
    public function editChatInviteLink(
        int|string $chatId,
        string $inviteLink,
        ?string $name = null,
        ?int $expireDate = null,
        ?int $memberLimit = null,
        ?bool $createsJoinRequest = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink
        ];

        if ($name !== null) $params['name'] = $name;
        if ($expireDate !== null) $params['expire_date'] = $expireDate;
        if ($memberLimit !== null) $params['member_limit'] = $memberLimit;
        if ($createsJoinRequest !== null) $params['creates_join_request'] = $createsJoinRequest;

        return $this->makeRequest('editChatInviteLink', $params);
    }

    /**
     * Revoke chat invite link
     *
     * @param int|string $chatId
     * @param string $inviteLink
     * @return array
     * @throws Exception
     */
    public function revokeChatInviteLink(int|string $chatId, string $inviteLink): array
    {
        $params = [
            'chat_id' => $chatId,
            'invite_link' => $inviteLink
        ];

        return $this->makeRequest('revokeChatInviteLink', $params);
    }

    /**
     * Approve chat join request
     *
     * @param int|string $chatId
     * @param int $userId
     * @return array
     * @throws Exception
     */
    public function approveChatJoinRequest(int|string $chatId, int $userId): array
    {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];

        return $this->makeRequest('approveChatJoinRequest', $params);
    }

    /**
     * Decline chat join request
     *
     * @param int|string $chatId
     * @param int $userId
     * @return array
     * @throws Exception
     */
    public function declineChatJoinRequest(int|string $chatId, int $userId): array
    {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];

        return $this->makeRequest('declineChatJoinRequest', $params);
    }

    /**
     * Set chat photo
     *
     * @param int|string $chatId
     * @param string $photo
     * @return array
     * @throws Exception
     */
    public function setChatPhoto(int|string $chatId, string $photo): array
    {
        $params = [
            'chat_id' => $chatId,
            'photo' => $photo
        ];

        return $this->makeRequest('setChatPhoto', $params);
    }

    /**
     * Delete chat photo
     *
     * @param int|string $chatId
     * @return array
     * @throws Exception
     */
    public function deleteChatPhoto(int|string $chatId): array
    {
        $params = ['chat_id' => $chatId];

        return $this->makeRequest('deleteChatPhoto', $params);
    }

    /**
     * Set chat title
     *
     * @param int|string $chatId
     * @param string $title
     * @return array
     * @throws Exception
     */
    public function setChatTitle(int|string $chatId, string $title): array
    {
        $params = [
            'chat_id' => $chatId,
            'title' => $title
        ];

        return $this->makeRequest('setChatTitle', $params);
    }

    /**
     * Set chat description
     *
     * @param int|string $chatId
     * @param string|null $description
     * @return array
     * @throws Exception
     */
    public function setChatDescription(int|string $chatId, ?string $description = null): array
    {
        $params = ['chat_id' => $chatId];

        if ($description !== null) $params['description'] = $description;

        return $this->makeRequest('setChatDescription', $params);
    }

    /**
     * Pin chat message
     *
     * @param int|string $chatId
     * @param int $messageId
     * @param bool|null $disableNotification
     * @return array
     * @throws Exception
     */
    public function pinChatMessage(int|string $chatId, int $messageId, ?bool $disableNotification = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];

        if ($disableNotification !== null) $params['disable_notification'] = $disableNotification;

        return $this->makeRequest('pinChatMessage', $params);
    }

    /**
     * Unpin chat message
     *
     * @param int|string $chatId
     * @param int|null $messageId
     * @return array
     * @throws Exception
     */
    public function unpinChatMessage(int|string $chatId, ?int $messageId = null): array
    {
        $params = ['chat_id' => $chatId];

        if ($messageId !== null) $params['message_id'] = $messageId;

        return $this->makeRequest('unpinChatMessage', $params);
    }

    /**
     * Unpin all chat messages
     *
     * @param int|string $chatId
     * @return array
     * @throws Exception
     */
    public function unpinAllChatMessages(int|string $chatId): array
    {
        $params = ['chat_id' => $chatId];

        return $this->makeRequest('unpinAllChatMessages', $params);
    }

    /**
     * Leave chat
     *
     * @param int|string $chatId
     * @return array
     * @throws Exception
     */
    public function leaveChat(int|string $chatId): array
    {
        $params = ['chat_id' => $chatId];

        return $this->makeRequest('leaveChat', $params);
    }

    /**
     * Get chat
     *
     * @param int|string $chatId
     * @return array
     * @throws Exception
     */
    public function getChat(int|string $chatId): array
    {
        $params = ['chat_id' => $chatId];

        return $this->makeRequest('getChat', $params);
    }

    /**
     * Get chat administrators
     *
     * @param int|string $chatId
     * @return array
     * @throws Exception
     */
    public function getChatAdministrators(int|string $chatId): array
    {
        $params = ['chat_id' => $chatId];

        return $this->makeRequest('getChatAdministrators', $params);
    }

    /**
     * Get chat member count
     *
     * @param int|string $chatId
     * @return int
     * @throws Exception
     */
    public function getChatMemberCount(int|string $chatId): int
    {
        $params = ['chat_id' => $chatId];
        $result = $this->makeRequest('getChatMemberCount', $params);
        return $result;
    }

    /**
     * Get chat member
     *
     * @param int|string $chatId
     * @param int $userId
     * @return array
     * @throws Exception
     */
    public function getChatMember(int|string $chatId, int $userId): array
    {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];

        return $this->makeRequest('getChatMember', $params);
    }

    /**
     * Set chat sticker set
     *
     * @param int|string $chatId
     * @param string $stickerSetName
     * @return array
     * @throws Exception
     */
    public function setChatStickerSet(int|string $chatId, string $stickerSetName): array
    {
        $params = [
            'chat_id' => $chatId,
            'sticker_set_name' => $stickerSetName
        ];

        return $this->makeRequest('setChatStickerSet', $params);
    }

    /**
     * Delete chat sticker set
     *
     * @param int|string $chatId
     * @return array
     * @throws Exception
     */
    public function deleteChatStickerSet(int|string $chatId): array
    {
        $params = ['chat_id' => $chatId];

        return $this->makeRequest('deleteChatStickerSet', $params);
    }

    /**
     * Answer callback query
     *
     * @param string $callbackQueryId
     * @param string|null $text
     * @param bool|null $showAlert
     * @param string|null $url
     * @param int|null $cacheTime
     * @return array
     * @throws Exception
     */
    public function answerCallbackQuery(
        string $callbackQueryId,
        ?string $text = null,
        ?bool $showAlert = null,
        ?string $url = null,
        ?int $cacheTime = null
    ): array {
        $params = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) $params['text'] = $text;
        if ($showAlert !== null) $params['show_alert'] = $showAlert;
        if ($url !== null) $params['url'] = $url;
        if ($cacheTime !== null) $params['cache_time'] = $cacheTime;

        return $this->makeRequest('answerCallbackQuery', $params);
    }

    /**
     * Set my commands
     *
     * @param array $commands
     * @param array|null $scope
     * @param string|null $languageCode
     * @return array
     * @throws Exception
     */
    public function setMyCommands(array $commands, ?array $scope = null, ?string $languageCode = null): array
    {
        $params = ['commands' => json_encode($commands)];

        if ($scope !== null) $params['scope'] = json_encode($scope);
        if ($languageCode !== null) $params['language_code'] = $languageCode;

        return $this->makeRequest('setMyCommands', $params);
    }

    /**
     * Delete my commands
     *
     * @param array|null $scope
     * @param string|null $languageCode
     * @return array
     * @throws Exception
     */
    public function deleteMyCommands(?array $scope = null, ?string $languageCode = null): array
    {
        $params = [];

        if ($scope !== null) $params['scope'] = json_encode($scope);
        if ($languageCode !== null) $params['language_code'] = $languageCode;

        return $this->makeRequest('deleteMyCommands', $params);
    }

    /**
     * Get my commands
     *
     * @param array|null $scope
     * @param string|null $languageCode
     * @return array
     * @throws Exception
     */
    public function getMyCommands(?array $scope = null, ?string $languageCode = null): array
    {
        $params = [];

        if ($scope !== null) $params['scope'] = json_encode($scope);
        if ($languageCode !== null) $params['language_code'] = $languageCode;

        return $this->makeRequest('getMyCommands', $params);
    }

    /**
     * Edit message text
     *
     * @param string $text
     * @param int|string|null $chatId
     * @param int|null $messageId
     * @param string|null $inlineMessageId
     * @param string|null $parseMode
     * @param array|null $entities
     * @param bool|null $disableWebPagePreview
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function editMessageText(
        string $text,
        ?int|string $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null,
        ?string $parseMode = null,
        ?array $entities = null,
        ?bool $disableWebPagePreview = null,
        ?array $replyMarkup = null
    ): array {
        $params = ['text' => $text];

        if ($chatId !== null) $params['chat_id'] = $chatId;
        if ($messageId !== null) $params['message_id'] = $messageId;
        if ($inlineMessageId !== null) $params['inline_message_id'] = $inlineMessageId;
        if ($parseMode !== null) $params['parse_mode'] = $parseMode;
        if ($entities !== null) $params['entities'] = json_encode($entities);
        if ($disableWebPagePreview !== null) $params['disable_web_page_preview'] = $disableWebPagePreview;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('editMessageText', $params);
    }

    /**
     * Edit message caption
     *
     * @param int|string|null $chatId
     * @param int|null $messageId
     * @param string|null $inlineMessageId
     * @param string|null $caption
     * @param string|null $parseMode
     * @param array|null $captionEntities
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function editMessageCaption(
        ?int|string $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null,
        ?string $caption = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?array $replyMarkup = null
    ): array {
        $params = [];

        if ($chatId !== null) $params['chat_id'] = $chatId;
        if ($messageId !== null) $params['message_id'] = $messageId;
        if ($inlineMessageId !== null) $params['inline_message_id'] = $inlineMessageId;
        if ($caption !== null) $params['caption'] = $caption;
        if ($parseMode !== null) $params['parse_mode'] = $parseMode;
        if ($captionEntities !== null) $params['caption_entities'] = json_encode($captionEntities);
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('editMessageCaption', $params);
    }

    /**
     * Edit message media
     *
     * @param array $media
     * @param int|string|null $chatId
     * @param int|null $messageId
     * @param string|null $inlineMessageId
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function editMessageMedia(
        array $media,
        ?int|string $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null,
        ?array $replyMarkup = null
    ): array {
        $params = ['media' => json_encode($media)];

        if ($chatId !== null) $params['chat_id'] = $chatId;
        if ($messageId !== null) $params['message_id'] = $messageId;
        if ($inlineMessageId !== null) $params['inline_message_id'] = $inlineMessageId;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('editMessageMedia', $params);
    }

    /**
     * Edit message reply markup
     *
     * @param int|string|null $chatId
     * @param int|null $messageId
     * @param string|null $inlineMessageId
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function editMessageReplyMarkup(
        ?int|string $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null,
        ?array $replyMarkup = null
    ): array {
        $params = [];

        if ($chatId !== null) $params['chat_id'] = $chatId;
        if ($messageId !== null) $params['message_id'] = $messageId;
        if ($inlineMessageId !== null) $params['inline_message_id'] = $inlineMessageId;
        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('editMessageReplyMarkup', $params);
    }

    /**
     * Stop poll
     *
     * @param int|string $chatId
     * @param int $messageId
     * @param array|null $replyMarkup
     * @return array
     * @throws Exception
     */
    public function stopPoll(int|string $chatId, int $messageId, ?array $replyMarkup = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];

        if ($replyMarkup !== null) $params['reply_markup'] = json_encode($replyMarkup);

        return $this->makeRequest('stopPoll', $params);
    }

    /**
     * Delete message
     *
     * @param int|string $chatId
     * @param int $messageId
     * @return array
     * @throws Exception
     */
    public function deleteMessage(int|string $chatId, int $messageId): array
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];

        return $this->makeRequest('deleteMessage', $params);
    }
}