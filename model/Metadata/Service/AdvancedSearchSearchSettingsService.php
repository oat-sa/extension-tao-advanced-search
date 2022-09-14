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

class AdvancedSearchSearchSettingsService implements SearchSettingsServiceInterface
{
    /** @var ClassMetadataSearcherInterface */
    private $classMetadataSearcher;

    public function __construct(ClassMetadataSearcherInterface $classMetadataSearcher)
    {
        $this->classMetadataSearcher = $classMetadataSearcher;
    }

    public function getSettingsByClassMetadataSearchRequest(ClassMetadataSearchRequest $classMetadataSearchRequest): SearchSettings
    {
        $classCollection = $this->classMetadataSearcher->findAll(new ClassMetadataSearchInput($classMetadataSearchRequest));

        $out = [
            'location' => new ResultColumn(
                'location',
                __('Location'),
                'text',
            )
        ];

        foreach ($classCollection->getIterator() as $class) {
            foreach ($class->getMetaData()->getIterator() as $metadata) {
                $out[$metadata->getLabel()] =  new ResultColumn(
                    (string)$metadata->getPropertyUri(),
                    $metadata->getLabel(),
                    $metadata->getType()
                );
            }
        }

        krsort($out);

        return new SearchSettings($out);
    }
}
