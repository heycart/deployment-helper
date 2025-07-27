<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Command;

use HeyCart\Deployment\Event\PostDeploy;
use HeyCart\Deployment\Helper\EnvironmentHelper;
use HeyCart\Deployment\Integration\Fastly\FastlyServiceUpdater;
use HeyCart\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fastly:snippet:deploy',
    description: 'Deploy all Fastly snippets'
)]
class FastlySnippetDeployCommand extends Command
{
    public function __construct(private readonly FastlyServiceUpdater $fastlyServiceUpdater)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiToken = EnvironmentHelper::getVariable('FASTLY_API_TOKEN', '');
        $serviceId = EnvironmentHelper::getVariable('FASTLY_SERVICE_ID', '');

        if ($apiToken === '' || $serviceId === '') {
            $output->writeln('FASTLY_API_TOKEN or FASTLY_SERVICE_ID is not set.');

            return self::FAILURE;
        }

        $this->fastlyServiceUpdater->__invoke(new PostDeploy(new RunConfiguration(), $output));

        return self::SUCCESS;
    }
}
