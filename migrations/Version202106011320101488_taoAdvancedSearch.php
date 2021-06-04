<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\oatbox\reporting\Report;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\scripts\install\RegisterTaskQueueServices;
use oat\taoAdvancedSearch\scripts\uninstall\UnRegisterTaskQueueServices;
use oat\taoTaskQueue\model\Service\QueueAssociationService;
use oat\taoTaskQueue\scripts\tools\BrokerFactory;

final class Version202106011320101488_taoAdvancedSearch extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Adds task queue for indexation events.';
    }

    public function up(Schema $schema): void
    {
        $registrationService = new RegisterTaskQueueServices();
        $this->propagate($registrationService);
        $registrationService->__invoke([]);

        if ( BrokerFactory::BROKER_MEMORY !== $this->getAssocitationService()->detectNeededBrokerType()){
            $this->addReport(Report::createWarning(
                sprintf('New worker must be created to proceed tasks from queue named `%s`',$registrationService->getQueueName())
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $registrationService = new UnRegisterTaskQueueServices();
        $this->propagate($registrationService);
        $registrationService->__invoke([]);
    }

    private function getAssocitationService(): QueueAssociationService
    {
        return $this->getServiceManager()->get(QueueAssociationService::class);
    }
}
