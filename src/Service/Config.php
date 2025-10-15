<?php

namespace BoldMinded\DexterCore\Service;

use Adbar\Dot;
use BoldMinded\DexterCore\Contracts\ConfigInterface;

class Config implements ConfigInterface
{
    private array $defaultSettings = [];

    private array $options = [];

    public function __construct(
        ConfigFile $configFile,
        array $userConfig = []
    ) {
        $this->defaultSettings = $configFile->get('settings');
        $this->options = $this->array_merge_recursive_distinct($this->defaultSettings, $userConfig);
    }

    private function array_merge_recursive_distinct(
        array &$array1,
        array &$array2
    ): array
    {
        foreach ($array2 as $key => $value) {
            if (isset($array1[$key])) {
                if (is_array($array1[$key]) && is_array($value)) {
                    // If both arrays are sequential (numerically indexed), append instead of merge by key
                    if (array_is_list($array1[$key]) && array_is_list($value)) {
                        $array1[$key] = array_merge($array1[$key], $value);
                    } else {
                        // Recurse for associative arrays
                        $array1[$key] = $this->array_merge_recursive_distinct($array1[$key], $value);
                    }
                } else {
                    // Overwrite scalar value
                    $array1[$key] = $value;
                }
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }

    public function get(
        string $key,
        string|null $index = null,
        array|null $values = null,
    ): mixed
    {
        $value = (new Dot($this->options))->get($key);

        // Indices are special. We want to prefix each index with the environment name, so we
        // can have a staging_whatever, prod_whatever of the indexes for each environment.
        if (str_contains($key, 'indices.')) {
            $env = $this->get('env');
            $suffix = $this->get('suffix');

            $value = array_map(function ($indexName) use ($env, $suffix) {
                return $env . '_' . $indexName . $suffix;
            }, $value);
        } else if (is_callable($value)) {
            return call_user_func($value, $index, $values);
        }

        return $value;
    }

    public function set(string $name, $value): void
    {
        $this->options[$name] = $value;
    }

    public function getDefault(string $name)
    {
        return (new Dot($this->defaultSettings))->get($name);
    }

    public function getAll(): array
    {
        return $this->options;
    }

    public function setAll(array $options): void
    {
        $this->options = $options;
    }
}
