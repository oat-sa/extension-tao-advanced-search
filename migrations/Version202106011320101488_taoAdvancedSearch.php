<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\scripts\install\RegisterTaskQueueServices;
use oat\taoAdvancedSearch\scripts\uninstall\UnRegisterTaskQueueServices;

final class Version202106011320101488_taoAdvancedSearch extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Separate taskQueue for indexation events';
    }

    public function up(Schema $schema): void
    {
        $registrationService = new RegisterTaskQueueServices();
        $this->propagate($registrationService);
        $registrationService->__invoke([]);
    }

    public function down(Schema $schema): void
    {
        $registrationService = new UnRegisterTaskQueueServices();
        $this->propagate($registrationService);
        $registrationService->__invoke([]);
    }
}
