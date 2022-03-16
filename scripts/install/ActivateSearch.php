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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA.
 *
 * @author Andrei Shapiro <andrei.shapiro@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\scripts\install;

use oat\oatbox\reporting\Report;
use oat\oatbox\extension\InstallAction;

class ActivateSearch extends InstallAction
{
    public const PARAM_HOST = 'host';

    public function __invoke($params = []): Report
    {
        $path = implode(DIRECTORY_SEPARATOR, [
            __DIR__,
            '..',
            '..',
            '..',
            'vendor',
            'oat-sa',
            'lib-tao-elasticsearch',
            'bin',
            'activateElasticSearch.php',
        ]);

        return Report::createInfo(
            shell_exec(
                sprintf(
                    'php %s . %s',
                    $path,
                    $this->getHost($params)
                )
            )
        );
    }

    private function getHost(array $params): string
    {
        return $params[self::PARAM_HOST] ?? $_ENV['ADVANCED_SEARCH_HOST'] ?? '';
    }
}
