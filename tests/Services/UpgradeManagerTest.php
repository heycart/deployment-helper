<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Tests\Services;

use HeyCart\Deployment\Config\ProjectConfiguration;
use HeyCart\Deployment\Helper\ProcessHelper;
use HeyCart\Deployment\Services\AccountService;
use HeyCart\Deployment\Services\AppHelper;
use HeyCart\Deployment\Services\HeyCartState;
use HeyCart\Deployment\Services\HookExecutor;
use HeyCart\Deployment\Services\OneTimeTasks;
use HeyCart\Deployment\Services\PluginHelper;
use HeyCart\Deployment\Services\UpgradeManager;
use HeyCart\Deployment\Struct\RunConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(UpgradeManager::class)]
#[CoversClass(RunConfiguration::class)]
class UpgradeManagerTest extends TestCase
{
    public function testRun(): void
    {
        $oneTimeTasks = $this->createMock(OneTimeTasks::class);
        $oneTimeTasks
            ->expects($this->once())
            ->method('execute');

        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects(static::never())->method('refresh');

        $manager = new UpgradeManager(
            $this->createMock(HeyCartState::class),
            $this->createMock(ProcessHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $hookExecutor,
            $oneTimeTasks,
            new ProjectConfiguration(),
            $accountService,
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    public function testRunUpdatesVersion(): void
    {
        $state = $this->createMock(HeyCartState::class);
        $state
            ->expects($this->exactly(3))
            ->method('getCurrentVersion')
            ->willReturn('1.0.0');

        $state
            ->expects($this->exactly(2))
            ->method('getPreviousVersion')
            ->willReturn('0.0.0');

        $state
            ->expects($this->once())
            ->method('setVersion')
            ->with('1.0.0');

        $manager = new UpgradeManager(
            $state,
            $this->createMock(ProcessHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    public function testRunUpdatesVersionNoAssetCompile(): void
    {
        $state = $this->createMock(HeyCartState::class);
        $state
            ->expects($this->exactly(3))
            ->method('getCurrentVersion')
            ->willReturn('1.0.0');

        $state
            ->expects($this->exactly(2))
            ->method('getPreviousVersion')
            ->willReturn('0.0.0');

        $state
            ->expects($this->once())
            ->method('setVersion')
            ->with('1.0.0');

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
        );

        $manager->run(new RunConfiguration(true, true), $this->createMock(OutputInterface::class));

        static::assertCount(5, $consoleCommands);
        static::assertSame(['messenger:setup-transports'], $consoleCommands[0]);
        static::assertArrayHasKey(1, $consoleCommands);
        static::assertSame(['system:update:finish', '--skip-asset-build'], $consoleCommands[1]);
    }

    #[Env('SALES_CHANNEL_URL', 'http://foo.com')]
    public function testRunWithDifferentSalesChannelUrl(): void
    {
        $state = $this->createMock(HeyCartState::class);
        $state
            ->expects($this->exactly(2))
            ->method('isStorefrontInstalled')
            ->willReturn(true);

        $state
            ->expects($this->once())
            ->method('isSalesChannelExisting')
            ->with('http://foo.com')
            ->willReturn(false);

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertCount(7, $consoleCommands);
        static::assertArrayHasKey(1, $consoleCommands);
        static::assertSame(['sales-channel:create:storefront', '--name=Storefront', '--url=http://foo.com'], $consoleCommands[1]);
    }

    public function testRunWithMaintenanceMode(): void
    {
        $state = $this->createMock(HeyCartState::class);

        $state
            ->expects($this->once())
            ->method('enableMaintenanceMode');

        $state
            ->expects($this->once())
            ->method('disableMaintenanceMode');

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $config = new ProjectConfiguration();
        $config->maintenance->enabled = true;

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            $config,
            $this->createMock(AccountService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertCount(7, $consoleCommands);
        static::assertSame(['cache:pool:clear', 'cache.http', 'cache.object'], $consoleCommands[0]);
        static::assertArrayHasKey(5, $consoleCommands);
        static::assertSame(['cache:pool:clear', 'cache.http', 'cache.object'], $consoleCommands[6]);
    }

    public function testRunWithLicenseDomain(): void
    {
        $oneTimeTasks = $this->createMock(OneTimeTasks::class);
        $oneTimeTasks
            ->expects($this->once())
            ->method('execute');

        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects(static::once())->method('refresh');

        $configuration = new ProjectConfiguration();
        $configuration->store->licenseDomain = 'example.com';

        $manager = new UpgradeManager(
            $this->createMock(HeyCartState::class),
            $this->createMock(ProcessHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $hookExecutor,
            $oneTimeTasks,
            $configuration,
            $accountService,
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }
}
