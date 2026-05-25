# Nested `attributes` for properties instead of flat fields dynamic mapping per each new metadata property

## Background

Custom metadata was historically indexed as dynamic top-level Elasticsearch fields (`RadioBox_<encoded-uri>`, etc.), which grows the cluster mapping. New indexing stores those values under a single nested field `attributes` with entries `{ key, type, value, raw_value }` while keeping a compatibility query path for documents that still expose the old flat fields.

## Affected indices

Items, tests, deliveries, groups, assets, and test-takers (see `IndexerInterface::INDEXES_USING_NESTED_ATTRIBUTES`).

## Index mapping

Ensure each index definition includes nested `attributes` (already present under `taoAdvancedSearch/config/*.conf.php`). After upgrading code, ensure that [Version202605011800001488_taoAdvancedSearch.php](../migrations/Version202605011800001488_taoAdvancedSearch.php) was applied without errors.
## Normal deployment

1. Stop Queue workers to avoid mixed-state batches during cutover.
2. Run migration that applies nested **`attributes` mapping** on existing indices before indexing nested-only payloads. Doctrine migration **`Version202605011800001488_taoAdvancedSearch`** runs `IndexMigration` for `items`, `tests`, `deliveries`, `groups`, `assets`, and `test-takers` with the same `attributes` block as the extension config (or run the equivalent `PUT` mapping by hand).

## Runtime compatibility

- **Indexing**: `ElasticSearchIndexer` writes dynamic properties into `attributes` for the indexes listed above (unless `FEATURE_FLAG_ADVANCED_SEARCH_DISABLE_NESTED_ATTRIBUTES` is enabled).
- **Search (flag on)**: `QueryBuilder` uses `StructuredResourceSearchQueryBuilder` (structured `bool.must` DSL). Standard fields use term/match clauses; custom metadata uses nested `attributes` plus a temporary flat-field `query_string` compatibility clause (`NestedAttributesQueryService::buildUnreindexedFlatCustomMetadataClause()` — remove when all documents are reindexed).
- **Search (flag off)**: `LegacyResourceQueryConditionsBuilder` emits the same single top-level `query_string` as pre-migration (`master`) code. Unit tests in `legacy_acl` / `legacy_noacl` fixtures assert exact master JSON.

## Operational checks

- `_cat/indices` and `_mapping` should show `attributes` as `nested` and **no new** `RadioBox_*` / `TextBox_*` top-level fields after reindex for custom properties created post-migration.
- Spot-check search using both fixed fields (`label:`, `class:`) and a known custom property `encoded-uri:value` syntax.

## Troubleshooting

### `failed to find nested object under path [attributes]` (HTTP 400)

Elasticsearch only accepts `nested` queries when the mapping defines `attributes` as type `nested`. Old indices created before this change do not have it.

**Proper fix:** recreate or update the affected index from `taoAdvancedSearch/config/<index>.conf.php` (e.g. `tests.conf.php`), then reindex. Until then, advanced search will emit nested clauses that ES rejects.
1. Drop and recreate indexes so nested field `attributes` mapping fixed
2. Trigger a **Re-populate** (./taoAdvancedSearch/scripts/tools/IndexResources.sh) so existing RDF-backed properties are emitted only under `attributes` for new documents.

**Switch to legacy mode (flat fields only for indexing and search):** enable feature flag `FEATURE_FLAG_ADVANCED_SEARCH_DISABLE_NESTED_ATTRIBUTES` (env `FEATURE_FLAG_ADVANCED_SEARCH_DISABLE_NESTED_ATTRIBUTES=true`)
After migrating mappings and reindexing, disable the flag again (default).

### `{"type":"illegal_argument_exception","reason":"Limit of total fields [1000] has been exceeded while adding new fields [2]"}`
Normally after migrating to `attributes` we do not create a new mapping per each new property defined, thus avoiding overflowing the mapping with stale fields,
but, without manual re-indexation, a number of mapped stale properties may already be close to the default limit before code deployment.
In this case re-index using elasticsearch or re-create and re-populate as described above.
