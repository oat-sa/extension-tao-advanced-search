<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use Exception;
use oat\oatbox\reporting\Report;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Index\Service\RecreatingIndexService;
use oat\taoAdvancedSearch\model\Resource\Service\IndiciesConfigurationService;
use oat\taoAdvancedSearch\scripts\tools\RecreateIndex;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202503241625501483_taoAdvancedSearch extends AbstractMigration
{
    private const INDEX_CONFIG_FILE = __DIR__
    . DIRECTORY_SEPARATOR . '..'
    . DIRECTORY_SEPARATOR . 'config'
    . DIRECTORY_SEPARATOR . 'index.conf.php';

    public function getDescription(): string
    {
        return 'Recreate tests index';
    }

    public function up(Schema $schema): void
    {
        if (!is_readable(self::INDEX_CONFIG_FILE)) {
            $this->addReport(Report::createWarning('Index config not found'));
            return;
        }

        $indexes = require self::INDEX_CONFIG_FILE;

        try {
            $configuration = $this->getServiceManager()
                ->getContainer()
                ->get(IndiciesConfigurationService::class)
                ->checkIndexConfiguration('tests')->asArray();
        } catch (Exception $exception) {
            $this->addReport(Report::createError($exception->getMessage()));
            return;
        }

        if (!$this->areConfigurationsEqual($configuration, $indexes)) {
            $this->runAction(new RecreateIndex(), ['--index', 'tests']);
            return;
        }

        $this->addReport(Report::createInfo('Index test is already up to date'));
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }

    private function areConfigurationsEqual(array $configration, array $indexes): bool
    {
        if (isset(
            $indexes['tests']['body']['mappings']['properties'],
            $configration['tests']['mappings']['properties'])
        ) {
            return empty(array_diff_assoc(
                $indexes['tests']['body']['mappings']['properties'],
                $configration['tests']['mappings']['properties']
            ));
        }
        return false;
    }
}
