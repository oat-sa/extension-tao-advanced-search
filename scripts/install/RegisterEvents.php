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
 * Copyright (c) 2021-2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\scripts\install;

use oat\generis\model\data\event\ClassDeletedEvent;
use oat\generis\model\data\event\ClassPropertyCreatedEvent;
use oat\generis\model\data\event\ClassPropertyDeletedEvent;
use oat\generis\model\data\event\ResourceCreated;
use oat\generis\model\data\event\ResourceDeleted;
use oat\generis\model\data\event\ResourceUpdated;
use oat\oatbox\event\EventManager;
use oat\oatbox\extension\InstallAction;
use oat\tao\model\event\ClassMovedEvent;
use oat\tao\model\event\ClassPropertiesChangedEvent;
use oat\taoAdvancedSearch\model\Metadata\Listener\ClassDeletionListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\ClassMovedListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\MetadataChangedListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\MetadataInheritanceListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\MetadataListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\ResourceUpdatedListener;
use oat\taoAdvancedSearch\model\Metadata\Service\ListSavedEventListener;

class RegisterEvents extends InstallAction
{
    public function __invoke($params)
    {
        $eventManager = $this->getServiceManager()->get(EventManager::SERVICE_ID);

        $this->registerService(MetadataChangedListener::SERVICE_ID, new MetadataChangedListener());
        $this->registerService(MetadataListener::SERVICE_ID, new MetadataListener());
        $this->registerService(ClassDeletionListener::SERVICE_ID, new ClassDeletionListener());
        $this->registerService(ClassMovedListener::SERVICE_ID, new ClassMovedListener());

        $eventManager->attach(
            ClassPropertyDeletedEvent::class,
            [
                MetadataListener::class,
                'listen'
            ]
        );

        $eventManager->attach(
            ClassPropertyCreatedEvent::class,
            [
                MetadataListener::class,
                'listen'
            ]
        );

        $eventManager->attach(
            ClassPropertiesChangedEvent::class,
            [
                MetadataChangedListener::class,
                'listen'
            ]
        );

        $eventManager->attach(
            ClassDeletedEvent::class,
            [
                ClassDeletionListener::class,
                'listen'
            ]
        );


        $eventManager->attach(
            ResourceCreated::class,
            [
                MetadataInheritanceListener::class,
                'listen'
            ]
        );

        $eventManager->attach(
            ClassMovedEvent::class,
            [
                ClassMovedListener::class,
                'listen'
            ]
        );

        // @todo This also needs a migration
        $eventManager->attach(
            ResourceUpdated::class,
            [
                ResourceUpdatedListener::class,
                'listen'
            ]
        );
        /*//initially the test is empty
         * / @todo What happens when a test is imported? Do we call create or update?
         * $eventManager->attach(
            ResourceCreated::class,
            [
                ResourceUpdatedListener::class,
                'listen'
            ]
        );*/
        $eventManager->attach(
            ResourceDeleted::class,
            [
                ResourceUpdatedListener::class, // @fixme
                'listen'
            ]
        );

        $eventManager->attach(
            ListSavedEvent::class,
            [
                ListSavedEventListener::class,
                'listen'
            ]
        );

        $this->getServiceManager()->register(EventManager::SERVICE_ID, $eventManager);
    }
}
