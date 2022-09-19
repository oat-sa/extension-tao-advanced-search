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

use oat\tao\model\Lists\Business\Contract\ClassMetadataSearcherInterface;
use oat\tao\model\Lists\Business\Domain\ClassMetadataSearchRequest;
use oat\tao\model\Lists\Business\Input\ClassMetadataSearchInput;
use oat\tao\model\search\Contract\SearchSettingsServiceInterface;
use oat\tao\model\search\ResultColumn;
use oat\tao\model\search\SearchSettings;

class AdvancedSearchSettingsService implements SearchSettingsServiceInterface
{
    /** @var ClassMetadataSearcherInterface */
    private $classMetadataSearcher;

    public function __construct(ClassMetadataSearcherInterface $classMetadataSearcher)
    {
        $this->classMetadataSearcher = $classMetadataSearcher;
    }

    public function getSettingsByClassMetadataSearchRequest(
        ClassMetadataSearchRequest $classMetadataSearchRequest
    ): SearchSettings {
        $classCollection = $this->classMetadataSearcher->findAll(new ClassMetadataSearchInput($classMetadataSearchRequest));

        //@TODO FIXME For "results", we need to add other default columns here

        $out = [
            new ResultColumn(
                'label',
                'label',
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
                'location',
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
                'updated_at',
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
                $out[] =  new ResultColumn(
                    (string)$metadata->getPropertyUri(),
                    (string)$metadata->getSortId(),
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
