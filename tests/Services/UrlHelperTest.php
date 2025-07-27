<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use HeyCart\Deployment\Services\UrlHelper;

/**
 * @internal
 */
#[CoversClass(UrlHelper::class)]
class UrlHelperTest extends TestCase
{
    #[DataProvider('normalizeCases')]
    public function testNormalizeUrls(string $input, string $expected): void
    {
        static::assertSame($expected, UrlHelper::normalizeSalesChannelUrl($input));
    }

    public static function normalizeCases(): \Generator
    {
        yield ['http://localhost', 'http://localhost'];
        yield ['http://localhost/', 'http://localhost'];
        yield ['http://localhost/foo', 'http://localhost/foo'];
    }
}
