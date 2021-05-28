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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Metadata\Service;

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\TaoOntology;
use oat\tao\model\task\migration\ResultUnit;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\tao\model\task\migration\service\ResultSearcherInterface;
use oat\tao\model\task\migration\ResultUnitCollection;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriRepository;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriRepositoryInterface;

class MetadataResultSearcher extends ConfigurableService implements ResultSearcherInterface
{
    use OntologyAwareTrait;

    public const ROOT_CLASSES = [
        TaoOntology::CLASS_URI_ITEM,
        TaoOntology::CLASS_URI_ASSEMBLED_DELIVERY,
        TaoOntology::CLASS_URI_GROUP,
        TaoOntology::CLASS_URI_TEST,
    ];

    public function search(ResultFilter $filter): ResultUnitCollection
    {
        //FIXME Remove after testing
        \common_Logger::w('MetadataResultSearcher ============> ' . var_export($filter, true));

        $offset = $filter->getParameter('start');
        $limit = $filter->getParameter('end') - $filter->getParameter('start');

        $allClassUris = $this->getClassUriRepository()->findAll();

        $classesBatch = array_slice($allClassUris, $offset, $limit);

        //FIXME Remove after testing
        \common_Logger::w('MetadataResultSearcher BATCH ============> ' . var_export($classesBatch, true));

        $collection = new ResultUnitCollection();

        foreach ($classesBatch as $classUri) {
            $collection->add(new ResultUnit($classUri));
        }

        return $collection;
    }

    private function getClassUriRepository(): ClassUriRepositoryInterface
    {
        return $this->getServiceLocator()->get(ClassUriRepository::class);
    }
}
