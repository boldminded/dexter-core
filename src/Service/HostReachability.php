<?php

namespace BoldMinded\DexterCore\Service;

use BoldMinded\DexterCore\Contracts\ConfigInterface;

/**
 * Whether an AI provider could plausibly fetch a URL from this site.
 *
 * Image description works by handing the provider a URL that it fetches
 * itself, which fails silently-ish on any site the provider cannot reach --
 * local development, staging behind auth, an intranet. Callers use this to
 * decide whether to inline the image instead.
 *
 * Deliberately conservative: a wrongly-inlined image still works, it just
 * costs bytes, while a wrongly-trusted host costs a failed API call and a
 * missing description.
 */
class HostReachability
{
    /**
     * TLDs and suffixes that never resolve publicly.
     *
     * .test, .localhost, .invalid and .example are reserved by RFC 2606 and
     * RFC 6761 for exactly this; .local is mDNS; the rest are conventions
     * common enough in local tooling to be worth catching by default.
     */
    public const DEFAULT_LOCAL_SUFFIXES = [
        '.test',
        '.localhost',
        '.local',
        '.invalid',
        '.example',
        '.ddev',
        '.ddev.site',
        '.lndo.site',
        '.docksal',
        '.wip',
    ];

    public const CONFIG_KEY = 'localHostSuffixes';

    public static function isPublic(string $url, ?ConfigInterface $config = null): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        // A reserved IP is unreachable by definition, whatever it is named.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        foreach (self::suffixes($config) as $suffix) {
            $suffix = strtolower(trim((string) $suffix));

            if ($suffix === '') {
                continue;
            }

            // Match a bare TLD as well as a suffix: an entry of "test" should
            // catch "example.test", not just a host literally ending ".test".
            $suffix = str_starts_with($suffix, '.') ? $suffix : '.' . $suffix;

            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        // A single-label host has no public DNS to resolve against.
        return str_contains($host, '.');
    }

    /**
     * The configured suffixes, appended to the defaults.
     *
     * Appended rather than replacing, so a site adding its own convention
     * does not silently lose the reserved TLDs it was already protected from.
     *
     * @return string[]
     */
    public static function suffixes(?ConfigInterface $config = null): array
    {
        $configured = $config?->get(self::CONFIG_KEY);

        if (! is_array($configured)) {
            return self::DEFAULT_LOCAL_SUFFIXES;
        }

        return array_values(array_unique(array_merge(
            self::DEFAULT_LOCAL_SUFFIXES,
            $configured
        )));
    }
}
