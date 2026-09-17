<?php

declare(strict_types=1);

namespace DKAnnual\Telegram;

use DKAnnual\Config;
use GuzzleHttp\Client;
use RuntimeException;

final class TelegramBotClient
{
    private readonly Client $http;

    public function __construct(private readonly Config $config)
    {
        $this->http = new Client([
            'timeout' => 10.0,
            'http_errors' => true,
        ]);
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        $token = trim((string) $this->config->get('telegram.bot_token', ''));
        if ($token === '') {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        $baseUrl = rtrim((string) $this->config->get('telegram.bot_api_base_url', 'https://api.telegram.org'), '/');

        $this->http->post($baseUrl . '/bot' . $token . '/sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
            ],
        ]);
    }
}
