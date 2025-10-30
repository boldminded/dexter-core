## Search References
- https://www.meilisearch.com/docs/reference/api/multi_search#federationoptions
- https://www.algolia.com/doc/libraries/sdk/methods/search/search

Algolia does not have a concept of federation, so the `federation` option is used to apply the same search parameters to multiple queries.

The two most common search parameters have aliases that will work regardless of the search provider chosen.

`index`, `indexName`, and `indexUid` are all synonymous when using Algolia or Meilisearch.

`term`, `query`, and `q` are all synonymous when using Algolia or Meilisearch.

`searchParams` is used for any additional, query parameters. E.g. `facets`, `aroundLatLng`, etc when used in the single index search.
When using multiSearch you will need to refer to the documentation of your chosen search provider for exact parameter names and values
to properly construct the  `queries` array. The index and query normalizers will still be applied even in multiSearch.

(substitute `craft.` for `exp.` if using ExpressionEngine with Coilpack)

```twig
{% set results = craft.dexter.multiSearch({
    federation: {
        limit: 10,
    },
    queries: [
        {
            index: 'demo_collections',
            q: 'van gogh'
        },
        {
            index: 'demo_images',
            q: 'van gogh'
        }
    ]
})
%}

{% if results %}
    <ul>
        {% for result in results %}
            <li>{{ result.title }} {{ result.objectID }}</li>
        {% endfor %}
    </ul>
{% else %}
    <p>No results found.</p>
{% endif %}
```

```twig
{% set results = craft.dexter.search({
    index: 'demo_collections',
    searchParams: {},
}) %}
```

```twig
{% set ids = craft.dexter.search({
    index: 'demo_collections',
    q: 'empire',
    searchParams: {},
    idsOnly: true,
}) %}

{% set entries = craft.entries.section('collection').uid(ids).all() %}
```

In ExpressionEngine

```html
{exp:dexter:search index="demo_collections" term="van gogh"}
    {search_params}
        {
            "limit": 1,
            "offset": 0,
            "filter": [
                "status = 'open'",
                "entry_date >= 1732312020"
            ]
        }
    {/search_params}

    {title}
{/exp:dexter:search}
```
