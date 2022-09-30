# TAO _taoAdvancedSearch_ extension

![TAO Logo](https://github.com/oat-sa/taohub-developer-guide/raw/master/resources/tao-logo.png)

![GitHub](https://img.shields.io/github/license/oat-sa/extension-tao-advanced-search.svg)
![GitHub release](https://img.shields.io/github/release/oat-sa/extension-tao-advanced-search.svg)
![GitHub commit activity](https://img.shields.io/github/commit-activity/y/oat-sa/extension-tao-advanced-search.svg)

[![codecov](https://codecov.io/gh/oat-sa/extension-tao-advanced-search/branch/master/graph/badge.svg?token=uPVdj0JrEn)](https://codecov.io/gh/oat-sa/extension-tao-advanced-search)

> Extension required to advanced search integration with TAO platform `oat-sa/extension-tao-advanced-search`

# WARNING

The library `oat-sa/lib-tao-elasticsearch` is **deprecated** and this presence in the [composer.json](composer.json)
is maintained only for backward compatibility purposes with already installed applications. 

Please, do not use this classes anymore! Use only classes from this very extension.

## Requirements

- ElasticSearch 7.10+ installed.
- Have this extension installed in TAO.

## Installation instructions

### Activate advanced search 

#### Activate without credentials needed 

```shell
php index.php 'oat\taoAdvancedSearch\scripts\tools\Activate' --host <host url> --port <host port> [--indexPrefix <optional>]
```

#### Activate with credentials needed 

```shell
php index.php 'oat\taoAdvancedSearch\scripts\tools\Activate' --host <host url> --port <host port> --user <host user> --pass <host pass> [--indexPrefix <optional>]
```

#### Activate for ElasticCloud 

```shell
php index.php 'oat\taoAdvancedSearch\scripts\tools\Activate' --elasticCloudId <cloud id> --elasticCloudApiKeyId <key id> --elasticCloudApiKey <key> [--indexPrefix <optional>]
```

### Create indexes

```shell
php index.php 'oat\taoAdvancedSearch\scripts\tools\IndexCreator'
```

**ATTENTION**: In case the indexes already exist and the command above is returning error, 
you can delete the indexes by running the command bellow:

```shell
php index.php 'oat\taoAdvancedSearch\scripts\tools\IndexDeleter'
```

## Indexation

### Warmup cache

This is necessary to optimize indexation:

```shell
./taoAdvancedSearch/scripts/tools/CacheWarmup.sh --help
```

### To populate ALL indexes, execute:

```shell script
./taoAdvancedSearch/scripts/tools/IndexPopulator.sh --help
```

### To populate only resources indexes (Items, tests, etc), execute:

```shell script
./taoAdvancedSearch/scripts/tools/IndexResources.sh --help
```

### To populate only resources from one class, execute:

```shell script
./taoAdvancedSearch/scripts/tools/IndexClassResources.sh --help
```

### To populate only class metadata indexes, execute:

```shell script
./taoAdvancedSearch/scripts/tools/IndexClassMetadata.sh --help
```

### To populate only delivery results, execute:

```shell script
./taoAdvancedSearch/scripts/tools/IndexDeliveryResults.sh --help
```

## Garbage collection

To clean old documents in the indexes:

````shell
./taoAdvancedSearch/scripts/tools/GarbageCollector.sh --help
````

And to index missing records

```shell
php index.php 'oat\taoAdvancedSearch\scripts\tools\IndexMissingRecords' -h
```

## Statistics

To retrieve information about indexed vs expected data and others, execute:

```shell
php index.php '\oat\taoAdvancedSearch\scripts\tools\IndexSummary'
```

## Environment variables

| Variable                              | Description                                                                                | Example        |
|---------------------------------------|--------------------------------------------------------------------------------------------|----------------|
| FEATURE_FLAG_ADVANCED_SEARCH_DISABLED | In case you do not want to have AdvancedSearch enabled even if this extension is installed | true           |
| ADVANCED_SEARCH_METADATA_BLACK_LIST   | To avoid indexing metadata that is used in the criteria filter                             | URI1,URI2,URI3 |


## How to create custom indexers?

Here we need to specify 3 required classes and create them:

- **Normalizer**: Convert search result support format of AdvancedSearch.
- **Result Searcher**: Execute the search for a paginated index execution.
- **Result Filter Factory**: The filter used to segregate the index within many workers.

```php
<?php
namespace oat\taoAdvancedSearch\model\DeliveryResult\Service;

use oat\taoAdvancedSearch\model\DeliveryResult\Factory\DeliveryResultFilterFactory;
use oat\taoAdvancedSearch\model\DeliveryResult\Normalizer\DeliveryResultNormalizer;
use oat\taoAdvancedSearch\model\Index\Service\AbstractIndexMigrationTask;

class DeliveryResultMigrationTask extends AbstractIndexMigrationTask
{
    protected function getConfig(): array
    {
        return [
            self::OPTION_NORMALIZER => DeliveryResultNormalizer::class,
            self::OPTION_RESULT_SEARCHER => DeliveryResultSearcher::class,
            self::OPTION_RESULT_FILTER_FACTORY => DeliveryResultFilterFactory::class,
        ];
    }
}
``` 
