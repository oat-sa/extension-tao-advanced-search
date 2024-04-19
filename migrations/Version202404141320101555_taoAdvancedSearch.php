<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use oat\oatbox\reporting\Report;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\scripts\install\RegisterTaskQueueServices;
use oat\taoTaskQueue\model\Service\QueueAssociationService;
use oat\taoTaskQueue\scripts\tools\BrokerFactory;

final class Version202404141320101555_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Register missing queue associations.';
    }

    public function up(Schema $schema): void
    {
        $registrationService = new RegisterTaskQueueServices();
        $this->propagate($registrationService);
        $registrationService->__invoke([]);

        if (BrokerFactory::BROKER_MEMORY !== $this->getAssocitationService()->guessDefaultBrokerType()) {
            $this->addReport(
                Report::createWarning(
                    sprintf(
                        'New worker must be created to proceed tasks from queue named `%s`',
                        $registrationService->getQueueName()
                    )
                )
            );
        }
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration(__CLASS__ . ' cannot be reversed');
    }

    private function getAssocitationService(): QueueAssociationService
    {
        return $this->getServiceManager()->get(QueueAssociationService::class);
    }
}
