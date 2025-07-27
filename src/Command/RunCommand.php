<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Command;

use HeyCart\Deployment\Event\PostDeploy;
use HeyCart\Deployment\Helper\EnvironmentHelper;
use HeyCart\Deployment\Services\HookExecutor;
use HeyCart\Deployment\Services\InstallationManager;
use HeyCart\Deployment\Services\HeyCartState;
use HeyCart\Deployment\Services\UpgradeManager;
use HeyCart\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand('run', description: 'Install or Update HeyCart')]
class RunCommand extends Command
{
    public function __construct(
        private readonly HeyCartState $state,
        private readonly InstallationManager $installationManager,
        private readonly UpgradeManager $upgradeManager,
        private readonly HookExecutor $hookExecutor,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('skip-theme-compile', null, InputOption::VALUE_NONE, 'Skip theme compile (Should be used when the theme has been compiled before in the CI/CD)');
        $this->addOption('skip-asset-install', null, InputOption::VALUE_NONE, 'Deprecated - use --skip-assets-install instead');
        $this->addOption('skip-assets-install', null, InputOption::VALUE_NONE, 'Skip asset install (Should be used when the assets have been copied before in the CI/CD)');
        $this->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Set script execution timeout (in seconds). Set to null to disable timeout', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeout = $input->getOption('timeout');

        $config = new RunConfiguration(
            skipThemeCompile: (bool) $input->getOption('skip-theme-compile'),
            skipAssetsInstall: ((bool) $input->getOption('skip-asset-install') || (bool) $input->getOption('skip-assets-install')),
            timeout: (float) (is_numeric($timeout) ? $timeout : EnvironmentHelper::getVariable('HEYCART_DEPLOYMENT_TIMEOUT', '300')),
            forceReinstallation: EnvironmentHelper::getVariable('HEYCART_DEPLOYMENT_FORCE_REINSTALL', '0') === '1',
        );

        $installed = $this->state->isInstalled();

        if ($config->forceReinstallation && $this->state->getPreviousVersion() === 'unknown') {
            $installed = false;
        }

        $this->hookExecutor->execute(HookExecutor::HOOK_PRE);

        if ($installed) {
            $this->upgradeManager->run($config, $output);
        } else {
            $this->installationManager->run($config, $output);
        }

        $this->eventDispatcher->dispatch(new PostDeploy($config, $output));

        $this->hookExecutor->execute(HookExecutor::HOOK_POST);

        return Command::SUCCESS;
    }
}
