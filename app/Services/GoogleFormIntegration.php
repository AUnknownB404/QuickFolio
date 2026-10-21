<?php

namespace App\Services;

class GoogleFormIntegration
{
    public static function isValidUrl(?string $url): bool
    {
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === 'docs.google.com'
            && preg_match('#^/forms/(?:d/e|d)/[A-Za-z0-9_-]+/viewform/?$#', $parts['path'] ?? '') === 1;
    }

    public static function responseUrl(?string $url): ?string
    {
        $formUrl = self::formUrl($url);
        if ($formUrl === null) {
            return null;
        }

        $path = parse_url($formUrl, PHP_URL_PATH);

        return 'https://docs.google.com' . preg_replace('#/viewform/?$#', '/formResponse', $path);
    }

    public static function formUrl(?string $url): ?string
    {
        if (!self::isValidUrl($url)) {
            return null;
        }

        return 'https://docs.google.com' . parse_url($url, PHP_URL_PATH);
    }
}
