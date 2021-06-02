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

namespace oat\taoAdvancedSearch\model\Metadata\Listener;

use Exception;
use oat\generis\model\data\event\ClassDeletedEvent;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Index\Listener\ListenerInterface;

class ClassDeletionListener extends ConfigurableService implements ListenerInterface
{
    public const SERVICE_ID = 'taoAdvancedSearch/ClassDeletionListener';

    /**
     * @throws UnsupportedEventException
     */
    public function listen($event): void
    {
        if (!$event instanceof ClassDeletedEvent) {
            throw new UnsupportedEventException(ClassDeletedEvent::class);
        }

        try {
            $this->getSearchProxy()->remove($event->getClass()->getUri());
        } catch (Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
    }

    private function getSearchProxy(): SearchProxy
    {
        return $this->getServiceLocator()->get(SearchProxy::SERVICE_ID);
    }
}
