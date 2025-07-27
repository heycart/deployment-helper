<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Services;

use HeyCart\Deployment\Helper\EnvironmentHelper;
use HeyCart\Deployment\Helper\ProcessHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class AccountService
{
    public const CORE_STORE_LICENSE_HOST = 'core.store.licenseHost';
    public const CORE_STORE_SHOP_SECRET = 'core.store.shopSecret';

    private HttpClientInterface $client;

    public function __construct(private SystemConfigHelper $systemConfigHelper, private ProcessHelper $processHelper, ?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::createForBaseUri('https://api.heycart.com');
    }

    public function refresh(SymfonyStyle $output, string $heycartVersion, string $licenseDomain): void
    {
        if (str_contains($heycartVersion, 'dev')) {
            $heycartVersion = '___VERSION___';
        }

        $changed = $this->setLicenseDomain($licenseDomain);

        if ($changed) {
            $output->info(\sprintf("Updated license domain to %s\n", $licenseDomain));
        }

        $email = EnvironmentHelper::getVariable('HEYCART_STORE_ACCOUNT_EMAIL', '');
        $password = EnvironmentHelper::getVariable('HEYCART_STORE_ACCOUNT_PASSWORD', '');
        $shopSecret = EnvironmentHelper::getVariable('HEYCART_STORE_SHOP_SECRET', '');

        if ($shopSecret !== '') {
            $changed = $this->setManuallyConfiguredShopSecret($heycartVersion, $licenseDomain, $shopSecret, $output);
        } elseif ($email === '' || $password === '') {
            $output->warning('No store account credentials found, skipping store account login verification and login if needed. Set HEYCART_STORE_ACCOUNT_EMAIL and HEYCART_STORE_ACCOUNT_PASSWORD to refresh the store account on deployment');
        } elseif ($this->refreshShopToken($heycartVersion, $licenseDomain, $email, $password)) {
            $output->info('Refreshed global shop token to communicate to store.heycart.com');
            $changed = true;
        }

        if ($changed) {
            $this->processHelper->console(['cache:pool:invalidate-tags', '-p', 'cache.object', 'system-config']);
        }
    }

    private function setLicenseDomain(string $licenseDomain): bool
    {
        $existingRecord = $this->systemConfigHelper->get(self::CORE_STORE_LICENSE_HOST);

        if ($existingRecord === $licenseDomain) {
            return false;
        }

        $this->systemConfigHelper->set(self::CORE_STORE_LICENSE_HOST, $licenseDomain);

        return true;
    }

    private function refreshShopToken(string $heycartVersion, string $licenseDomain, string $email, string $password): bool
    {
        $secret = $this->systemConfigHelper->get(self::CORE_STORE_SHOP_SECRET);
        if ($secret !== null && $this->isShopSecretStillValid($secret, $heycartVersion, $licenseDomain)) {
            return false;
        }

        $response = $this->client->request('POST', '/swplatform/login', [
            'query' => [
                'heycartVersion' => $heycartVersion,
                'domain' => $licenseDomain,
                'language' => 'en-GB',
            ],
            'json' => [
                'heycartId' => $email,
                'password' => $password,
                'heycartUserId' => bin2hex(random_bytes(16)),
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['shopSecret']) || !\is_string($data['shopSecret'])) {
            throw new \RuntimeException('Got invalid response from HeyCart API: ' . json_encode($data, \JSON_THROW_ON_ERROR));
        }

        $this->systemConfigHelper->set(self::CORE_STORE_SHOP_SECRET, $data['shopSecret']);

        return true;
    }

    private function isShopSecretStillValid(string $secret, string $heycartVersion, string $licenseDomain): bool
    {
        $response = $this->client->request('POST', '/swplatform/pluginupdates', [
            'query' => [
                'heycartVersion' => $heycartVersion,
                'domain' => $licenseDomain,
                'language' => 'en-GB',
            ],
            'json' => [
                'plugins' => [],
            ],
            'headers' => [
                'X-HeyCart-Shop-Secret' => $secret,
            ],
        ]);

        return $response->getStatusCode() === 200;
    }

    private function setManuallyConfiguredShopSecret(string $heycartVersion, string $licenseDomain, string $shopSecret, SymfonyStyle $output): bool
    {
        if (!$this->isShopSecretStillValid($shopSecret, $heycartVersion, $licenseDomain)) {
            $output->warning('Manually given shop secret is invalid, ignoring it');

            return false;
        }

        if ($this->systemConfigHelper->get(self::CORE_STORE_SHOP_SECRET) === $shopSecret) {
            return false;
        }

        $this->systemConfigHelper->set(self::CORE_STORE_SHOP_SECRET, $shopSecret);

        return true;
    }
}
