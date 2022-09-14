<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use oat\tao\model\search\SearchProxy;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Metadata\Service\AdvancedSearchSearchSettingsService;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;

final class Version202209141256261488_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add option ' . SearchProxy::OPTION_ADVANCED_SEARCH_CLASS . ' to ' . SearchProxy::SERVICE_ID;
    }

    public function up(Schema $schema): void
    {
        /** @var SearchProxy $searchProxy */
        $searchProxy = $this->getServiceManager()->get(SearchProxy::SERVICE_ID);
        $searchProxy->setOption(SearchProxy::OPTION_SEARCH_SETTINGS_SERVICE, AdvancedSearchSearchSettingsService::class);

        $this->registerService(SearchProxy::SERVICE_ID, $searchProxy);
    }

    public function down(Schema $schema): void
    {
        /** @var SearchProxy $searchProxy */
        $searchProxy = $this->getServiceManager()->get(SearchProxy::SERVICE_ID);
        $searchProxy->setOption(SearchProxy::OPTION_SEARCH_SETTINGS_SERVICE, null);

        $this->registerService(SearchProxy::SERVICE_ID, $searchProxy);
    }
}
