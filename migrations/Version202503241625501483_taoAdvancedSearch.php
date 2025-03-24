<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\scripts\tools\RecreateIndex;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202503241625501483_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate tests index';
    }

    public function up(Schema $schema): void
    {
        $this->runAction(new RecreateIndex(), ['--index', 'tests']);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
