<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\tao\model\Lists\Business\Service\ClassMetadataSearcherProxy;
use oat\tao\model\Lists\Business\Service\ClassMetadataService;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Metadata\Service\ClassMetadataSearcher;

final class Version202101050805132234_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set Class Metadata Search Proxy';
    }

    public function up(Schema $schema): void
    {
        $proxy = $this->getProxy();

        $proxy->setOption(ClassMetadataSearcherProxy::OPTION_ACTIVE_SEARCHER, ClassMetadataSearcher::class);

        $this->getServiceManager()->register(ClassMetadataSearcherProxy::SERVICE_ID, $proxy);
    }

    public function down(Schema $schema): void
    {
        $proxy = $this->getProxy();

        $proxy->setOption(ClassMetadataSearcherProxy::OPTION_ACTIVE_SEARCHER, ClassMetadataService::SERVICE_ID);

        $this->getServiceManager()->register(ClassMetadataSearcherProxy::SERVICE_ID, $proxy);
    }

    private function getProxy(): ClassMetadataSearcherProxy
    {
        return $this->getServiceManager()->get(ClassMetadataSearcherProxy::SERVICE_ID);
    }
}
