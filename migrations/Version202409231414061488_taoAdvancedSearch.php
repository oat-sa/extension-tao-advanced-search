<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\generis\model\DependencyInjection\ServiceOptions;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202409231414061488_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update ElasticSearchConfig to use properties from environment variables';
    }

    public function up(Schema $schema): void
    {
        $serviceManager = $this->getServiceManager();
        $serviceOptions = $this->getServiceOptions();
        $options = $serviceOptions->getOptions();

        if (
            isset($options[ElasticSearchConfig::class][ElasticSearchConfig::OPTION_ELASTIC_CLOUD_ID]) &&
            isset($options[ElasticSearchConfig::class][ElasticSearchConfig::OPTION_INDEX_PREFIX]) &&
            isset($options[ElasticSearchConfig::class][ElasticSearchConfig::OPTION_ELASTIC_CLOUD_API_KEY_ID]) &&
            isset($options[ElasticSearchConfig::class][ElasticSearchConfig::OPTION_ELASTIC_CLOUD_API_KEY]) &&
            $this->checkExistingEnvVariables()
        ) {
            unset($options[ElasticSearchConfig::class]);
            $serviceOptions->setOptions($options);
            $serviceManager->register(ServiceOptions::SERVICE_ID, $serviceOptions);
        }

        if (
            $serviceOptions->get(ElasticSearchConfig::class, ElasticSearchConfig::OPTION_HOSTS)
            && getenv(ElasticSearchConfig::ENV_OPTION_HOSTS)
        ) {
            unset($options[ElasticSearchConfig::class]);
            $serviceOptions->setOptions($options);
            $serviceManager->register(ServiceOptions::SERVICE_ID, $serviceOptions);
        }
    }

    private function checkExistingEnvVariables(): bool
    {
        return getenv(ElasticSearchConfig::ENV_OPTION_INDEX_PREFIX)
            && getenv(ElasticSearchConfig::ENV_OPTION_ELASTIC_CLOUD_ID)
            && getenv(ElasticSearchConfig::ENV_OPTION_ELASTIC_CLOUD_API_KEY_ID)
            && getenv(ElasticSearchConfig::ENV_OPTION_ELASTIC_CLOUD_API_KEY);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }

    private function getServiceOptions(): ServiceOptions
    {
        return $this->getServiceManager()->getContainer()->get(ServiceOptions::class);
    }
}