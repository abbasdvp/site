<?php

namespace Core;

use Exception;

/**
 * BotCore class - Main bot handler, manages updates and routing
 */
class BotCore
{
    private TelegramAPI $telegram;
    private array $handlers;
    private array $commands;
    private array $callbacks;
    private array $messageHandlers;

    /**
     * Constructor
     *
     * @param TelegramAPI $telegram
     */
    public function __construct(TelegramAPI $telegram)
    {
        $this->telegram = $telegram;
        $this->handlers = [];
        $this->commands = [];
        $this->callbacks = [];
        $this->messageHandlers = [];
    }

    /**
     * Register a command handler
     *
     * @param string $command
     * @param callable $handler
     */
    public function registerCommand(string $command, callable $handler): void
    {
        $this->commands[$command] = $handler;
    }

    /**
     * Register a callback handler
     *
     * @param string $pattern
     * @param callable $handler
     */
    public function registerCallback(string $pattern, callable $handler): void
    {
        $this->callbacks[$pattern] = $handler;
    }

    /**
     * Register a message handler
     *
     * @param string $pattern
     * @param callable $handler
     */
    public function registerMessageHandler(string $pattern, callable $handler): void
    {
        $this->messageHandlers[$pattern] = $handler;
    }

    /**
     * Handle incoming webhook update
     *
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            $update = $this->telegram->getWebhookUpdates();
            
            if (empty($update)) {
                $this->logError('Empty update received');
                return;
            }

            // Process different types of updates
            if (isset($update['message'])) {
                $this->processMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->processCallbackQuery($update['callback_query']);
            } elseif (isset($update['edited_message'])) {
                $this->processEditedMessage($update['edited_message']);
            } elseif (isset($update['channel_post'])) {
                $this->processChannelPost($update['channel_post']);
            } elseif (isset($update['edited_channel_post'])) {
                $this->processEditedChannelPost($update['edited_channel_post']);
            } elseif (isset($update['inline_query'])) {
                $this->processInlineQuery($update['inline_query']);
            } elseif (isset($update['chosen_inline_result'])) {
                $this->processChosenInlineResult($update['chosen_inline_result']);
            } elseif (isset($update['shipping_query'])) {
                $this->processShippingQuery($update['shipping_query']);
            } elseif (isset($update['pre_checkout_query'])) {
                $this->processPreCheckoutQuery($update['pre_checkout_query']);
            }
        } catch (Exception $e) {
            $this->logError('Error handling update: ' . $e->getMessage());
        }
    }

    /**
     * Process a regular message
     *
     * @param array $message
     * @return void
     */
    private function processMessage(array $message): void
    {
        try {
            // Extract text and check for commands
            $text = $message['text'] ?? '';
            $chatId = $message['chat']['id'] ?? null;
            $userId = $message['from']['id'] ?? null;
            $messageId = $message['message_id'] ?? null;

            if ($chatId === null || $userId === null || $messageId === null) {
                $this->logError('Missing required fields in message: ' . json_encode($message));
                return;
            }

            // Check for commands
            if (strpos($text, '/') === 0) {
                $this->processCommand($text, $message);
                return;
            }

            // Check for forwarded messages
            if (isset($message['forward_from_chat'])) {
                $this->processForwardedMessage($message);
                return;
            }

            // Check for replies to bot messages
            if (isset($message['reply_to_message']) && 
                isset($message['reply_to_message']['from']) && 
                $message['reply_to_message']['from']['is_bot']) {
                
                $this->processReplyToBot($message);
                return;
            }

            // Process regular text messages
            $this->processRegularMessage($message);
        } catch (Exception $e) {
            $this->logError('Error processing message: ' . $e->getMessage());
        }
    }

    /**
     * Process a command
     *
     * @param string $text
     * @param array $message
     * @return void
     */
    private function processCommand(string $text, array $message): void
    {
        $commandParts = explode(' ', trim($text), 2);
        $command = strtolower($commandParts[0]);

        // Remove bot username if present (@botname)
        if (strpos($command, '@') !== false) {
            list($command, $botName) = explode('@', $command, 2);
        }

        if (isset($this->commands[$command])) {
            try {
                call_user_func($this->commands[$command], $message);
            } catch (Exception $e) {
                $this->logError("Error executing command {$command}: " . $e->getMessage());
            }
        } else {
            // Unknown command handler
            $this->handleUnknownCommand($message);
        }
    }

