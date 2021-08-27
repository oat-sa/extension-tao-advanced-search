<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\oatbox\event\EventManager;
use oat\oatbox\event\EventManagerAwareTrait;
use oat\tao\model\event\ClassMovedEvent;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Metadata\Listener\ClassMovedListener;

final class Version202108120848511488_taoAdvancedSearch extends AbstractMigration
{
    use EventManagerAwareTrait;

    public function getDescription(): string
    {
        return 'Register events related to classes movements';
    }

    public function up(Schema $schema): void
    {
        $this->registerService(ClassMovedListener::SERVICE_ID, new ClassMovedListener());

        $this->getEventManager()->attach(
            ClassMovedEvent::class,
            [
                ClassMovedListener::class,
                'listen'
            ]
        );

        $this->registerService(EventManager::SERVICE_ID, $this->getEventManager());
    }

    public function down(Schema $schema): void
    {
        $this->getEventManager()->detach(
            ClassMovedEvent::class,
            [
                ClassMovedListener::SERVICE_ID,
                'listen'
            ]
        );

        $this->registerService(EventManager::SERVICE_ID, $this->getEventManager());

        $this->getServiceManager()->unregister(ClassMovedListener::SERVICE_ID);
    }
}
