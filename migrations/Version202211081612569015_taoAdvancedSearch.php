<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\oatbox\event\EventManager;
use oat\oatbox\event\EventManagerAwareTrait;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Metadata\Listener\TestUpdatedListener;
use oat\taoTests\models\event\TestUpdatedEvent;

final class Version202211081612569015_taoAdvancedSearch extends AbstractMigration
{
    // sudo -u www-data php index.php '\oat\tao\scripts\tools\Migrations' -c rollback -v 'oat\taoAdvancedSearch\migrations\Version202211081550022884_taoAdvancedSearch'
    use EventManagerAwareTrait;

    public function getDescription(): string
    {
        return sprintf('Setup listeners for %s (%s)',
            TestUpdatedEvent::class,
            TestUpdatedListener::class
        );
    }

    public function up(Schema $schema): void
    {
        $this->getEventManager()->attach(
            TestUpdatedEvent::class,
            [TestUpdatedListener::class, 'listen']);

        $this->getServiceManager()->register(
            EventManager::SERVICE_ID,
            $this->getEventManager()
        );
    }

    public function down(Schema $schema): void
    {
        $this->getEventManager()->detach(
            TestUpdatedEvent::class,
            [TestUpdatedListener::class, 'listen']
        );

        $this->getServiceManager()->register(
            EventManager::SERVICE_ID,
            $this->getEventManager()
        );
    }
}
