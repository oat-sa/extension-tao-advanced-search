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
use oat\tao\model\Lists\Business\Contract\ClassMetadataSearcherInterface;
use oat\tao\model\Lists\Business\Domain\ClassMetadataSearchRequest;
use oat\tao\model\Lists\Business\Input\ClassMetadataSearchInput;
use oat\tao\model\search\Contract\SearchSettingsServiceInterface;
use oat\tao\model\search\ResultColumn;
use oat\tao\model\search\SearchSettings;

class AdvancedSearchSettingsService implements SearchSettingsServiceInterface
{
    private const OMIT_PROPERTIES = [
        OntologyRdfs::RDFS_LABEL
    ];

    /** @var ClassMetadataSearcherInterface */
    private $classMetadataSearcher;

    /** @var AdvancedSearchChecker */
    private $advancedSearchChecker;

    /** @var SearchSettingsServiceInterface */
    private $defaultSearchSettingsService;

    public function __construct(
        ClassMetadataSearcherInterface $classMetadataSearcher,
        SearchSettingsServiceInterface $defaultSearchSettingsService,
        AdvancedSearchChecker $advancedSearchChecker
    ) {
        $this->classMetadataSearcher = $classMetadataSearcher;
        $this->advancedSearchChecker = $advancedSearchChecker;
        $this->defaultSearchSettingsService = $defaultSearchSettingsService;
    }

    public function getSettingsByClassMetadataSearchRequest(
        ClassMetadataSearchRequest $classMetadataSearchRequest
    ): SearchSettings {
        if (!$this->advancedSearchChecker->isEnabled()) {
            return $this->defaultSearchSettingsService
                ->getSettingsByClassMetadataSearchRequest($classMetadataSearchRequest);
        }

        $classCollection = $this->classMetadataSearcher->findAll(new ClassMetadataSearchInput($classMetadataSearchRequest));

        if ($classMetadataSearchRequest->getStructure() === 'results') {
            return new SearchSettings(
                [
                    new ResultColumn(
                        'label',
                        'label.raw',
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
                'label.raw',
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

        foreach ($classCollection->getIterator() as $class) {
            foreach ($class->getMetaData()->getIterator() as $metadata) {
                if (in_array($metadata->getPropertyUri(), self::OMIT_PROPERTIES, true)) {
                    continue;
                }

                $out[] = new ResultColumn(
                    (string)$metadata->getPropertyUri(),
                    ((string)$metadata->getSortId()) . '.raw',
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
}
