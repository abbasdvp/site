<?php

/**
 * Telegram Video Processing Bot
 * A comprehensive bot that processes videos by adding intro/outro clips and watermarks
 */

class TelegramVideoBot
{
    private $token;
    private $apiUrl;
    private $botUsername;
    
    // Configuration values loaded from config file
    private $defaultIntroVideo;
    private $defaultOutroVideo;
    private $defaultWatermarkText;
    private $defaultWatermarkSize;
    private $defaultWatermarkColor;
    private $defaultWatermarkPosition;
    private $maxFileSize;
    private $supportedFormats;
    private $tempDir;
    
    public function __construct($token)
    {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$token}";
        
        // Load configuration
        $this->loadConfig();
        
        // Get bot info to set username
        $this->botUsername = $this->getBotInfo();
    }
    
    /**
     * Load configuration from config file
     */
    private function loadConfig()
    {
        $config = include __DIR__ . '/config.php';
        
        $this->defaultIntroVideo = $config['default_intro_video'] ?? 'https://nyfer.hostme.top/default_intro.mp4';
        $this->defaultOutroVideo = $config['default_outro_video'] ?? 'https://nyfer.hostme.top/default_outro.mp4';
        $this->defaultWatermarkText = $config['default_watermark_text'] ?? 'User ID: {USER_ID}';
        $this->defaultWatermarkSize = $config['default_watermark_size'] ?? 24;
        $this->defaultWatermarkColor = $config['default_watermark_color'] ?? 'white';
        $this->defaultWatermarkPosition = $config['default_watermark_position'] ?? 'bottom-right';
        $this->maxFileSize = $config['max_file_size'] ?? 50 * 1024 * 1024; // 50MB
        $this->supportedFormats = $config['supported_formats'] ?? ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm'];
        $this->tempDir = $config['temp_dir'] ?? sys_get_temp_dir();
    }
    
    /**
     * Get bot information to retrieve username
     */
    private function getBotInfo()
    {
        $response = $this->makeApiRequest('getMe');
        if ($response && isset($response['result']['username'])) {
            return $response['result']['username'];
        }
        return null;
    }
    
    /**
     * Make API request to Telegram
     */
    private function makeApiRequest($method, $params = [], $files = [])
    {
        $url = $this->apiUrl . '/' . $method;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if (!empty($files)) {
            $params = array_merge($params, $files);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } elseif (!empty($params)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Download file from Telegram
     */
    private function downloadFile($fileId, $filePath)
    {
        $fileUrl = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $data !== false) {
            file_put_contents($filePath, $data);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get file path from file ID
     */
    private function getFile($fileId)
    {
        $response = $this->makeApiRequest('getFile', ['file_id' => $fileId]);
        if ($response && isset($response['result']['file_path'])) {
            return $response['result']['file_path'];
        }
        return false;
    }
    
    /**
     * Process video with intro, outro and watermark
     */
    private function processVideo($inputPath, $userId, $chatId, $messageId)
    {
        // Create temporary directory for processing
        $tempDir = sys_get_temp_dir() . '/video_processing_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        try {
            // Define paths
            $introPath = $tempDir . '/intro.mp4';
            $outroPath = $tempDir . '/outro.mp4';
            $watermarkedInputPath = $tempDir . '/watermarked_input.mp4';
            $outputPath = $tempDir . '/output.mp4';
            
            // Download default intro and outro videos
            $this->downloadFileFromUrl($this->defaultIntroVideo, $introPath);
            $this->downloadFileFromUrl($this->defaultOutroVideo, $outroPath);
            
            // Add watermark to input video
            $this->addWatermarkToVideo($inputPath, $watermarkedInputPath, $userId);
            
            // Concatenate videos: intro + watermarked_input + outro
            $this->concatenateVideos([$introPath, $watermarkedInputPath, $outroPath], $outputPath);
            
            // Send processed video back to user
            $this->sendVideo($chatId, $outputPath, $messageId);
            
            // Clean up temporary files
            $this->cleanupTempFiles([$introPath, $outroPath, $watermarkedInputPath, $outputPath, $tempDir]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error processing video: " . $e->getMessage());
            
            // Clean up even if there was an error
            $this->cleanupTempFiles([$introPath, $outroPath, $watermarkedInputPath, $outputPath, $tempDir]);
            
            return false;
        }
    }
    
    /**
     * Download file from URL
     */
    private function downloadFileFromUrl($url, $localPath)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $data !== false) {
            file_put_contents($localPath, $data);
            return true;
        }
        
        return false;
    }
    
    /**
     * Add watermark to video
     */
    private function addWatermarkToVideo($inputPath, $outputPath, $userId)
    {
        // Replace placeholder in watermark text with actual user ID
        $watermarkText = str_replace('{USER_ID}', $userId, $this->defaultWatermarkText);
        
        // Build FFmpeg command for adding watermark
        $fontSize = $this->defaultWatermarkSize;
        $color = $this->defaultWatermarkColor;
        
        // Position mapping for FFmpeg
        $positionMap = [
            'top-left' => '10:10',
            'top-right' => 'w-tw-10:10',
            'bottom-left' => '10:h-th-10',
            'bottom-right' => 'w-tw-10:h-th-10',
            'center' => '(w-tw)/2:(h-th)/2'
        ];
        
        $position = $positionMap[$this->defaultWatermarkPosition] ?? $positionMap['bottom-right'];
        
        // Escape special characters in text for FFmpeg
        $escapedText = escapeshellarg($watermarkText);
        
        $cmd = "ffmpeg -i \"{$inputPath}\" -vf \"drawtext=fontfile=/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf:fontsize={$fontSize}:fontcolor={$color}@0.8:x={$position}:y={$position}:text={$escapedText}\" -c:a copy \"{$outputPath}\" -y 2>&1";
        
        // Execute the command
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            error_log("FFmpeg watermark command failed: " . $cmd);
            error_log("FFmpeg output: " . implode("\n", $output));
            throw new Exception("FFmpeg failed to add watermark: " . implode("\n", $output));
        }
    }
    
    /**
     * Concatenate multiple videos
     */
    private function concatenateVideos($inputPaths, $outputPath)
    {
        // Create a temporary file listing all input files
        $listFile = tempnam(sys_get_temp_dir(), 'video_list_');
        $listContent = '';
        
        foreach ($inputPaths as $path) {
            if (file_exists($path)) {
                // Properly escape the path for the list file
                $escapedPath = str_replace("'", "'\\''", $path); // For single quotes in the path
                $listContent .= "file '{$escapedPath}'\n";
            }
        }
        
        file_put_contents($listFile, $listContent);
        
        // Execute FFmpeg concatenate command
        $cmd = "ffmpeg -f concat -safe 0 -i \"{$listFile}\" -c copy \"{$outputPath}\" -y 2>&1";
        exec($cmd, $output, $returnCode);
        
        // Remove the temporary list file
        unlink($listFile);
        
        if ($returnCode !== 0) {
            error_log("FFmpeg concatenate command failed: " . $cmd);
            error_log("FFmpeg output: " . implode("\n", $output));
            throw new Exception("FFmpeg failed to concatenate videos: " . implode("\n", $output));
        }
    }
    
    /**
     * Send video back to user
     */
    private function sendVideo($chatId, $videoPath, $replyToMessageId = null)
    {
        $params = [
            'chat_id' => $chatId,
            'reply_to_message_id' => $replyToMessageId
        ];
        
        $files = [
            'video' => new CURLFile($videoPath)
        ];
        
        $this->makeApiRequest('sendVideo', $params, $files);
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles($paths)
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                // Recursively delete directory
                $this->deleteDirectory($path);
            }
        }
    }
    
    /**
     * Delete directory recursively
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
    
    /**
     * Handle incoming updates
     */
    public function handleUpdate()
    {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);
        
        if (!$update) {
            return;
        }
        
        // Check if it's a video message
        if (isset($update['message']['video']) || isset($update['message']['document'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $userId = $message['from']['id'];
            $messageId = $message['message_id'];
            
            // Determine if it's a video or document
            $videoInfo = null;
            if (isset($message['video'])) {
                $videoInfo = $message['video'];
            } elseif (isset($message['document']) && strpos($message['document']['mime_type'], 'video/') === 0) {
                $videoInfo = $message['document'];
            }
            
            if ($videoInfo) {
                $fileId = $videoInfo['file_id'];
                
                // Get file path from Telegram
                $filePath = $this->getFile($fileId);
                if ($filePath) {
                    // Create temporary file for the video
                    $tempVideoPath = tempnam(sys_get_temp_dir(), 'telegram_video_');
                    
                    // Download the video
                    if ($this->downloadFile($fileId, $tempVideoPath)) {
                        // Process the video
                        if ($this->processVideo($tempVideoPath, $userId, $chatId, $messageId)) {
                            // Send success message
                            $this->sendMessage($chatId, "✅ ویدیو شما با موفقیت پردازش شد و ارسال گردید.", $messageId);
                        } else {
                            $this->sendMessage($chatId, "❌ خطایی در پردازش ویدیو رخ داد. لطفاً دوباره تلاش کنید.", $messageId);
                        }
                        
                        // Clean up temporary video file
                        if (file_exists($tempVideoPath)) {
                            unlink($tempVideoPath);
                        }
                    } else {
                        $this->sendMessage($chatId, "❌ خطا در دانلود ویدیو. لطفاً دوباره تلاش کنید.", $messageId);
                    }
                } else {
                    $this->sendMessage($chatId, "❌ نمی‌توان به ویدیو دسترسی پیدا کرد. لطفاً دوباره تلاش کنید.", $messageId);
                }
            }
        }
        // Handle forwarded videos
        elseif (isset($update['message']['forward_from_chat']['id'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $userId = $message['from']['id'];
            $messageId = $message['message_id'];
            
            // Check if forwarded message contains video
            if (isset($message['video']) || isset($message['document'])) {
                $videoInfo = null;
                if (isset($message['video'])) {
                    $videoInfo = $message['video'];
                } elseif (isset($message['document']) && strpos($message['document']['mime_type'], 'video/') === 0) {
                    $videoInfo = $message['document'];
                }
                
                if ($videoInfo) {
                    $fileId = $videoInfo['file_id'];
                    
                    // Get file path from Telegram
                    $filePath = $this->getFile($fileId);
                    if ($filePath) {
                        // Create temporary file for the video
                        $tempVideoPath = tempnam(sys_get_temp_dir(), 'telegram_video_');
                        
                        // Download the video
                        if ($this->downloadFile($fileId, $tempVideoPath)) {
                            // Process the video
                            if ($this->processVideo($tempVideoPath, $userId, $chatId, $messageId)) {
                                // Send success message
                                $this->sendMessage($chatId, "✅ ویدیو فوروارد شده با موفقیت پردازش شد و ارسال گردید.", $messageId);
                            } else {
                                $this->sendMessage($chatId, "❌ خطایی در پردازش ویدیو فوروارد شده رخ داد. لطفاً دوباره تلاش کنید.", $messageId);
                            }
                            
                            // Clean up temporary video file
                            if (file_exists($tempVideoPath)) {
                                unlink($tempVideoPath);
                            }
                        } else {
                            $this->sendMessage($chatId, "❌ خطا در دانلود ویدیو فوروارد شده. لطفاً دوباره تلاش کنید.", $messageId);
                        }
                    } else {
                        $this->sendMessage($chatId, "❌ نمی‌توان به ویدیو فوروارد شده دسترسی پیدا کرد. لطفاً دوباره تلاش کنید.", $messageId);
                    }
                }
            }
        }
        // Handle text commands
        elseif (isset($update['message']['text'])) {
            $text = trim($update['message']['text']);
            $chatId = $update['message']['chat']['id'];
            $userId = $update['message']['from']['id'];
            $messageId = $update['message']['message_id'];
            
            if ($text === '/start') {
                $welcomeMsg = "🎉 سلام! به ربات پردازش ویدیو خوش آمدید!\n\n"
                    . "✨ امکانات:\n"
                    . "- ارسال ویدیو برای افزودن Intro/Outro و واترمارک\n"
                    . "- پشتیبانی از فوروارد ویدیو\n"
                    . "- واترمارک شخصی‌سازی شده با اطلاعات کاربر\n\n"
                    . " просто ویدیو خود را ارسال کنید تا پردازش شود.";
                $this->sendMessage($chatId, $welcomeMsg, $messageId);
            } elseif ($text === '/help') {
                $helpMsg = "📋 راهنمای استفاده:\n\n"
                    . "1. ویدیوی مورد نظر خود را ارسال کنید\n"
                    . "2. ربات ویدیو را با کلیپ‌های پیش‌فرض و واترمارک پردازش می‌کند\n"
                    . "3. ویدیوی نهایی به شما ارسال می‌شود\n\n"
                    . "💡 نکات:\n"
                    . "- فرمت‌های پشتیبانی شده: MP4, MOV, AVI و سایر فرمت‌های رایج\n"
                    . "- حداکثر حجم ویدیو: 50 مگابایت\n"
                    . "- می‌توانید ویدیو را فوروارد کنید تا پردازش شود";
                $this->sendMessage($chatId, $helpMsg, $messageId);
            }
        }
    }
    
    /**
     * Send message to chat
     */
    private function sendMessage($chatId, $text, $replyToMessageId = null)
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text
        ];
        
        if ($replyToMessageId) {
            $params['reply_to_message_id'] = $replyToMessageId;
        }
        
        $this->makeApiRequest('sendMessage', $params);
    }
}

// Initialize and run the bot
$botToken = '8296306367:AAHh2cS7PW1MfYrL-VsOOpPHbu29csvcEGU';
$bot = new TelegramVideoBot($botToken);
$bot->handleUpdate();