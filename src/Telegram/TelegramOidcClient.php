<?php

declare(strict_types=1);

namespace DKAnnual\Telegram;

use DKAnnual\Config;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use JsonException;
use RuntimeException;

final class TelegramOidcClient
{
    private readonly Client $http;

    public function __construct(private readonly Config $config)
    {
        $this->http = new Client([
            'timeout' => 10.0,
            'http_errors' => true,
        ]);
    }

    public function authorizationUrl(string $state, string $nonce, string $codeChallenge): string
    {
        $clientId = $this->requiredConfig('telegram.client_id');
        $redirectUri = $this->requiredConfig('telegram.redirect_uri');
        $authorizationUrl = (string) $this->config->get('telegram.authorization_url', 'https://oauth.telegram.org/auth');
        $scopes = $this->config->get('telegram.scopes', ['openid', 'profile', 'telegram:bot_access']);
        if (!is_array($scopes) || $scopes === []) {
            $scopes = ['openid', 'profile', 'telegram:bot_access'];
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', array_map('strval', $scopes)),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return $authorizationUrl . '?' . $query;
    }

    /** @return array<string, mixed> */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $clientId = $this->requiredConfig('telegram.client_id');
        $clientSecret = $this->requiredConfig('telegram.client_secret');
        $redirectUri = $this->requiredConfig('telegram.redirect_uri');
        $tokenUrl = (string) $this->config->get('telegram.token_url', 'https://oauth.telegram.org/token');

        $response = $this->http->post($tokenUrl, [
            'auth' => [$clientId, $clientSecret],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'code_verifier' => $codeVerifier,
            ],
        ]);

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Telegram token response was not valid JSON.', 0, $exception);
        }

        if (!is_array($data) || !isset($data['id_token']) || !is_string($data['id_token'])) {
            throw new RuntimeException('Telegram token response did not include an ID token.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function verifyIdToken(string $idToken, string $expectedNonce): array
    {
        $jwksUrl = (string) $this->config->get('telegram.jwks_url', 'https://oauth.telegram.org/.well-known/jwks.json');
        $response = $this->http->get($jwksUrl);

        try {
            $jwks = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Telegram JWKS response was not valid JSON.', 0, $exception);
        }

        if (!is_array($jwks)) {
            throw new RuntimeException('Telegram JWKS response was invalid.');
        }

        $decoded = JWT::decode($idToken, JWK::parseKeySet($jwks));
        $claims = get_object_vars($decoded);

        $issuer = (string) $this->config->get('telegram.issuer', 'https://oauth.telegram.org');
        if (($claims['iss'] ?? null) !== $issuer) {
            throw new RuntimeException('Telegram ID token issuer did not match.');
        }

        $clientId = $this->requiredConfig('telegram.client_id');
        $audience = $claims['aud'] ?? null;
        $audienceMatches = is_array($audience)
            ? in_array($clientId, array_map('strval', $audience), true)
            : is_scalar($audience) && hash_equals($clientId, (string) $audience);

        if (!$audienceMatches) {
            throw new RuntimeException('Telegram ID token audience did not match.');
        }

        $nonce = $claims['nonce'] ?? null;
        if (!is_string($nonce) || !hash_equals($expectedNonce, $nonce)) {
            throw new RuntimeException('Telegram ID token nonce did not match.');
        }

        return $claims;
    }

    private function requiredConfig(string $key): string
    {
        $value = $this->config->get($key);
        if (!is_scalar($value) || trim((string) $value) === '') {
            throw new RuntimeException(sprintf('Missing required configuration: %s', $key));
        }

        return trim((string) $value);
    }
}
