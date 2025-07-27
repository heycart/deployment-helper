<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Tests\Services;

use HeyCart\Deployment\Helper\ProcessHelper;
use HeyCart\Deployment\Services\PluginLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PluginLoader::class)]
class PluginLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $processHelper
            ->method('getPluginList')
            ->willReturn('[{"name":"TestPlugin", "version": "1.0.0", "composerName": "test/test-plugin", "path": "custom/plugins/TestPlugin", "active": true, "installedAt": "2021-01-01 00:00:00", "upgradeVersion": "1.0.1"}]');

        $plugins = (new PluginLoader(__DIR__, $processHelper))->all();

        static::assertCount(1, $plugins);
        static::assertArrayHasKey('TestPlugin', $plugins);
    }

    public function testLoadDependenciesInRightOrder(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $data = [
            [
                'name' => 'Plugin1',
                'composerName' => 'plugin/1',
                'path' => '_fixtures/plugin1',
                'version' => '1.0.0',
            ],
            [
                'name' => 'Plugin2',
                'composerName' => 'plugin/2',
                'path' => '_fixtures/plugin2',
                'version' => '1.0.0',
            ],
        ];

        $processHelper
            ->method('getPluginList')
            ->willReturn(json_encode($data, \JSON_THROW_ON_ERROR));

        $plugins = (new PluginLoader(__DIR__, $processHelper))->all();

        static::assertCount(2, $plugins);
        static::assertSame(
            [
                'Plugin2' => [
                    'name' => 'Plugin2',
                    'composerName' => 'plugin/2',
                    'path' => '_fixtures/plugin2',
                    'version' => '1.0.0',
                ],
                'Plugin1' => [
                    'name' => 'Plugin1',
                    'composerName' => 'plugin/1',
                    'path' => '_fixtures/plugin1',
                    'version' => '1.0.0',
                ],
            ],
            $plugins,
        );
    }

    public function testLoadDependenciesInRightOrderWithReplaces(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $data = [
            [
                'name' => 'Plugin1',
                'composerName' => 'store.heycart.com/plugin1',
                'path' => 'plugin1',
                'version' => '1.0.0',
            ],
            [
                'name' => 'Plugin2',
                'composerName' => 'store.heycart.com/plugin2',
                'path' => 'plugin2',
                'version' => '1.0.0',
            ],
        ];

        $processHelper
            ->method('getPluginList')
            ->willReturn(json_encode($data, \JSON_THROW_ON_ERROR));

        $plugins = (new PluginLoader(__DIR__ . '/_fixtures', $processHelper))->all();

        static::assertCount(2, $plugins);
        static::assertSame(
            [
                'Plugin2' => [
                    'name' => 'Plugin2',
                    'composerName' => 'store.heycart.com/plugin2',
                    'path' => 'plugin2',
                    'version' => '1.0.0',
                ],
                'Plugin1' => [
                    'name' => 'Plugin1',
                    'composerName' => 'store.heycart.com/plugin1',
                    'path' => 'plugin1',
                    'version' => '1.0.0',
                ],
            ],
            $plugins,
        );
    }
}
