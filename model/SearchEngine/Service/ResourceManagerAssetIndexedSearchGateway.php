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
use oat\tao\model\accessControl\AccessControlEnablerInterface;
use oat\tao\model\accessControl\PermissionCheckerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Exception\AssetSearchUnavailableException;
use oat\taoItems\model\media\AssetIndexedSearchGatewayInterface;
use oat\taoItems\model\media\AssetSearchQuery;
use oat\taoMediaManager\model\MediaSource;
use Psr\Log\LoggerInterface;
use tao_helpers_Uri;

class ResourceManagerAssetIndexedSearchGateway implements AssetIndexedSearchGatewayInterface
{
    // coderabbit: ignored — php -l clean; private helpers remain inside this class (brace FP)
    private const FETCH_MULTIPLIER = 3;

    /** Hard stop so ACL post-filter cannot walk an unbounded index. */
    private const MAX_SCANNED_HITS = 2000;

    /** @var ElasticSearch */
    private $elasticSearch;

    /** @var ResourceManagerAssetSearchQueryBuilder */
    private $queryBuilder;

    /** @var PermissionCheckerInterface */
    private $permissionChecker;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ElasticSearch $elasticSearch,
        ResourceManagerAssetSearchQueryBuilder $queryBuilder,
        PermissionCheckerInterface $permissionChecker,
        LoggerInterface $logger
    ) {
        $this->elasticSearch = $elasticSearch;
        $this->queryBuilder = $queryBuilder;
        $this->permissionChecker = $permissionChecker;
        $this->logger = $logger;
    }

    public function isAvailable(): bool
    {
        try {
            return $this->elasticSearch->ping();
        } catch (Exception $exception) {
            $this->logger->warning(
                'Asset indexed search ping failed: ' . $exception->getMessage()
            );

            return false;
        }
    }

    public function search(AssetSearchQuery $query): array
    {
        try {
            $scopeLocation = $this->resolveScopeLocation($query);
            $searchBody = $this->queryBuilder->build(
                $query,
                $scopeLocation,
                $query->getMetadataCriteria()
            );

            $pageSize = $query->getPageSize();
            $page = $query->getPage();

            $authorizedItems = [];
            $esTotal = 0;
            $esFrom = 0;
            $scannedHits = 0;
            $scanTruncated = false;
            $batchSize = max($pageSize * self::FETCH_MULTIPLIER, 20);

            while (true) {
                $searchBody['from'] = $esFrom;
                $searchBody['size'] = $batchSize;

                $result = $this->elasticSearch->searchWithBody(IndexerInterface::ASSETS_INDEX, $searchBody);
                $esTotal = max($esTotal, $result->getTotalCount());

                $batchHits = iterator_to_array($result);
                if ($batchHits === []) {
                    break;
                }

                $scannedHits += count($batchHits);

                foreach ($batchHits as $hit) {
                    if (!is_array($hit)) {
                        continue;
                    }

                    $uri = (string)($hit['id'] ?? '');
                    if ($uri === '' || !$this->permissionChecker->hasReadAccess($uri)) {
                        continue;
                    }

                    $mapped = $this->mapHit($hit);
                    if (!$this->matchesMimeFilter($mapped['mime'], $query->getFilter())) {
                        continue;
                    }

                    $authorizedItems[] = $mapped;
                }

                $esFrom += $batchSize;

                if ($esFrom >= $esTotal) {
                    break;
                }

                if ($scannedHits >= self::MAX_SCANNED_HITS) {
                    $scanTruncated = true;
                    $this->logger->warning(sprintf(
                        'Asset indexed search scan truncated after %d hits (index total %d).',
                        $scannedHits,
                        $esTotal
                    ));
                    break;
                }
            }

            $total = count($authorizedItems);
            $maxPage = max(1, (int)ceil($total / $pageSize) ?: 1);
            $normalizedPage = min(max(1, $page), $maxPage);
            $pageItems = array_slice($authorizedItems, ($normalizedPage - 1) * $pageSize, $pageSize);

            return [
                'items' => array_values($pageItems),
                'total' => $total,
                'page' => $normalizedPage,
                'pageSize' => $pageSize,
                'totalIsApproximate' => $scanTruncated,
            ];
        } catch (AssetSearchUnavailableException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new AssetSearchUnavailableException(
                'Asset indexed search failed: ' . $exception->getMessage(),
                (int)$exception->getCode(),
                $exception
            );
        }
    }

    private function resolveScopeLocation(AssetSearchQuery $search): string
    {
        $asset = $search->getAsset();
        $mediaSource = $asset->getMediaSource();

        if ($mediaSource instanceof AccessControlEnablerInterface) {
            $mediaSource->enableAccessControl();
        }

        $scopeQuery = new AssetSearchQuery(
            $asset,
            $search->getItemUri(),
            $search->getItemLang(),
            $search->getFilter(),
            1,
            0,
            0
        );

        $tree = $mediaSource->getDirectories($scopeQuery);
        $scopePath = (string)($tree['path'] ?? $search->getParentLink());
        $scopeLabel = (string)($tree['label'] ?? $scopePath);

        return $scopeLabel !== '' ? $scopeLabel : $scopePath;
    }

    /**
     * @param array<string, mixed> $hit
     * @return array<string, mixed>
     */
    private function mapHit(array $hit): array
    {
        $label = $this->stringifyHitValue($hit['label'] ?? '');
        $uri = (string)($hit['id'] ?? '');
        $mime = trim($this->stringifyHitValue($hit['mime_type'] ?? ''));
        // Do not fall back to indexed `type` (often an ontology URI array).
        if ($mime === '' && $uri !== '') {
            $mime = $this->resolveMimeTypeFromResource($uri);
        }

        return [
            'uri' => $this->toMediaBrowserUri($uri),
            'label' => $label,
            'name' => $label,
            'mime' => $mime,
            'location' => (string)($hit['location'] ?? ''),
            'updatedAt' => $hit['updated_at'] ?? null,
        ];
    }

    /**
     * Browse/download expect MediaSource URIs (taomedia://mediamanager/<encoded>),
     * while the assets index stores the raw RDF resource id.
     */
    private function toMediaBrowserUri(string $resourceUri): string
    {
        if ($resourceUri === '') {
            return $resourceUri;
        }

        if (strpos($resourceUri, MediaSource::SCHEME_NAME) === 0) {
            return $resourceUri;
        }

        // Keep non-HTTP schemes used by fixtures / other media sources as-is.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $resourceUri) && !preg_match('#^https?://#i', $resourceUri)) {
            return $resourceUri;
        }

        return MediaSource::SCHEME_NAME . tao_helpers_Uri::encode($resourceUri);
    }

    /**
     * @param mixed $value
     */
    private function stringifyHitValue($value): string
    {
        if (is_array($value)) {
            $first = reset($value);

            return is_scalar($first) ? (string)$first : '';
        }

        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @param array<int, mixed> $allowedMimes
     */
    private function matchesMimeFilter(string $mime, array $allowedMimes): bool
    {
        $normalizedAllowed = array_values(array_filter($allowedMimes, static function ($value): bool {
            return is_string($value) && $value !== '';
        }));
        if ($normalizedAllowed === []) {
            return true;
        }

        return $mime !== '' && in_array($mime, $normalizedAllowed, true);
    }

    private function resolveMimeTypeFromResource(string $uri): string
    {
        try {
            $resource = new \core_kernel_classes_Resource($uri);
            $value = $resource->getOnePropertyValue(
                new \core_kernel_classes_Property(
                    \oat\taoMediaManager\model\TaoMediaOntology::PROPERTY_MIME_TYPE
                )
            );
            if ($value instanceof \core_kernel_classes_Literal) {
                return trim((string)$value);
            }
        } catch (Exception $exception) {
            $this->logger->warning(
                'Unable to resolve asset mime type for ' . $uri . ': ' . $exception->getMessage()
            );
        }

        return '';
    }
}
