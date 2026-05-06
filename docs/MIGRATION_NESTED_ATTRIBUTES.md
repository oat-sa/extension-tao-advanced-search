# Nested `attributes` for properties instead of flat fields dynamic mapping per each new metadata property

## Background

Custom metadata was historically indexed as dynamic top-level Elasticsearch fields (`RadioBox_<encoded-uri>`, etc.), which grows the cluster mapping. New indexing stores those values under a single nested field `attributes` with entries `{ key, type, value, raw_value }` while keeping a compatibility query path for documents that still expose the old flat fields.

## Affected indices

Items, tests, deliveries, groups, assets, and test-takers (see `ElasticSearchIndexer::INDEXES_USING_NESTED_ATTRIBUTES`).

## Index mapping

Ensure each index definition includes nested `attributes` (already present under `taoAdvancedSearch/config/*.conf.php`). After upgrading code, ensure that [Version202605011800001488_taoAdvancedSearch.php](../migrations/Version202605011800001488_taoAdvancedSearch.php) applied without errors

## Normal deployment

1. Stop Queue workers to avoid mixed-state batches during cutover.
2. Run migration that applies nested **`attributes` mapping** on existing indices before indexing nested-only payloads. Doctrine migration **`Version202605011800001488_taoAdvancedSearch`** runs `IndexMigration` for `items`, `tests`, `deliveries`, `groups`, `assets`, and `test-takers` with the same `attributes` block as the extension config (or run the equivalent `PUT` mapping by hand).

## Runtime compatibility

- **Indexing**: `ElasticSearchIndexer` writes dynamic properties into `attributes` for the indexes listed above; legacy **flat** fields are no longer added for those documents.
- **Search**: `QueryBuilder` keeps the same query string syntax; each custom-field predicate is executed as a `bool.should` between the legacy `query_string` on **flat** fields and a **nested** query on `attributes` (keyword match on `attributes.key`, `attributes.type`, and `attributes.value.raw`), so **old and new** documents **remain searchable**.

## Operational checks

- `_cat/indices` and `_mapping` should show `attributes` as `nested` and **no new** `RadioBox_*` / `TextBox_*` top-level fields after reindex for custom properties created post-migration.
- Spot-check search using both fixed fields (`label:`, `class:`) and a known custom property `encoded-uri:value` syntax.

## Troubleshooting

### `failed to find nested object under path [attributes]` (HTTP 400)

Elasticsearch only accepts `nested` queries when the mapping defines `attributes` as type `nested`. Old indices created before this change do not have it.

**Proper fix:** recreate or update the affected index from `taoAdvancedSearch/config/<index>.conf.php` (e.g. `tests.conf.php`), then reindex. Until then, advanced search will emit nested clauses that ES rejects.
1. Drop and recreate indexes so nested field `attributes` mapping fixed
2. Trigger a **Re-populate** (./taoAdvancedSearch/scripts/tools/IndexResources.sh) so existing RDF-backed properties are emitted only under `attributes` for new documents.

**Temporary workaround (legacy flat fields only):** set environment variable `ELASTICSEARCH_USE_NESTED_ATTRIBUTES_QUERY=false` (or configure `use_nested_attributes_query` to `false` in Elasticsearch service options). Search then uses only the historic `query_string` on flat widget fields; documents indexed **only** under nested `attributes` may not match until indices are migrated.
After migrating mappings and reindexing, remove the override or set it to `true` again (default).

### `{"type":"illegal_argument_exception","reason":"Limit of total fields [1000] has been exceeded while adding new fields [2]"}`
Normally after migrating to `attributes` we do not create a new mapping per each new property defined, so not overflowing mapping with stale fields, 
but, without manual re-indexation a number of mapped stale properties may already been close to default limit before code deployment.
In this case re-index using elasticsearch or re-create and re-populate as described above.
