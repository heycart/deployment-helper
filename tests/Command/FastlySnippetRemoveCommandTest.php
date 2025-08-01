<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Tests\Command;

use HeyCart\Deployment\Command\FastlySnippetRemoveCommand;
use HeyCart\Deployment\Integration\Fastly\FastlyAPIClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(FastlySnippetRemoveCommand::class)]
class FastlySnippetRemoveCommandTest extends TestCase
{
    public function testRunCommandWithoutEnv(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->never())
            ->method('setApiKey');

        $fastlyAPIClient
            ->expects($this->never())
            ->method('getCurrentlyActiveVersion');

        $fastlyAPIClient
            ->expects($this->never())
            ->method('listSnippets');

        $command = new FastlySnippetRemoveCommand($fastlyAPIClient);
        $tester = new CommandTester($command);

        $tester->execute(['snippetName' => 'test']);

        static::assertEquals(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('FASTLY_API_TOKEN or FASTLY_SERVICE_ID is not set.', $tester->getDisplay());
    }

    #[Env('FASTLY_API_TOKEN', 'apiToken')]
    #[Env('FASTLY_SERVICE_ID', 'serviceId')]
    public function testRunCommandWithEnv(): void
    {
        $fastlyAPIClient = $this->createMock(FastlyAPIClient::class);
        $fastlyAPIClient
            ->expects($this->once())
            ->method('setApiKey')
            ->with('apiToken');

        $fastlyAPIClient
            ->expects($this->once())
            ->method('getCurrentlyActiveVersion')
            ->willReturn(1);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('cloneServiceVersion')
            ->with('serviceId', 1)
            ->willReturn(2);

        $fastlyAPIClient
            ->expects($this->once())
            ->method('deleteSnippet')
            ->with('serviceId', 2, 'test');

        $fastlyAPIClient
            ->expects($this->once())
            ->method('activateServiceVersion')
            ->with('serviceId', 2);

        $command = new FastlySnippetRemoveCommand($fastlyAPIClient);
        $tester = new CommandTester($command);

        $tester->execute(['snippetName' => 'test']);

        static::assertEquals(Command::SUCCESS, $tester->getStatusCode());
    }
}
