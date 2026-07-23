<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected $botToken;

    protected $defaultChatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->defaultChatId = config('services.telegram.chat_id');
    }

    /**
     * Send a text message to a specific Telegram chat or the default one.
     */
    public function sendMessage(string $message, ?string $chatId = null): bool
    {
        $chatId = $chatId ?? $this->defaultChatId;

        if (empty($this->botToken) || empty($chatId)) {
            Log::warning('TelegramBotService: bot_token or chat_id is missing. Message not sent.', [
                'message' => $message,
            ]);

            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if (! $response->successful()) {
                Log::error('TelegramBotService Error: '.$response->body());

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('TelegramBotService Exception: '.$e->getMessage());

            return false;
        }
    }
}
