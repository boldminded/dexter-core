<?php

namespace BoldMinded\DexterCore\Service\Search;

use CmsIg\Seal\Schema\Field\AbstractField;
use CmsIg\Seal\Schema\Field\BooleanField;
use CmsIg\Seal\Schema\Field\DateTimeField;
use CmsIg\Seal\Schema\Field\FloatField;
use CmsIg\Seal\Schema\Field\IdentifierField;
use CmsIg\Seal\Schema\Field\IntegerField;
use CmsIg\Seal\Schema\Field\ObjectField;
use CmsIg\Seal\Schema\Field\TextField;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Schema;

class SealSchemaFactory
{
    /**
     * Build a SEAL Schema from Meilisearch settings per index.
     * Each settings array should mimic GET /indexes/:uid/settings shape:
     * - primaryKey (string) (optional, may be fetched outside)
     * - searchableAttributes (string[])
     * - filterableAttributes (string[])
     * - sortableAttributes (string[])
     * - distinctAttribute (string|null)
     * - faceting: { ... } (ignored)
     *
     * @param array<string, array<string,mixed>> $settingsByIndex
     * @param array<string, array<string,string>> $typeHints map: index => [field => 'text'|'int'|'float'|'bool'|'datetime']
     */
    public static function fromMeilisearchSettings(array $settingsByIndex, array $typeHints = []): Schema
    {
        $indexes = [];
        foreach ($settingsByIndex as $indexName => $settings) {
            $searchable = (array)($settings['searchableAttributes'] ?? []);
            $filterable = (array)($settings['filterableAttributes'] ?? []);
            $sortable = (array)($settings['sortableAttributes'] ?? []);
            $distinctAttr = $settings['distinctAttribute'] ?? null;
            $primaryKey = $settings['primaryKey'] ?? 'id';

            $fields = self::buildFields(
                $indexName,
                $searchable,
                $filterable,
                $sortable,
                facet: $filterable, // in Meili, filterable attributes are typically used for faceting as well
                distinctAttr: is_string($distinctAttr) ? $distinctAttr : null,
                identifier: (string)$primaryKey,
                typeHints: $typeHints[$indexName] ?? [],
            );

            $indexes[$indexName] = new Index($indexName, $fields, options: []);
        }

        return new Schema($indexes);
    }

    /**
     * Build a SEAL Schema from Algolia settings per index.
     * Each settings array should mimic settings payload:
     * - searchableAttributes (string[])
     * - attributesForFaceting (string[])
     * - attributeForDistinct (string|null)
     * Note: Algolia sortable fields require virtual replicas; we do not infer sortable here.
     *
     * @param array<string, array<string,mixed>> $settingsByIndex
     * @param array<string, array<string,string>> $typeHints
     */
    public static function fromAlgoliaSettings(array $settingsByIndex, array $typeHints = []): Schema
    {
        $indexes = [];
        foreach ($settingsByIndex as $indexName => $settings) {
            $searchable = (array)($settings['searchableAttributes'] ?? []);
            $facets = (array)($settings['attributesForFaceting'] ?? []);
            $distinctAttr = $settings['attributeForDistinct'] ?? null;

            $fields = self::buildFields(
                $indexName,
                $searchable,
                filterable: $facets,
                sortable: [],
                facet: $facets,
                distinctAttr: is_string($distinctAttr) ? $distinctAttr : null,
                identifier: 'objectID',
                typeHints: $typeHints[$indexName] ?? [],
            );

            $indexes[$indexName] = new Index($indexName, $fields, options: []);
        }

        return new Schema($indexes);
    }

    /**
     * @param string[] $searchable
     * @param string[] $filterable
     * @param string[] $sortable
     * @param string[] $facet
     * @param array<string,string> $typeHints
     * @return array<string, AbstractField>
     */
    private static function buildFields(
        string $indexName,
        array $searchable,
        array $filterable,
        array $sortable,
        array $facet,
        ?string $distinctAttr,
        string $identifier,
        array $typeHints = [],
    ): array {
        $names = array_values(array_unique(array_merge($searchable, $filterable, $sortable, $facet, [$identifier])));
        $fields = [];

        foreach ($names as $name) {
            if ($name === $identifier) {
                $fields[$name] = new IdentifierField($name);
                continue;
            }

            $type = strtolower((string)($typeHints[$name] ?? 'text'));
            $multiple = false; // can be adjusted via typeHints if needed later
            $isSearchable = in_array($name, $searchable, true);
            $isFilterable = in_array($name, $filterable, true);
            $isSortable = in_array($name, $sortable, true);
            $isFacet = in_array($name, $facet, true);
            $isDistinct = ($distinctAttr !== null && $name === $distinctAttr);

            $fields[$name] = match ($type) {
                'int', 'integer' => new IntegerField($name, $multiple, $isSearchable, $isFilterable, $isSortable, $isDistinct, $isFacet),
                'float', 'double', 'number' => new FloatField($name, $multiple, $isSearchable, $isFilterable, $isSortable, $isDistinct, $isFacet),
                'bool', 'boolean' => new BooleanField($name, $multiple, $isSearchable, $isFilterable, $isSortable, $isDistinct, $isFacet),
                'datetime', 'date' => new DateTimeField($name, $multiple, false, $isFilterable, $isSortable, $isDistinct, $isFacet),
                default => new TextField($name, $multiple, $isSearchable, $isFilterable, $isSortable, $isDistinct, $isFacet),
            };
        }

        // Basic geo support if a typical _geo field exists
        if (!isset($fields['_geo']) && !isset($fields['_geoloc'])) {
            // no-op; projects can enrich schema via typeHints or custom loader
        }

        return $fields;
    }
}

