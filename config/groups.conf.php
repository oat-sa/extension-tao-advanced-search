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
    'index' => 'groups',
    'body' => [
        'mappings' => [
            'properties' => [
                'class' => [
                    'type' => 'keyword',
                ],
                'label' => [
                    'type' => 'keyword'
                ],
                'type' => [
                    'type' => 'keyword',
                    'ignore_above' => 256,
                ],
                'updated_at' => [
                    'type' => 'keyword',
                ],
                'location' => [
                    'type' => 'keyword'
                ],
            ],
            'dynamic_templates' => require __DIR__ . '/dynamic-templates.conf.php',
        ],
        'settings' => [
            'index' => [
                'number_of_shards' => '1',
                'number_of_replicas' => '1',
            ],
        ],
    ],
];
