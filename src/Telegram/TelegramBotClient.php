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

    /** @return array<string, mixed> */
    public function getMe(): array
    {
        $response = $this->http->get($this->apiUrl('getMe'));
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload) || ($payload['ok'] ?? false) !== true || !is_array($payload['result'] ?? null)) {
            throw new RuntimeException('Telegram getMe response was invalid.');
        }

        return $payload['result'];
    }

    /** @return list<array{id:string,title:string,type:string}> */
    public function recentChats(): array
    {
        $response = $this->http->get($this->apiUrl('getUpdates'), [
            'query' => [
                'limit' => 100,
                'timeout' => 0,
                'allowed_updates' => json_encode(['message', 'channel_post', 'my_chat_member'], JSON_THROW_ON_ERROR),
            ],
        ]);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload) || ($payload['ok'] ?? false) !== true || !is_array($payload['result'] ?? null)) {
            throw new RuntimeException('Telegram getUpdates response was invalid.');
        }

        $chats = [];
        foreach ($payload['result'] as $update) {
            if (!is_array($update)) {
                continue;
            }

            $chat = null;
            foreach (['message', 'channel_post', 'my_chat_member'] as $key) {
                if (isset($update[$key]['chat']) && is_array($update[$key]['chat'])) {
                    $chat = $update[$key]['chat'];
                    break;
                }
            }

            if (!is_array($chat) || !isset($chat['id'])) {
                continue;
            }

            $id = (string) $chat['id'];
            $title = trim((string) ($chat['title'] ?? ''));
            if ($title === '') {
                $title = trim(implode(' ', array_filter([
                    (string) ($chat['first_name'] ?? ''),
                    (string) ($chat['last_name'] ?? ''),
                ])));
            }
            if ($title === '') {
                $title = (string) ($chat['username'] ?? $id);
            }

            $chats[$id] = [
                'id' => $id,
                'title' => $title,
                'type' => (string) ($chat['type'] ?? 'unknown'),
            ];
        }

        return array_values($chats);
    }

    /** @return array<string, mixed> */
    public function getChat(int|string $chatId): array
    {
        $response = $this->http->get($this->apiUrl('getChat'), [
            'query' => ['chat_id' => $chatId],
        ]);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload) || ($payload['ok'] ?? false) !== true || !is_array($payload['result'] ?? null)) {
            throw new RuntimeException('Telegram getChat response was invalid.');
        }

        return $payload['result'];
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        $this->http->post($this->apiUrl('sendMessage'), [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
            ],
        ]);
    }

    private function apiUrl(string $method): string
    {
        $token = trim((string) $this->config->get('telegram.bot_token', ''));
        if ($token === '') {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        $baseUrl = rtrim((string) $this->config->get('telegram.bot_api_base_url', 'https://api.telegram.org'), '/');
        return $baseUrl . '/bot' . $token . '/' . $method;
    }
}
