<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GreenApiService
{
    protected string $idInstance;
    protected string $apiTokenInstance;
    protected string $baseUrl = 'https://api.green-api.com';
    protected bool $isConfigured = false;

    public function __construct()
    {
        $this->idInstance = env('GREEN_API_ID_INSTANCE') ?? Setting::where('key', 'whatsapp_green_api_id_instance')->value('value') ?? '';
        $this->apiTokenInstance = env('GREEN_API_TOKEN_INSTANCE') ?? Setting::where('key', 'whatsapp_green_api_api_token_instance')->value('value') ?? '';

        if (!empty($this->idInstance) && !empty($this->apiTokenInstance)) {
            $this->isConfigured = true;
        }
    }

    /**
     * Send a WhatsApp message.
     *
     * @param string $chatId The recipient's phone number with country code + @c.us (e.g., 9665XXXXXXXX@c.us)
     * @param string $message The message content
     * @return array|null
     */
    public function sendMessage(string $chatId, string $message): ?array
    {
        if (!$this->isConfigured) {
            Log::error('GreenApiService: API ID or Token is not configured.');
            return null;
        }

        // Standardize chatId
        if (!str_contains($chatId, '@')) {
            $chatId = preg_replace('/[^\d]/', '', $chatId) . '@c.us';
        }

        $url = "{$this->baseUrl}/waInstance{$this->idInstance}/sendMessage/{$this->apiTokenInstance}";

        try {
            $response = Http::post($url, [
                'chatId' => $chatId,
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info('GreenApiService: Message sent successfully.', ['chatId' => $chatId]);
                return $response->json();
            }

            Log::error('GreenApiService: Failed to send message.', [
                'chatId' => $chatId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('GreenApiService: Exception sending message.', [
                'chatId' => $chatId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public function isEnabled(): bool
    {
        $envEnabled = env('WHATSAPP_ENABLED');
        if ($envEnabled !== null) {
            return filter_var($envEnabled, FILTER_VALIDATE_BOOLEAN);
        }
        return filter_var(Setting::where('key', 'whatsapp_enabled')->value('value'), FILTER_VALIDATE_BOOLEAN);
    }
}
