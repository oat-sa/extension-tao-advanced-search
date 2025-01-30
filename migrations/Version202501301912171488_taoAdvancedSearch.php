<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\tao\model\resources\relation\service\ResourceRelationServiceProxy;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Resource\Service\ItemRelationsService;
use oat\taoAdvancedSearch\scripts\install\RegisterItemRelationsService;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202501301912171488_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'This will register Item Relation Service if the search proxy supports custom index';
    }

    public function up(Schema $schema): void
    {
        $this->runAction(new RegisterItemRelationsService());
    }

    public function down(Schema $schema): void
    {
        $resourceRelationService = $this->getServiceManager()->get(ResourceRelationServiceProxy::SERVICE_ID);
        $services = $resourceRelationService->getOption(ResourceRelationServiceProxy::OPTION_SERVICES);

        if (isset($services['test']) && is_array($services['test'])) {
            $services['test'] = array_filter($services['test'], function ($service) {
                return $service !== ItemRelationsService::class;
            });
        }

        $resourceRelationService->setOption(ResourceRelationServiceProxy::OPTION_SERVICES, $services);
        $this->getServiceManager()->register(
            ResourceRelationServiceProxy::SERVICE_ID, $resourceRelationService
        );
    }
}
