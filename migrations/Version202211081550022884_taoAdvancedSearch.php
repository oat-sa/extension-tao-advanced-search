<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\generis\model\data\event\ResourceDeleted;
use oat\generis\model\data\event\ResourceUpdated;
use oat\oatbox\event\EventManager;
use oat\oatbox\event\EventManagerAwareTrait;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Metadata\Listener\ResourceDeletedListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\ResourceUpdatedListener;

final class Version202211081550022884_taoAdvancedSearch extends AbstractMigration
{
    use EventManagerAwareTrait;

    public function getDescription(): string
    {
        return sprintf(
            'Setup listeners for %s (%s) and %s (%s)',
            ResourceUpdated::class,
            ResourceUpdatedListener::class,
            ResourceDeleted::class,
            ResourceDeletedListener::class
        );
    }

    public function up(Schema $schema): void
    {
        $this->getEventManager()->attach(
            ResourceUpdated::class,
            [ResourceUpdatedListener::class, 'listen']
        );
        $this->getEventManager()->attach(
            ResourceDeleted::class,
            [ResourceDeletedListener::class, 'listen']
        );

        $this->getServiceManager()->register(
            EventManager::SERVICE_ID,
            $this->getEventManager()
        );
    }

    public function down(Schema $schema): void
    {
        $this->getEventManager()->detach(
            ResourceUpdated::class,
            [ResourceUpdatedListener::class, 'listen']
        );
        $this->getEventManager()->detach(
            ResourceDeleted::class,
            [ResourceDeletedListener::class, 'listen']
        );

        $this->getServiceManager()->register(
            EventManager::SERVICE_ID,
            $this->getEventManager()
        );
    }
}
