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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

return [
    'index' => 'item-comments',
    'body' => [
        'mappings' => [
            'properties' => [
                'id' => [
                    'type' => 'keyword',
                    'ignore_above' => 256,
                ],
                'itemUri' => [
                    'type' => 'keyword',
                    'ignore_above' => 512,
                ],
                'authorId' => [
                    'type' => 'keyword',
                    'ignore_above' => 512,
                ],
                'authorLabel' => [
                    'type' => 'text',
                    'fields' => [
                        'raw' => [
                            'type' => 'keyword',
                            'ignore_above' => 256,
                        ],
                    ],
                ],
                'body' => [
                    'type' => 'text',
                ],
                'createdAt' => [
                    'type' => 'date',
                ],
                'edited' => [
                    'type' => 'boolean',
                ],
                'resolved' => [
                    'type' => 'boolean',
                ],
            ],
        ],
        'settings' => [
            'index' => [
                'number_of_shards' => '1',
                // Single-node local/dev clusters cannot allocate replicas; keep 0 by default.
                'number_of_replicas' => '0',
            ],
        ],
    ],
];
