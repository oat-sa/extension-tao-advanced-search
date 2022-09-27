<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\oatbox\event\EventManager;
use oat\oatbox\event\EventManagerAwareTrait;
use oat\tao\model\Lists\Business\Event\ListSavedEvent;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Metadata\Service\ListSavedEventListener;

final class Version202209271256261488_taoAdvancedSearch extends AbstractMigration
{
    use EventManagerAwareTrait;

    public function getDescription(): string
    {
        return sprintf(
            'Listen to %s in %s',
            ListSavedEvent::class,
            ListSavedEventListener::class
        );
    }

    public function up(Schema $schema): void
    {
        $this->getEventManager()->attach(
            ListSavedEvent::class,
            [
                ListSavedEventListener::class,
                'listen'
            ]
        );

        $this->getServiceManager()->register(EventManager::SERVICE_ID, $this->getEventManager());
    }

    public function down(Schema $schema): void
    {
        $this->getEventManager()->detach(
            ListSavedEvent::class,
            [
                ListSavedEventListener::class,
                'listen'
            ]
        );

        $this->getServiceManager()->register(EventManager::SERVICE_ID, $this->getEventManager());
    }
}
