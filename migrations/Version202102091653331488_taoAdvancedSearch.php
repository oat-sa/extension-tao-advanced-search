<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\generis\model\data\event\ClassDeletedEvent;
use oat\generis\model\data\event\ClassPropertyCreatedEvent;
use oat\generis\model\data\event\ClassPropertyDeletedEvent;
use oat\oatbox\event\EventManager;
use oat\oatbox\event\EventManagerAwareTrait;
use oat\tao\model\event\ClassPropertiesChangedEvent;
use oat\tao\model\event\ClassPropertyRemovedEvent;
use oat\tao\model\event\DataAccessControlChangedEvent;
use oat\tao\model\listener\ClassPropertiesChangedListener;
use oat\tao\model\listener\ClassPropertyRemovedListener;
use oat\tao\model\listener\DataAccessControlChangedListener;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Metadata\Listener\ClassDeletionListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\MetadataChangedListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\MetadataListener;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version202102091653331488_taoAdvancedSearch extends AbstractMigration
{
    use EventManagerAwareTrait;

    public function getDescription(): string
    {
        return 'Register events';
    }

    public function up(Schema $schema): void
    {
        $this->registerService(MetadataChangedListener::SERVICE_ID, new MetadataChangedListener());
        $this->registerService(MetadataListener::SERVICE_ID, new MetadataListener());
        $this->registerService(ClassDeletionListener::SERVICE_ID, new ClassDeletionListener());

        $this->getEventManager()->attach(
            ClassPropertyDeletedEvent::class,
            [
                MetadataListener::class,
                'listen'
            ]
        );

        $this->getEventManager()->attach(
            ClassPropertyCreatedEvent::class,
            [
                MetadataListener::class,
                'listen'
            ]
        );

        $this->getEventManager()->attach(
            ClassPropertiesChangedEvent::class,
            [
                MetadataChangedListener::class,
                'listen'
            ]
        );

        $this->getEventManager()->attach(
            ClassDeletedEvent::class,
            [
                ClassDeletionListener::class,
                'listen'
            ]
        );

        $this->getServiceManager()->register(EventManager::SERVICE_ID, $this->getEventManager());
    }

    public function down(Schema $schema): void
    {

        $this->getEventManager()->detach(
            ClassPropertiesChangedEvent::class,
            [
                ClassPropertiesChangedListener::SERVICE_ID, 'handleEvent'
            ]
        );

        $this->getEventManager()->detach(
            ClassPropertyRemovedEvent::class,
            [
                ClassPropertyRemovedListener::SERVICE_ID, 'handleEvent'
            ]
        );

        $this->getEventManager()->detach(
            DataAccessControlChangedEvent::class,
            [
                DataAccessControlChangedListener::SERVICE_ID, 'handleEvent'
            ]
        );

        $this->getServiceManager()->register(EventManager::SERVICE_ID, $this->getEventManager());

        $this->getServiceManager()->unregister(MetadataChangedListener::SERVICE_ID);
        $this->getServiceManager()->unregister(MetadataListener::SERVICE_ID);
        $this->getServiceManager()->unregister(ClassDeletionListener::SERVICE_ID);
    }
}
