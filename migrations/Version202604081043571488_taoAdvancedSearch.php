<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\tao\model\resources\relation\service\ResourceRelationServiceProxy;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Resource\Service\TestDeliveryRelationService;
use oat\taoAdvancedSearch\scripts\install\RegisterItemRelationsService;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202604081043571488_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Register test->delivery relation orchestrator service for advanced search';
    }

    public function up(Schema $schema): void
    {
        $this->runAction(new RegisterItemRelationsService());
    }

    public function down(Schema $schema): void
    {
        $resourceRelationService = $this->getServiceManager()->get(ResourceRelationServiceProxy::SERVICE_ID);
        $resourceRelationService->removeService('delivery', TestDeliveryRelationService::class);
        $this->getServiceManager()->register(ResourceRelationServiceProxy::SERVICE_ID, $resourceRelationService);
    }
}