    /**
     * Process a forwarded message
     *
     * @param array $message
     * @return void
     */
    private function processForwardedMessage(array $message): void
    {
        // Check if it's a forwarded message from a channel
        if (isset($message['forward_from_chat']['type']) && 
            $message['forward_from_chat']['type'] === 'channel') {
            
            $forwardFromChat = $message['forward_from_chat'];
            $channelId = $forwardFromChat['id'] ?? null;
            $channelUsername = $forwardFromChat['username'] ?? null;
            $channelTitle = $forwardFromChat['title'] ?? null;

            if ($channelId) {
                // Forwarded from channel - likely adding a channel
                $this->processChannelAddition($message, $channelId, $channelUsername, $channelTitle);
                return;
            }
        }

        // For other forwarded messages, pass to general handler
        $this->processRegularMessage($message);
    }

    /**
     * Process a reply to a bot message
     *
     * @param array $message
     * @return void
     */
    private function processReplyToBot(array $message): void
    {
        $replyToMessage = $message['reply_to_message'];
        $text = $message['text'] ?? '';

        // Check if it's a custom time reply
        if (preg_match('/^time\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2})/', $text, $matches)) {
            $this->processCustomTimeReply($message, $matches[1]);
            return;
        }

        // For other replies to bot, pass to general handler
        $this->processRegularMessage($message);
    }

    /**
     * Process a regular message
     *
     * @param array $message
     * @return void
     */
    private function processRegularMessage(array $message): void
    {
        $text = $message['text'] ?? '';
        
        // Try to match against registered message handlers
        foreach ($this->messageHandlers as $pattern => $handler) {
            if (preg_match($pattern, $text)) {
                try {
                    call_user_func($handler, $message);
                    return;
                } catch (Exception $e) {
                    $this->logError("Error executing message handler for pattern {$pattern}: " . $e->getMessage());
                }
            }
        }

        // If no pattern matched, handle as unknown message
        $this->handleUnknownMessage($message);
    }

    /**
     * Process channel addition via forwarded message
     *
     * @param array $message
     * @param int $channelId
     * @param string|null $channelUsername
     * @param string|null $channelTitle
     * @return void
     */
    private function processChannelAddition(array $message, int $channelId, ?string $channelUsername, ?string $channelTitle): void
    {
        // Look for a handler specifically for channel additions
        foreach ($this->handlers as $handlerClass => $handlerInstance) {
            if (method_exists($handlerInstance, 'addChannelByForward')) {
                try {
                    call_user_func([$handlerInstance, 'addChannelByForward'], $message, $channelId, $channelUsername, $channelTitle);
                    return;
                } catch (Exception $e) {
                    $this->logError("Error in channel addition handler: " . $e->getMessage());
                }
            }
        }

        // Fallback to general message handler
        $this->processRegularMessage($message);
    }

    /**
     * Process custom time reply
     *
     * @param array $message
     * @param string $time
     * @return void
     */
    private function processCustomTimeReply(array $message, string $time): void
    {
        // Look for a handler specifically for custom time replies
        foreach ($this->handlers as $handlerClass => $handlerInstance) {
            if (method_exists($handlerInstance, 'handleCustomTimeReply')) {
                try {
                    call_user_func([$handlerInstance, 'handleCustomTimeReply'], $message, $time);
                    return;
                } catch (Exception $e) {
                    $this->logError("Error in custom time reply handler: " . $e->getMessage());
                }
            }
        }

        // Fallback to general message handler
        $this->processRegularMessage($message);
    }

    /**
     * Process a callback query
     *
     * @param array $callbackQuery
     * @return void
     */
    private function processCallbackQuery(array $callbackQuery): void
    {
        try {
            $data = $callbackQuery['data'] ?? '';
            $id = $callbackQuery['id'] ?? null;
            $message = $callbackQuery['message'] ?? null;
            $inlineMessageId = $callbackQuery['inline_message_id'] ?? null;
            $chatInstance = $callbackQuery['chat_instance'] ?? null;
            $userId = $callbackQuery['from']['id'] ?? null;

            if ($id === null) {
                $this->logError('Missing callback query id: ' . json_encode($callbackQuery));
                return;
            }

            // Try to match against registered callback patterns
            foreach ($this->callbacks as $pattern => $handler) {
                if (preg_match($pattern, $data)) {
                    try {
                        call_user_func($handler, $callbackQuery);
                        
                        // Answer the callback query
                        $this->telegram->answerCallbackQuery($id);
                        return;
                    } catch (Exception $e) {
                        $this->logError("Error executing callback handler for pattern {$pattern}: " . $e->getMessage());
                    }
                }
            }

            // If no pattern matched, handle as unknown callback
            $this->handleUnknownCallback($callbackQuery);
            
            // Still answer the callback query even if not handled
            $this->telegram->answerCallbackQuery($id);
        } catch (Exception $e) {
            $this->logError('Error processing callback query: ' . $e->getMessage());
        }
    }

