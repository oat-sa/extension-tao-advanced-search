<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\generis\model\data\event\ResourceCreated;
use oat\oatbox\event\EventManager;
use oat\oatbox\event\EventManagerAwareTrait;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Metadata\Listener\MetadataInheritanceListener;

final class Version202107021653331488_taoAdvancedSearch extends AbstractMigration
{
    use EventManagerAwareTrait;

    public function getDescription(): string
    {
        return 'Register events';
    }

    public function up(Schema $schema): void
    {
        $this->registerService(MetadataInheritanceListener::SERVICE_ID, new MetadataInheritanceListener());

        $this->getEventManager()->attach(
            ResourceCreated::class,
            [
                MetadataInheritanceListener::class,
                'listen'
            ]
        );

        $this->getServiceManager()->register(EventManager::SERVICE_ID, $this->getEventManager());
    }

    public function down(Schema $schema): void
    {
        $this->getEventManager()->detach(
            ResourceCreated::class,
            [
                MetadataInheritanceListener::SERVICE_ID,
                'listen'
            ]
        );

        $this->getServiceManager()->register(EventManager::SERVICE_ID, $this->getEventManager());

        $this->getServiceManager()->unregister(MetadataInheritanceListener::SERVICE_ID);
    }
}
