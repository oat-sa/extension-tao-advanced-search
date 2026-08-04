<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\oatbox\reporting\Report;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\scripts\install\RegisterItemCommentElasticsearchAdapter;
use Throwable;

/**
 * Ensures item-comments Elasticsearch index exists and switches comment persistence to ES.
 *
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202608041200001488_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create item-comments ES index and adjust Item Comment business logic to Elasticsearch adapter (NYSED-19)';
    }

    public function up(Schema $schema): void
    {
        try {
            $script = new RegisterItemCommentElasticsearchAdapter();
            $script->setServiceLocator($this->getServiceLocator());
            $report = $script([]);

            if ($report instanceof Report) {
                $this->addReport($report);
            } else {
                $this->addReport(
                    Report::createSuccess('Item comments persistence switched to Elasticsearch')
                );
            }
        } catch (Throwable $exception) {
            $this->addReport(
                Report::createError(
                    sprintf('Failed adjusting item comments to Elasticsearch: %s', $exception->getMessage())
                )
            );
            throw $exception;
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally left empty: index and ES adapter remain for forward compatibility.
    }
}
