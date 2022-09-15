<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\tao\model\search\SearchProxy;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Metadata\Service\AdvancedSearchSettingsService;

final class Version202209141256261488_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return sprintf(
            'Add option %s to %s',
            SearchProxy::OPTION_ADVANCED_SEARCH_CLASS,
            SearchProxy::SERVICE_ID
        );
    }

    public function up(Schema $schema): void
    {
        /** @var SearchProxy $searchProxy */
        $searchProxy = $this->getServiceManager()->get(SearchProxy::SERVICE_ID);
        $searchProxy->setOption(SearchProxy::OPTION_SEARCH_SETTINGS_SERVICE, AdvancedSearchSettingsService::class);

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
