<?php
/**
 * Webhook setup script for Telegram bot
 * This script sets the webhook URL for the bot
 */

$botToken = '8296306367:AAHh2cS7PW1MfYrL-VsOOpPHbu29csvcEGU';
$webhookUrl = 'https://nyfer.hostme.top/index.php'; // Update this to your actual domain

$url = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $responseData = json_decode($response, true);
    if ($responseData['ok']) {
        echo "✅ Webhook set successfully!\n";
        echo "URL: {$webhookUrl}\n";
        echo "Result: " . print_r($responseData['result'], true) . "\n";
    } else {
        echo "❌ Failed to set webhook:\n";
        echo $response . "\n";
    }
} else {
    echo "❌ HTTP Error: {$httpCode}\n";
    echo $response . "\n";
}