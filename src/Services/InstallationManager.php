<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Services;

use Doctrine\DBAL\Connection;
use HeyCart\Deployment\Config\ProjectConfiguration;
use HeyCart\Deployment\Helper\EnvironmentHelper;
use HeyCart\Deployment\Helper\ProcessHelper;
use HeyCart\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InstallationManager
{
    public function __construct(
        private readonly HeyCartState $state,
        private readonly Connection $connection,
        private readonly ProcessHelper $processHelper,
        private readonly PluginHelper $pluginHelper,
        private readonly AppHelper $appHelper,
        private readonly HookExecutor $hookExecutor,
        private readonly ProjectConfiguration $configuration,
        private readonly AccountService $accountService,
    ) {
    }

    public function run(RunConfiguration $configuration, OutputInterface $output): void
    {
        $this->processHelper->setTimeout($configuration->timeout);

        $output->writeln('HeyCart is not installed, starting installation');

        $this->hookExecutor->execute(HookExecutor::HOOK_PRE_INSTALL);

        $shopLocale = EnvironmentHelper::getVariable('INSTALL_LOCALE', 'zh-CN');
        $shopCurrency = EnvironmentHelper::getVariable('INSTALL_CURRENCY', 'CNY');
        $adminUser = EnvironmentHelper::getVariable('INSTALL_ADMIN_USERNAME', 'admin');
        $adminPassword = EnvironmentHelper::getVariable('INSTALL_ADMIN_PASSWORD', 'heycart123');
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        $salesChannelUrl = UrlHelper::normalizeSalesChannelUrl(EnvironmentHelper::getVariable('SALES_CHANNEL_URL', $appUrl) ?? '');

        $additionalInstallParameters = [];

        if ($configuration->skipThemeCompile) {
            $additionalInstallParameters[] = '--no-assign-theme';
        }

        if ($configuration->skipAssetsInstall) {
            $additionalInstallParameters[] = '--skip-assets-install';
        }

        if ($configuration->forceReinstallation) {
            $additionalInstallParameters[] = '--drop-database';
        }

        $this->processHelper->console(['system:install', '--create-database', '--shop-locale=' . $shopLocale, '--shop-currency=' . $shopCurrency, '--force', ...$additionalInstallParameters]);
        $this->processHelper->console(['user:create', $adminUser, '--password=' . $adminPassword]);

        $this->processHelper->console(['messenger:setup-transports']);

        if ($this->state->isStorefrontInstalled()) {
            $this->removeExistingHeadlessSalesChannel();
            if (!$this->state->isSalesChannelExisting($salesChannelUrl)) {
                $this->processHelper->console(['sales-channel:create:storefront', '--name=Storefront', '--url=' . $salesChannelUrl]);
            }

            $themeChangeParameters = [];
            if ($configuration->skipThemeCompile) {
                $themeChangeParameters[] = '--no-compile';
            }

            $this->processHelper->console(['theme:change', '--all', 'Storefront', ...$themeChangeParameters]);

            if ($configuration->skipThemeCompile) {
                $this->processHelper->console(['theme:dump']);
            }
        }

        $this->state->disableFirstRunWizard();

        $this->processHelper->console(['plugin:refresh']);
        $this->pluginHelper->installPlugins($configuration->skipAssetsInstall);
        $this->pluginHelper->updatePlugins($configuration->skipAssetsInstall);
        $this->pluginHelper->deactivatePlugins($configuration->skipAssetsInstall);
        $this->pluginHelper->removePlugins($configuration->skipAssetsInstall);

        if ($this->configuration->store->licenseDomain !== '') {
            $this->accountService->refresh(new SymfonyStyle(new ArgvInput([]), $output), $this->state->getCurrentVersion(), $this->configuration->store->licenseDomain);
        }

        $this->appHelper->installApps();
        $this->appHelper->updateApps();
        $this->appHelper->deactivateApps();
        $this->appHelper->removeApps();

        $this->state->setVersion($this->state->getCurrentVersion());

        $this->hookExecutor->execute(HookExecutor::HOOK_POST_INSTALL);
    }

    private function removeExistingHeadlessSalesChannel(): void
    {
        $this->connection->executeStatement('DELETE FROM sales_channel WHERE type_id = 0xf183ee5650cf4bdb8a774337575067a6');
    }
}