    /**
     * Process an edited message
     *
     * @param array $message
     * @return void
     */
    private function processEditedMessage(array $message): void
    {
        // Currently not handling edited messages, but could be implemented
        // For now, just log it
        $this->logInfo('Received edited message: ' . json_encode($message));
    }

    /**
     * Process a channel post
     *
     * @param array $message
     * @return void
     */
    private function processChannelPost(array $message): void
    {
        // Currently not handling channel posts directly
        // Could be implemented for specific use cases
        $this->logInfo('Received channel post: ' . json_encode($message));
    }

    /**
     * Process an edited channel post
     *
     * @param array $message
     * @return void
     */
    private function processEditedChannelPost(array $message): void
    {
        // Currently not handling edited channel posts
        $this->logInfo('Received edited channel post: ' . json_encode($message));
    }

    /**
     * Process an inline query
     *
     * @param array $inlineQuery
     * @return void
     */
    private function processInlineQuery(array $inlineQuery): void
    {
        // Currently not handling inline queries
        $this->logInfo('Received inline query: ' . json_encode($inlineQuery));
    }

    /**
     * Process a chosen inline result
     *
     * @param array $chosenInlineResult
     * @return void
     */
    private function processChosenInlineResult(array $chosenInlineResult): void
    {
        // Currently not handling chosen inline results
        $this->logInfo('Received chosen inline result: ' . json_encode($chosenInlineResult));
    }

    /**
     * Process a shipping query
     *
     * @param array $shippingQuery
     * @return void
     */
    private function processShippingQuery(array $shippingQuery): void
    {
        // Currently not handling shipping queries
        $this->logInfo('Received shipping query: ' . json_encode($shippingQuery));
    }

    /**
     * Process a pre-checkout query
     *
     * @param array $preCheckoutQuery
     * @return void
     */
    private function processPreCheckoutQuery(array $preCheckoutQuery): void
    {
        // Currently not handling pre-checkout queries
        $this->logInfo('Received pre-checkout query: ' . json_encode($preCheckoutQuery));
    }

    /**
     * Handle unknown command
     *
     * @param array $message
     * @return void
     */
    private function handleUnknownCommand(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        if ($chatId) {
            try {
                $this->telegram->sendMessage($chatId, "❌ دستور ناشناخته!\n\nلطفاً از دکمه‌های منو یا دستورات موجود استفاده کنید.");
            } catch (Exception $e) {
                $this->logError('Error sending unknown command response: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle unknown message
     *
     * @param array $message
     * @return void
     */
    private function handleUnknownMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        if ($chatId) {
            try {
                $this->telegram->sendMessage($chatId, "❌ پیام ناشناخته!\n\nلطفاً از دکمه‌های منو یا دستورات موجود استفاده کنید.");
            } catch (Exception $e) {
                $this->logError('Error sending unknown message response: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle unknown callback
     *
     * @param array $callbackQuery
     * @return void
     */
    private function handleUnknownCallback(array $callbackQuery): void
    {
        $id = $callbackQuery['id'] ?? null;
        if ($id) {
            try {
                $this->telegram->answerCallbackQuery($id, "❌ گزینه ناشناخته!", true);
            } catch (Exception $e) {
                $this->logError('Error answering unknown callback: ' . $e->getMessage());
            }
        }
    }

    /**
     * Add a handler instance to the bot core
     *
     * @param string $handlerClass
     * @param object $handlerInstance
     */
    public function addHandler(string $handlerClass, object $handlerInstance): void
    {
        $this->handlers[$handlerClass] = $handlerInstance;
    }

    /**
     * Log an info message
     *
     * @param string $message
     */
    private function logInfo(string $message): void
    {
        error_log('[INFO] ' . $message);
    }

    /**
     * Log an error message
     *
     * @param string $message
     */
    private function logError(string $message): void
    {
        error_log('[ERROR] ' . $message);
    }

    /**
     * Get registered commands
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get registered callbacks
     *
     * @return array
     */
    public function getCallbacks(): array
    {
        return $this->callbacks;
    }

    /**
     * Get registered message handlers
     *
     * @return array
     */
    public function getMessageHandlers(): array
    {
        return $this->messageHandlers;
    }
}