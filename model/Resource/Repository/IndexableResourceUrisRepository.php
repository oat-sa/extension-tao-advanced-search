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

namespace oat\taoAdvancedSearch\model\Resource\Repository;

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\Resource\Cache\CacheIndexableResourceUrisService;

class IndexableResourceUrisRepository extends ConfigurableService
{
    use OntologyAwareTrait;

    public function findAll(int $offset, int $limit): array
    {
        $handle = $this->getCacheIndexableResourceUrisService()->getStream();

        var_export($handle); exit('dsadsadasads');//FIXME

        fseek($handle, $offset);

        $output = [];
        $total = 0;

        while (!feof($handle) || $total >= $limit) {
            $classUri = fgets($handle);

            $output[] = $classUri;

            $total++;
        }

        fclose($handle);

        return $output;
    }

    public function getTotal(): int
    {
        return $this->getCacheIndexableResourceUrisService()->getTotal();
    }

    private function getCacheIndexableResourceUrisService(): CacheIndexableResourceUrisService
    {
        return $this->getServiceLocator()->get(CacheIndexableResourceUrisService::class);
    }
}
