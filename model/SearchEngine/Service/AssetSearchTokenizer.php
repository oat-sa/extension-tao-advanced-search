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

namespace oat\taoAdvancedSearch\model\SearchEngine\Service;

/**
 * Tokenization aligned with Resource Manager {@see \oat\taoItems\model\media\AssetSearchBuilder}.
 */
class AssetSearchTokenizer
{
    /**
     * @return string[]
     */
    public function tokenize(string $value): array
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [];

        $tokens = array_values(array_filter($parts, static function (string $part): bool {
            return $part !== '';
        }));

        $tokens = array_values(array_unique($tokens));

        return $this->dropTrailingFileExtensionToken($normalized, $tokens);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function enrichDocumentBody(array $body): array
    {
        if (isset($body['attributes']) && is_array($body['attributes'])) {
            $body['attributes'] = $this->enrichAttributes($body['attributes']);
        }

        $body['search_tokens'] = $this->collectDocumentSearchTokens($body);

        return $body;
    }

    /**
     * @param array<int, array<string, mixed>> $attributes
     *
     * @return array<int, array<string, mixed>>
     */
    public function enrichAttributes(array $attributes): array
    {
        foreach ($attributes as $index => $attribute) {
            if (!is_array($attribute)) {
                continue;
            }

            $attributes[$index]['search_tokens'] = $this->tokenize($this->attributeNestedSearchText($attribute));
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return string[]
     */
    public function collectDocumentSearchTokens(array $body): array
    {
        $chunks = [];

        foreach (['label', 'location'] as $field) {
            if (!isset($body[$field])) {
                continue;
            }
            $text = $this->stringify($body[$field]);
            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        if (isset($body['attributes']) && is_array($body['attributes'])) {
            foreach ($body['attributes'] as $attribute) {
                if (!is_array($attribute)) {
                    continue;
                }
                $text = $this->attributeText($attribute);
                if ($text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        if ($chunks === []) {
            return [];
        }

        return $this->tokenize(implode(' ', $chunks));
    }

    /**
     * @param array<string, mixed> $attribute
     */
    private function attributeText(array $attribute): string
    {
        $parts = [];

        if (array_key_exists('value', $attribute)) {
            $parts[] = $this->stringify($attribute['value']);
        }
        if (array_key_exists('raw_value', $attribute)) {
            $parts[] = $this->stringify($attribute['raw_value']);
        }

        return trim(implode(' ', array_filter($parts, static function (string $part): bool {
            return $part !== '';
        })));
    }

    /**
     * Nested {@code attributes.search_tokens} follow human-readable metadata (raw_value).
     *
     * @param array<string, mixed> $attribute
     */
    private function attributeNestedSearchText(array $attribute): string
    {
        if (array_key_exists('raw_value', $attribute)) {
            $raw = $this->stringify($attribute['raw_value']);
            if ($raw !== '') {
                return $raw;
            }
        }

        if (array_key_exists('value', $attribute)) {
            return $this->stringify($attribute['value']);
        }

        return '';
    }

    /**
     * Drop a trailing extension token (e.g. {@code photo.png} → {@code photo}) while keeping
     * in-name tokens ({@code mp3_154.mp3} → {@code mp3}, {@code 154}).
     *
     * @param string[] $tokens
     *
     * @return string[]
     */
    private function dropTrailingFileExtensionToken(string $normalized, array $tokens): array
    {
        if ($tokens === [] || !str_contains($normalized, '.')) {
            return $tokens;
        }

        $extension = pathinfo($normalized, PATHINFO_EXTENSION);
        if ($extension === '') {
            return $tokens;
        }

        $extensionToken = mb_strtolower($extension, 'UTF-8');
        if (preg_match('/^\p{L}+$/u', $extensionToken) !== 1) {
            return $tokens;
        }

        $lastIndex = count($tokens) - 1;
        if ($tokens[$lastIndex] !== $extensionToken) {
            return $tokens;
        }

        array_pop($tokens);

        return array_values($tokens);
    }

    /**
     * @param mixed $value
     */
    private function stringify($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $parts[] = (string)$item;
                }
            }

            return implode(' ', $parts);
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }
}
