<?php

declare(strict_types=1);

namespace HeyCart\Deployment\Services;

class UrlHelper
{
    public static function normalizeSalesChannelUrl(string $url): string
    {
        $path = parse_url($url, \PHP_URL_PATH);

        if ($path === '/') {
            return rtrim($url, '/');
        }

        return $url;
    }
}
