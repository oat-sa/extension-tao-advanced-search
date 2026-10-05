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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA.
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Service;

use oat\taoAdvancedSearch\model\SearchEngine\Service\AssetSearchTokenizer;
use PHPUnit\Framework\TestCase;

class AssetSearchTokenizerTest extends TestCase
{
    /** @var AssetSearchTokenizer */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new AssetSearchTokenizer();
    }

    public function testTokenizeSplitsOnNonAlphanumeric(): void
    {
        $this->assertSame(['mp3', '154'], $this->subject->tokenize('MP3_154.mp3'));
    }

    public function testEnrichDocumentBodyAddsSearchTokensAndNestedAttributeTokens(): void
    {
        $body = $this->subject->enrichDocumentBody([
            'label' => ['Clip_154.mp3'],
            'location' => ['Assets/Audio'],
            'attributes' => [
                [
                    'key' => 'prop',
                    'value' => ['stored'],
                    'raw_value' => 'Science Grade',
                ],
            ],
        ]);

        $this->assertContains('clip', $body['search_tokens']);
        $this->assertContains('154', $body['search_tokens']);
        $this->assertContains('audio', $body['search_tokens']);
        $this->assertContains('science', $body['search_tokens']);
        $this->assertSame(['science', 'grade'], $body['attributes'][0]['search_tokens']);
    }

    public function testEnrichDocumentBodyKeepsAttributeTokensWhenLabelHasFileExtension(): void
    {
        $body = $this->subject->enrichDocumentBody([
            'label' => 'Clip_154.mp3',
            'attributes' => [
                [
                    'raw_value' => 'Grade 2.0',
                ],
            ],
        ]);

        $this->assertContains('2', $body['search_tokens']);
        $this->assertContains('0', $body['search_tokens']);
        $this->assertSame(['grade', '2', '0'], $body['attributes'][0]['search_tokens']);
    }

    public function testEnrichAttributesRetainsDottedSuffixTokens(): void
    {
        $attributes = $this->subject->enrichAttributes([
            [
                'raw_value' => 'notes.txt',
            ],
        ]);

        $this->assertSame(['notes', 'txt'], $attributes[0]['search_tokens']);
    }
}
