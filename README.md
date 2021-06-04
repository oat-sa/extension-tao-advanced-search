# TAO _taoAdvancedSearch_ extension

![TAO Logo](https://github.com/oat-sa/taohub-developer-guide/raw/master/resources/tao-logo.png)

![GitHub](https://img.shields.io/github/license/oat-sa/extension-tao-advanced-search.svg)
![GitHub release](https://img.shields.io/github/release/oat-sa/extension-tao-advanced-search.svg)
![GitHub commit activity](https://img.shields.io/github/commit-activity/y/oat-sa/extension-tao-advanced-search.svg)

> Extension required to advanced search integration with TAO platform `oat-sa/extension-tao-advanced-search`

## Installation instructions

### Worker configuration
Event processing related to the indexing isolated within separate taskQueue named `indexation_queue`. 
It must be configured to work though RDS broker according to [this instruction](https://github.com/oat-sa/extension-tao-task-queue/blob/master/README.md)

## Create an Indexer

### 1) Create a new Migration Indexing Task

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

### 2) Populate the indexes

#### To warmup cache

This is necessary to optimize indexation:

```shell
./taoAdvancedSearch/scripts/tools/CacheWarmup.sh --help
```

#### To populate ALL indexes, execute:

```shell script
./taoAdvancedSearch/scripts/tools/IndexPopulator.sh --help
```

#### To populate only resources indexes (Items, tests, etc), execute:

```shell script
./taoAdvancedSearch/scripts/tools/IndexResources.sh --help
```

#### To populate only class metadata indexes, execute:

```shell script
./taoAdvancedSearch/scripts/tools/IndexClassMetatada.sh --help
```

#### To populate only delivery results, execute:

```shell script
./taoAdvancedSearch/scripts/tools/IndexDeliveryResults.sh --help
```
