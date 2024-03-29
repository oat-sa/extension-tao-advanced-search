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
 */

declare(strict_types=1);

return [
    'items' => require __DIR__ . '/items.conf.php',
    'tests' => require __DIR__ . '/tests.conf.php',
    'groups' => require __DIR__ . '/groups.conf.php',
    'deliveries' => require __DIR__ . '/deliveries.conf.php',
    'delivery-results' => require __DIR__ . '/delivery-results.conf.php',
    'test-takers' => require __DIR__ . '/test-takers.conf.php',
    'assets' => require __DIR__ . '/assets.conf.php',
    'property-list' => require __DIR__ . '/property-list.conf.php',
];
