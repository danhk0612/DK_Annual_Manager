<?php

declare(strict_types=1);

use DKAnnual\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testRuntimeOverrideCanReplaceNestedSetting(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dkannual-config-');
        self::assertNotFalse($path);

        file_put_contents($path, <<<'PHP'
<?php
return [
    'telegram' => [
        'bot_token' => 'old-token',
    ],
];
PHP);

        try {
            $config = new Config($path);
            self::assertSame('old-token', $config->get('telegram.bot_token'));

            $config->set('telegram.bot_token', 'new-token');
            $config->set('holiday_api.service_key', 'service-key');

            self::assertSame('new-token', $config->get('telegram.bot_token'));
            self::assertSame('service-key', $config->get('holiday_api.service_key'));
        } finally {
            @unlink($path);
        }
    }
}
