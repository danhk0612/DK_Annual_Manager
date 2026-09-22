<?php

declare(strict_types=1);

namespace DKAnnual\Error;

use Throwable;

final class ErrorHandler
{
    private static bool $debug = false;
    private static string $appName = 'DK Annual Manager';

    public static function register(): void
    {
        set_exception_handler(static function (Throwable $exception): void {
            error_log(sprintf(
                '[%s] %s: %s in %s:%d',
                self::$appName,
                $exception::class,
                self::redactSensitive($exception->getMessage()),
                $exception->getFile(),
                $exception->getLine(),
            ));

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('Referrer-Policy: same-origin');
            }

            $detail = self::$debug
                ? sprintf('%s: %s', $exception::class, self::redactSensitive($exception->getMessage()))
                : '요청을 처리하는 중 오류가 발생했습니다.';

            echo '<!doctype html><html lang="ko"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>오류 · ' . htmlspecialchars(self::$appName, ENT_QUOTES, 'UTF-8') . '</title>'
                . '<style>body{margin:0;background:#f5f7fb;color:#172033;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}'
                . 'main{max-width:640px;margin:12vh auto;padding:36px;background:#fff;border:1px solid #e5e9f2;border-radius:18px}'
                . 'h1{margin-top:0}p{color:#586174;line-height:1.7}a{color:#4054e8}</style></head><body><main>'
                . '<h1>서비스 오류</h1><p>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><a href="/">처음 화면으로 돌아가기</a></p></main></body></html>';
        });
    }

    private static function redactSensitive(string $message): string
    {
        $patterns = [
            '/\/bot\d+:[A-Za-z0-9_-]+\//',
            '/([?&](?:ServiceKey|service_key|client_secret|bot_token)=)[^&\s]+/i',
            '/((?:ServiceKey|service_key|client_secret|bot_token)\s*[:=]\s*)[^\s,;]+/i',
        ];

        $replacements = [
            '/bot[REDACTED]/',
            '$1[REDACTED]',
            '$1[REDACTED]',
        ];

        return preg_replace($patterns, $replacements, $message) ?? '[redacted error]';
    }

    public static function setDebug(bool $debug): void
    {
        self::$debug = $debug;
    }

    public static function setAppName(string $appName): void
    {
        $appName = trim($appName);
        if ($appName !== '') {
            self::$appName = $appName;
        }
    }
}
