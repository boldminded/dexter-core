## TODO
- Add multi-index search support
    - https://github.com/meilisearch/meilisearch-php/blob/3ba7c596eb448eef9cd47d2694c64cc093459710/src/Endpoints/Delegates/HandlesMultiSearch.php#L11
    - https://www.meilisearch.com/docs/reference/api/multi_search#federationoptions
    - https://www.algolia.com/doc/libraries/sdk/methods/search/search

Single Index Search
```json
{
    "index": "entries",
    "q": "modern art",
    "limit": 20,
    "offset": 0,
    "sort": {
        "date": "desc"
    },
    "highlight": {
        "fields": [
            "title",
            "excerpt"
        ],
        "preTag": "<em>",
        "postTag": "</em>"
    },
    "distinct": "uid",
    "facets": [
        {
            "type": "count",
            "field": "category"
        },
        {
            "type": "minmax",
            "field": "price"
        }
    ],
    "filters": [
        {
            "type": "equal",
            "field": "status",
            "value": "published"
        },
        {
            "type": "in",
            "field": "locale",
            "values": [
                "en",
                "fr"
            ]
        },
        {
            "type": "and",
            "conditions": [
                {
                    "type": "gte",
                    "field": "date",
                    "value": "2024-01-01"
                },
                {
                    "type": "or",
                    "conditions": [
                        {
                            "type": "equal",
                            "field": "type",
                            "value": "entry"
                        },
                        {
                            "type": "equal",
                            "field": "type",
                            "value": "file"
                        }
                    ]
                }
            ]
        }
    ]
}
```

Multi Index Search
```json
{
    "query": "modern art",
    "federation": {
        "limit": 20,
        "offset": 0,
        "weights": {
            "entries": 1,
            "files": 0.7
        }
    },
    "queries": [
        {
            "index": "entries",
            "q": "modern art",
            "limit": 10,
            "sort": {
                "date": "desc"
            },
            "filters": [
                {
                    "type": "equal",
                    "field": "status",
                    "value": "published"
                }
            ],
            "highlight": {
                "fields": [
                    "title",
                    "excerpt"
                ]
            },
            "facets": [
                {
                    "type": "count",
                    "field": "category"
                }
            ]
        },
        {
            "index": "files",
            "q": "modern art",
            "limit": 10,
            "filters": [
                {
                    "type": "equal",
                    "field": "status",
                    "value": "published"
                },
                {
                    "type": "in",
                    "field": "mime",
                    "values": [
                        "image/jpeg",
                        "image/png"
                    ]
                }
            ]
        }
    ]
}
```
