<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Tests\Command;

use HeyCart\Deployment\Command\OneTimeTaskListCommand;
use HeyCart\Deployment\Services\OneTimeTasks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(OneTimeTaskListCommand::class)]
class OneTimeTaskListCommandTest extends TestCase
{
    public function testList(): void
    {
        $taskService = $this->createMock(OneTimeTasks::class);
        $taskService->method('getExecutedTasks')->willReturn([
            'test' => ['created_at' => '2021-01-01 00:00:00'],
        ]);

        $cmd = new OneTimeTaskListCommand($taskService);
        $tester = new CommandTester($cmd);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('test', $tester->getDisplay());
        static::assertStringContainsString('2021-01-01 00:00:00', $tester->getDisplay());
    }
}
