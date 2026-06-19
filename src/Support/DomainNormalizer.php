<?php

namespace Uticms\Platform\Support;

final class DomainNormalizer
{
    public static function normalize(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    public static function fromAppUrl(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return 'localhost';
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'localhost';
        }

        return self::normalize($host);
    }
}
