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

use Exception;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\AssetMimeTypeResolverInterface;
use oat\taoMediaManager\model\TaoMediaOntology;
use Psr\Log\LoggerInterface;

/**
 * Ontology-backed MIME lookup with per-request URI cache.
 */
class OntologyAssetMimeTypeResolver implements AssetMimeTypeResolverInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var array<string, string> */
    private $cache = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function resolve(string $uri): string
    {
        if ($uri === '') {
            return '';
        }

        if (array_key_exists($uri, $this->cache)) {
            return $this->cache[$uri];
        }

        $mime = '';
        try {
            $resource = new \core_kernel_classes_Resource($uri);
            $value = $resource->getOnePropertyValue(
                new \core_kernel_classes_Property(TaoMediaOntology::PROPERTY_MIME_TYPE)
            );
            if ($value instanceof \core_kernel_classes_Literal) {
                $mime = trim((string)$value);
            }
        } catch (Exception $exception) {
            $this->logger->warning(
                'Unable to resolve asset mime type for ' . $uri . ': ' . $exception->getMessage()
            );
        }

        $this->cache[$uri] = $mime;

        return $mime;
    }
}
