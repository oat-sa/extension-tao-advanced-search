<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\generis\model\data\event\ResourceUpdated;
use oat\oatbox\event\EventManager;
use oat\oatbox\event\EventManagerAwareTrait;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Index\Listener\AgnosticEventListener;
use oat\taoTests\models\event\TestUpdatedEvent;

final class Version202211161003288041_taoAdvancedSearch extends AbstractMigration
{
    use EventManagerAwareTrait;

    private const CALLBACK = [AgnosticEventListener::class, 'listen'];
    private const HANDLED_EVENTS = [
        ResourceUpdated::class,
        TestUpdatedEvent::class,
        QtiTestImportEvent::class,
    ];

    public function getDescription(): string
    {
        return sprintf('Setup %s as the listener for %s',
            AgnosticEventListener::class,
            implode(', ', self::HANDLED_EVENTS)
        );
    }

    public function up(Schema $schema): void
    {
        $manager = $this->getEventManager();

        foreach (self::HANDLED_EVENTS as $event) {
            $manager->attach($event, self::CALLBACK);
        }

        $this->updateEventManagerConfig($manager);
    }

    public function down(Schema $schema): void
    {
        $manager = $this->getEventManager();

        foreach (self::HANDLED_EVENTS as $event) {
            $manager->attach($event, self::CALLBACK);
        }

        $this->updateEventManagerConfig($manager);
    }

    private function updateEventManagerConfig(EventManager $manager): void
    {
        $this->getServiceManager()->register(
            EventManager::SERVICE_ID,
            $manager
        );
    }
}
