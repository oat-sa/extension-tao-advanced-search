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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

use oat\taoAdvanceSearch\scripts\install\RegisterEvents;
use oat\taoAdvanceSearch\scripts\update\Updater;

$managerRole = 'http://www.tao.lu/Ontologies/generis.rdf#advanceSearchManager';

return [
    'name' => 'taoAdvanceSearch',
    'label' => 'Extension to manage advanced search',
    'description' => 'Extension to manage advanced search integration for all TAO resources',
    'license' => 'GPL-2.0',
    'version' => '1.0.0',
    'author' => 'Open Assessment Technologies SA',
    'requires' => [
        //@TODO Needs to be updated
        'generis' => '>=12.24.0',
        'tao' => '>=44.11.0',
        'taoDelivery' => '>=14.10.1',
        'taoResultServer' => '>=12.2.2',
        'taoTestTaker' => '>=7.6.0',
        'taoLti' => '>=11.11.0',
    ],
    'managementRole' => $managerRole,
    'acl' => [
        ['grant', $managerRole, ['ext' => 'taoAdvanceSearch']],
    ],
    'install' => [
        'php' => [
            RegisterEvents::class,
        ],
        'rdf' => []
    ],
    'uninstall' => [
    ],
    'update' => Updater::class,
    'routes' => [
    ],
    'constants' => [
        'BASE_URL' => ROOT_URL . 'taoAdvanceSearch/',
    ]
];
