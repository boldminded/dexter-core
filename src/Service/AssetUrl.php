<?php

namespace BoldMinded\DexterCore\Service;

use BoldMinded\DexterCore\Contracts\ConfigInterface;

/**
 * Shared vocabulary for how asset and file fields are indexed.
 *
 * Each CMS resolves a URL its own way -- EE through its File model, Craft
 * through a volume, Statamic through an asset container -- but the choice of
 * *which* form to index is the same question everywhere, so the config key and
 * its allowed values live here rather than being reinvented per build.
 *
 *   path      the stored filename or path, as authored
 *   url       site-relative, e.g. /assets/bg-1.jpg
 *   absolute  includes scheme and domain
 *
 * Defaults to absolute, matching the behaviour every build shipped before this
 * setting existed.
 */
class AssetUrl
{
    public const PATH = 'path';
    public const URL = 'url';
    public const ABSOLUTE = 'absolute';

    public const CONFIG_KEY = 'assetUrls';

    public const DEFAULT = self::ABSOLUTE;

    /**
     * The configured format, falling back to the default when unset or when
     * the configured value is not one this version understands.
     */
    public static function format(ConfigInterface $config): string
    {
        $value = $config->get(self::CONFIG_KEY);

        return self::isValid($value) ? $value : self::DEFAULT;
    }

    public static function isValid(mixed $value): bool
    {
        return in_array($value, self::all(), true);
    }

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::PATH, self::URL, self::ABSOLUTE];
    }

    /**
     * Pick the right value for the configured format.
     *
     * Callers pass what their CMS can produce; whichever is chosen, an empty
     * result falls back to the stored path so a missing or moved file still
     * leaves something searchable rather than a hole in the document.
     */
    public static function pick(
        string $format,
        string $path,
        ?string $url = null,
        ?string $absoluteUrl = null,
    ): string {
        $chosen = match ($format) {
            self::ABSOLUTE => $absoluteUrl ?: $url,
            self::URL => $url,
            default => null,
        };

        return $chosen !== null && $chosen !== '' ? $chosen : $path;
    }
}
