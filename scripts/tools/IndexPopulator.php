<?php

declare(strict_types=1);

namespace oat\taoAdvancedSearch\scripts\tools;

use \common_report_Report;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

final class IndexPopulator extends ScriptAction implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;
    use OntologyAwareTrait;

    protected function provideOptions(): array
    {
        return [
            'target' => [
                'prefix' => 't',
                'longPrefix' => 'target',
                'flag' => false,
                'description' => '', //TODO: to be done
                'defaultValue' => ''
            ],
        ];
    }

    protected function provideDescription()
    {
        // TODO: Implement provideDescription() method.
    }

    /**
     * @inheritDoc
     */
    protected function run(): common_report_Report
    {
        return common_report_Report::createInfo('Hello world');
    }
}