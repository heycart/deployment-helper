<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Tests\Helper;

use HeyCart\Deployment\Helper\EnvironmentHelper;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EnvironmentHelper::class)]
class EnvironmentHelperTest extends TestCase
{
    #[BackupGlobals(true)]
    public function testGet(): void
    {
        static::assertNull(EnvironmentHelper::getVariable('FOO'));
        static::assertSame('bla', EnvironmentHelper::getVariable('FOO', 'bla'));
        $_SERVER['FOO'] = 'bar';
        static::assertSame('bar', EnvironmentHelper::getVariable('FOO'));
    }

    #[BackupGlobals(true)]
    public function testHas(): void
    {
        static::assertFalse(EnvironmentHelper::hasVariable('FOO'));
        $_SERVER['FOO'] = 'bar';
        static::assertTrue(EnvironmentHelper::hasVariable('FOO'));
    }
}
