<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use HeyCart\Deployment\Command\OneTimeTaskUnmarkCommand;
use HeyCart\Deployment\Services\OneTimeTasks;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(OneTimeTaskUnmarkCommand::class)]
class OneTimeTaskUnmarkCommandTest extends TestCase
{
    public function testUnmark(): void
    {
        $taskService = $this->createMock(OneTimeTasks::class);
        $taskService
            ->expects($this->once())
            ->method('remove')
            ->with('test');

        $cmd = new OneTimeTaskUnmarkCommand($taskService);
        $tester = new CommandTester($cmd);
        $tester->execute(['id' => 'test']);

        $tester->assertCommandIsSuccessful();
    }
}
