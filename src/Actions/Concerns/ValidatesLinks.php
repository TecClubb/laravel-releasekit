<?php

namespace TecClub\ReleaseKit\Actions\Concerns;

use TecClub\ReleaseKit\Exceptions\InvalidLink;

trait ValidatesLinks
{
    /**
     * Schemes that would run code when a website renders the link.
     */
    private const BLOCKED_SCHEMES = ['javascript', 'data', 'vbscript', 'file'];

    /**
     * Accepts http(s) and app-store schemes such as market:// or itms-apps://.
     */
    protected function assertUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (strlen($url) > 2048
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || in_array($scheme, self::BLOCKED_SCHEMES, true)) {
            throw InvalidLink::url($url);
        }
    }
}
