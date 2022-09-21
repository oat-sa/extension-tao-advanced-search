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
    'index' => 'delivery-results',
    'body' => [
        'mappings' => [
            'properties' => [
                'label' => [
                    'type' => 'keyword'
                ],
                'updated_at' => [
                    'type' => 'keyword',
                ],
                'location' => [
                    'type' => 'keyword'
                ],
                'delivery' => [
                    'type' => 'keyword'
                ],
                'type' => [
                    'type' => 'keyword',
                    'ignore_above' => 256,
                ],
                'test_taker' => [
                    'type' => 'keyword'
                ],
                'test_taker_name' => [
                    'type' => 'keyword'
                ],
                'delivery_execution' => [
                    'type' => 'keyword'
                ],
                'custom_tag' => [
                    'type' => 'keyword'
                ],
                'context_label' => [
                    'type' => 'keyword'
                ],
                'context_id' => [
                    'type' => 'keyword',
                    'ignore_above' => 256,
                ],
                'resource_link_id' => [
                    'type' => 'keyword',
                    'ignore_above' => 256,
                ],
                'delivery_execution_start_time' => [
                    'type' => 'text'
                ],
                'test_taker_first_name' => [
                    'type' => 'text'
                ],
                'test_taker_first_last_name' => [
                    'type' => 'text'
                ],
            ],
        ],
        'settings' => [
            'index' => [
                'number_of_shards' => '1',
                'number_of_replicas' => '1',
            ],
        ],
    ],
];
