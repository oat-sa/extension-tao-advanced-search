<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\scripts\tools\IndexMigration;

final class Version202307251344000003_taoAdvancedSearch extends AbstractMigration
{
    private const INDEX_UPDATE_BODY = '{"properties": {"test_qti_structure": {"type": "object", "enabled": false}}}';

    public function getDescription(): string
    {
        return sprintf(
            'Migrate index "%s" with "%s"',
            IndexerInterface::TESTS_INDEX,
            self::INDEX_UPDATE_BODY
        );
    }

    public function up(Schema $schema): void
    {
        $this->runAction(
            new IndexMigration(),
            [
                '-i',
                IndexerInterface::TESTS_INDEX,
                '-q',
                self::INDEX_UPDATE_BODY,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
