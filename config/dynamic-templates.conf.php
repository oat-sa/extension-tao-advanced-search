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
    [
        'Calendar' => [
            'match' => 'Calendar_*',
            'match_mapping_type' => 'string',
            'mapping' => [
                'type' => 'keyword'
            ]
        ]
    ],
    [
        'HTMLArea' => [
            'match' => 'HTMLArea_*',
            'match_mapping_type' => 'string',
            'mapping' => [
                'type' => 'keyword'
            ]
        ]
    ],
    [
        'TextArea' => [
            'match' => 'TextArea_*',
            'match_mapping_type' => 'string',
            'mapping' => [
                'type' => 'keyword'
            ]
        ]
    ],
    [
        'TextBox' => [
            'match' => 'TextBox_*',
            'match_mapping_type' => 'string',
            'mapping' => [
                'type' => 'keyword'
            ]
        ]
    ],
    [
        'SearchTextBox' => [
            'match' => 'SearchTextBox_*',
            'match_mapping_type' => 'string',
            'mapping' => [
                'type' => 'keyword'
            ]
        ]
    ],
    [
        'SearchDropdown' => [
            'match' => 'SearchDropdown_*',
            'match_mapping_type' => 'string',
            'mapping' => [
                'type' => 'keyword'
            ]
        ]
    ],
    [
        'CheckBox' => [
            'match' => 'CheckBox_*',
            'match_mapping_type' => 'string',
            'mapping' => [
                'type' => 'keyword'
            ]
        ]
    ],
    [
        'ComboBox' => [
            'match' => 'ComboBox_*',
            'match_mapping_type' => 'string',
            'mapping' => [
                'type' => 'keyword'
            ]
        ]
    ],
    [
        'RadioBox' => [
            'match' => 'RadioBox_*',
            'match_mapping_type' => 'string',
            'mapping' => [
                'type' => 'keyword'
            ]
        ]
    ]
];
