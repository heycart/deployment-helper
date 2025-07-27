<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Tests\TestUtil;

use HeyCart\Deployment\Services\SystemConfigHelper;

class StaticSystemConfigHelper extends SystemConfigHelper
{
    /**
     * @param array<string, string> $config
     */
    public function __construct(private array $config = [])
    {
    }

    public function get(string $key): ?string
    {
        return $this->config[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->config[$key] = $value;
    }
}
