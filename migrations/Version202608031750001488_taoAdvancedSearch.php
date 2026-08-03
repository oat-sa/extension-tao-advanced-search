<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\oatbox\reporting\Report;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\scripts\install\RegisterItemCommentElasticsearchAdapter;

/**
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202608031750001488_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace Item Comment persistence adapter with Elasticsearch (NYSED-19)';
    }

    public function up(Schema $schema): void
    {
        $script = new RegisterItemCommentElasticsearchAdapter();
        $script->setServiceLocator($this->getServiceLocator());
        $script([]);

        $this->addReport(
            Report::createSuccess('Item Comment persistence adapter switched to Elasticsearch')
        );
    }

    public function down(Schema $schema): void
    {
        // Intentionally left empty.
    }
}
