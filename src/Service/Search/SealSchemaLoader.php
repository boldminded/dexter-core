<?php

namespace BoldMinded\DexterCore\Service\Search;

use CmsIg\Seal\Schema\Schema;

class SealSchemaLoader
{
    /**
     * Recursively load all JSON files in a directory and build a Meilisearch-based Schema.
     * The index name is derived from the filename (without extension).
     */
    public static function loadMeilisearch(string $directory): Schema
    {
        $settingsByIndex = [];
        $typeHintsByIndex = [];

        foreach (self::jsonFiles($directory) as $file) {
            [$index, $kind] = self::classifyJsonFile($file);
            $json = json_decode((string)file_get_contents($file), true);
            if (!is_array($json)) {
                continue;
            }

            if ($kind === 'types') {
                $typeHintsByIndex[$index] = $json;
            } else {
                $settingsByIndex[$index] = $json;
            }
        }

        return SealSchemaFactory::fromMeilisearchSettings($settingsByIndex, $typeHintsByIndex);
    }

    /**
     * Recursively load all JSON files in a directory and build an Algolia-based Schema.
     * The index name is derived from the filename (without extension).
     */
    public static function loadAlgolia(string $directory): Schema
    {
        $settingsByIndex = [];
        $typeHintsByIndex = [];

        foreach (self::jsonFiles($directory) as $file) {
            [$index, $kind] = self::classifyJsonFile($file);
            $json = json_decode((string)file_get_contents($file), true);
            if (!is_array($json)) {
                continue;
            }

            if ($kind === 'types') {
                $typeHintsByIndex[$index] = $json;
            } else {
                $settingsByIndex[$index] = $json;
            }
        }

        return SealSchemaFactory::fromAlgoliaSettings($settingsByIndex, $typeHintsByIndex);
    }

    /**
     * @return iterable<string>
     */
    private static function jsonFiles(string $directory): iterable
    {
        if (!is_dir($directory)) {
            return [];
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($it as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }
            if ($file->isFile() && strtolower($file->getExtension()) === 'json') {
                yield $file->getPathname();
            }
        }
    }

    /**
     * Derive index name and JSON kind (settings|types) from filename.
     * Recognized conventions:
     *   - "<index>.types.json" => types
     *   - "<index>.settings.json" => settings
     *   - "<index>.json" => settings
     *
     * @return array{0:string,1:string}
     */
    private static function classifyJsonFile(string $path): array
    {
        $name = pathinfo($path, PATHINFO_FILENAME);

        // endsWith helper without PHP 8.3 dependency
        $endsWith = function (string $haystack, string $needle): bool {
            $len = strlen($needle);
            if ($len === 0) { return true; }
            return substr($haystack, -$len) === $needle;
        };

        if ($endsWith($name, '.types')) {
            return [substr($name, 0, -strlen('.types')), 'types'];
        }

        if ($endsWith($name, '.settings')) {
            return [substr($name, 0, -strlen('.settings')), 'settings'];
        }

        return [$name, 'settings'];
    }
}
