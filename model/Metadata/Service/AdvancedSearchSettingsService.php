<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Metadata\Service;

use oat\generis\model\OntologyRdfs;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\featureFlag\FeatureFlagCheckerInterface;
use oat\tao\model\featureFlag\Service\FeatureFlagPropertiesMapping;
use oat\tao\model\Lists\Business\Contract\ClassMetadataSearcherInterface;
use oat\tao\model\Lists\Business\Domain\ClassMetadataSearchRequest;
use oat\tao\model\Lists\Business\Input\ClassMetadataSearchInput;
use oat\tao\model\search\Contract\SearchSettingsServiceInterface;
use oat\tao\model\search\ResultColumn;
use oat\tao\model\search\SearchSettings;
use oat\tao\model\TaoOntology;

class AdvancedSearchSettingsService implements SearchSettingsServiceInterface
{
    private const OMIT_PROPERTIES = [
        OntologyRdfs::RDFS_LABEL,
        TaoOntology::PROPERTY_TRANSLATION_TYPE,
        TaoOntology::PROPERTY_TRANSLATION_PROGRESS,
        TaoOntology::PROPERTY_TRANSLATION_ORIGINAL_RESOURCE_URI,
    ];

    public const DEFAULT_SORT_COLUMN = 'label.raw';

    private ClassMetadataSearcherInterface $classMetadataSearcher;

    private AdvancedSearchChecker $advancedSearchChecker;

    private SearchSettingsServiceInterface $defaultSearchSettingsService;
    private FeatureFlagCheckerInterface $featureFlagChecker;
    private FeatureFlagPropertiesMapping $featureFlagPropertiesMapping;

    public function __construct(
        ClassMetadataSearcherInterface $classMetadataSearcher,
        SearchSettingsServiceInterface $defaultSearchSettingsService,
        AdvancedSearchChecker $advancedSearchChecker,
        FeatureFlagCheckerInterface $featureFlagChecker,
        FeatureFlagPropertiesMapping $featureFlagPropertiesMapping
    ) {
        $this->classMetadataSearcher = $classMetadataSearcher;
        $this->advancedSearchChecker = $advancedSearchChecker;
        $this->defaultSearchSettingsService = $defaultSearchSettingsService;
        $this->featureFlagChecker = $featureFlagChecker;
        $this->featureFlagPropertiesMapping = $featureFlagPropertiesMapping;
    }

    public function getSettingsByClassMetadataSearchRequest(
        ClassMetadataSearchRequest $classMetadataSearchRequest
    ): SearchSettings {
        if (!$this->advancedSearchChecker->isEnabled()) {
            return $this->defaultSearchSettingsService
                ->getSettingsByClassMetadataSearchRequest($classMetadataSearchRequest);
        }

        $classCollection = $this->classMetadataSearcher->findAll(
            new ClassMetadataSearchInput($classMetadataSearchRequest)
        );

        if ($classMetadataSearchRequest->getStructure() === 'results') {
            return new SearchSettings(
                [
                    new ResultColumn(
                        'label',
                        self::DEFAULT_SORT_COLUMN,
                        __('Label'),
                        'text',
                        null,
                        null,
                        false,
                        true,
                        true
                    ),
                    new ResultColumn(
                        'test_taker',
                        'test_taker.raw',
                        __('Test Taker ID'),
                        'text',
                        null,
                        null,
                        false,
                        true,
                        false
                    ),
                    new ResultColumn(
                        'test_taker_name',
                        'test_taker_name.raw',
                        __('Test Taker Name'),
                        'text',
                        null,
                        null,
                        false,
                        true,
                        true
                    ),
                    new ResultColumn(
                        'delivery_execution_start_time',
                        'delivery_execution_start_time.raw',
                        __('Start Time'),
                        'text',
                        null,
                        null,
                        false,
                        true,
                        true
                    ),
                    new ResultColumn(
                        'delivery',
                        'delivery.raw',
                        __('Delivery Uri'),
                        'text',
                        null,
                        null,
                        false,
                        true,
                        true
                    ),
                ]
            );
        }

        $out = [
            new ResultColumn(
                'label',
                self::DEFAULT_SORT_COLUMN,
                __('Label'),
                'text',
                null,
                null,
                false,
                true,
                true
            ),
            new ResultColumn(
                'location',
                'location.raw',
                __('Location'),
                'text',
                null,
                null,
                false,
                true,
                true
            ),
            new ResultColumn(
                'updated_at',
                'updated_at.raw',
                __('Last modified on'),
                'text',
                null,
                null,
                false,
                true,
                true
            )
        ];

        $propertiesToHide = $this->getPropertiesToHide();

        foreach ($classCollection->getIterator() as $class) {
            foreach ($class->getMetaData()->getIterator() as $metadata) {
                if (in_array($metadata->getPropertyUri(), $propertiesToHide, true)) {
                    continue;
                }

                $out[] = new ResultColumn(
                    (string)$metadata->getPropertyUri(),
                    ((string)$metadata->getSortId()),
                    $metadata->getLabel(),
                    $metadata->getType(),
                    $metadata->getAlias(),
                    $metadata->getClassLabel(),
                    $metadata->isDuplicated(),
                    false,
                    $metadata->isSortable()
                );
            }
        }

        return new SearchSettings($out);
    }

    private function getPropertiesToHide(): array
    {
        $propertiesToHide = self::OMIT_PROPERTIES;

        foreach ($this->featureFlagPropertiesMapping->getAllProperties() as $featureFlag => $properties) {
            if (!$this->featureFlagChecker->isEnabled($featureFlag)) {
                foreach ($properties as $property) {
                    if (!in_array($property, $propertiesToHide, true)) {
                        $propertiesToHide[] = $property;
                    }
                }
            }
        }

        return $propertiesToHide;
    }
}
