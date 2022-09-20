<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use oat\generis\model\DependencyInjection\ServiceOptions;
use oat\oatbox\event\EventManagerAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\oatbox\service\exception\InvalidServiceManagerException;
use oat\oatbox\service\ServiceNotFoundException;
use oat\tao\model\search\index\IndexUpdaterInterface;
use oat\tao\model\search\SearchProxy;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\IndexUpdater;

final class Version202209081525261488_taoAdvancedSearch extends AbstractMigration
{
    use EventManagerAwareTrait;

    public function getDescription(): string
    {
        return 'Fix legacy configuration';
    }

    public function up(Schema $schema): void
    {
        /** @var SearchProxy $searchProxy */
        $searchProxy = $this->getServiceManager()->get(SearchProxy::SERVICE_ID);

        /** @var ConfigurableService $oldElasticSearch */
        $oldElasticSearch = $searchProxy->getAdvancedSearch();

        /** @var ServiceOptions $serviceOptions */
        $serviceOptions = $this->getServiceManager()->get(ServiceOptions::SERVICE_ID);

        if ($oldElasticSearch) {
            $serviceOptions->save(ElasticSearchConfig::class, 'hosts', $oldElasticSearch->getOption('hosts'));
        }

        $searchProxy->setOption(SearchProxy::OPTION_ADVANCED_SEARCH_CLASS, ElasticSearch::class);

        $this->registerService(SearchProxy::SERVICE_ID, $searchProxy);
        $this->registerService(ServiceOptions::SERVICE_ID, $serviceOptions);
        $this->registerService(IndexUpdaterInterface::SERVICE_ID, new IndexUpdater());
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration();
    }
}
