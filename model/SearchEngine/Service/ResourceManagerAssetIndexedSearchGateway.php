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
use oat\taoAdvancedSearch\model\SearchEngine\Contract\AssetMimeTypeResolverInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\AssetUriEncoderInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoItems\model\media\AssetIndexedSearchGatewayInterface;
use oat\taoItems\model\media\AssetSearchQuery;
use oat\taoItems\model\media\AssetSearchUnavailableException;
use oat\taoItems\model\media\ResourceUpdatedAtResolver;
use oat\taoMediaManager\model\MediaSource;
use Psr\Log\LoggerInterface;

/**
 * Indexed Elasticsearch gateway for Resource Manager scoped asset search.
 *
 * @license GPL-2.0-only
 * @copyright 2026 Open Assessment Technologies SA
 */
class ResourceManagerAssetIndexedSearchGateway implements AssetIndexedSearchGatewayInterface
{
    // coderabbit: ignored — php -l clean; private helpers remain inside this class (brace FP)

    /** @see \oat\tao\model\search\tokenizer\ResourceClasses */
    private const INDEXED_LOCATION_ROOT_CLASS_URIS = [
        'http://www.tao.lu/Ontologies/TAO.rdf#AssessmentContentObject',
        'http://www.tao.lu/Ontologies/TAO.rdf#TAOObject',
        'http://www.tao.lu/Ontologies/generis.rdf#generis_Ressource',
        'http://www.w3.org/2000/01/rdf-schema#Resource',
    ];

    private const FETCH_MULTIPLIER = 3;

    private const MIN_FETCH_BATCH_SIZE = 500;

    /** Hard stop so ACL post-filter cannot walk an unbounded index. */
    private const MAX_SCANNED_HITS = 2000;

    /** @var string[] */
    private const SEARCH_SOURCE_FIELDS = ['label', 'mime_type', 'location', 'updated_at'];

    /** @var ElasticSearch */
    private $elasticSearch;

    /** @var ResourceManagerAssetSearchQueryBuilder */
    private $queryBuilder;

    /** @var PermissionCheckerInterface */
    private $permissionChecker;

    /** @var LoggerInterface */
    private $logger;

    /** @var AssetMimeTypeResolverInterface */
    private $mimeTypeResolver;

    /** @var AssetUriEncoderInterface */
    private $uriEncoder;

    /** @var ResourceUpdatedAtResolver */
    private $updatedAtResolver;

    public function __construct(
        ElasticSearch $elasticSearch,
        ResourceManagerAssetSearchQueryBuilder $queryBuilder,
        PermissionCheckerInterface $permissionChecker,
        LoggerInterface $logger,
        AssetMimeTypeResolverInterface $mimeTypeResolver,
        AssetUriEncoderInterface $uriEncoder,
        ResourceUpdatedAtResolver $updatedAtResolver
    ) {
        $this->elasticSearch = $elasticSearch;
        $this->queryBuilder = $queryBuilder;
        $this->permissionChecker = $permissionChecker;
        $this->logger = $logger;
        $this->mimeTypeResolver = $mimeTypeResolver;
        $this->uriEncoder = $uriEncoder;
        $this->updatedAtResolver = $updatedAtResolver;
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
            $searchBody['_source'] = self::SEARCH_SOURCE_FIELDS;

            $pageSize = max(1, $query->getPageSize());
            $page = max(1, $query->getPage());

            $authorizedItems = [];
            $esTotal = 0;
            $esFrom = 0;
            $scannedHits = 0;
            $scanTruncated = false;
            $batchSize = max($pageSize * self::FETCH_MULTIPLIER, self::MIN_FETCH_BATCH_SIZE);
            $requiredAuthorizedCount = $page * $pageSize;

            while (true) {
                $remainingBudget = self::MAX_SCANNED_HITS - $scannedHits;
                if ($remainingBudget <= 0) {
                    if ($esFrom < $esTotal) {
                        $scanTruncated = true;
                        $this->logger->warning(sprintf(
                            'Asset indexed search scan truncated after %d hits (index total %d).',
                            $scannedHits,
                            $esTotal
                        ));
                    }
                    break;
                }

                $requestSize = min($batchSize, $remainingBudget);
                $searchBody['from'] = $esFrom;
                $searchBody['size'] = $requestSize;

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

                    $indexedMime = trim($this->stringifyHitValue($hit['mime_type'] ?? ''));
                    $mimeForFilter = $indexedMime;
                    if ($mimeForFilter === '' && $this->hasActiveMimeFilter($query->getFilter()) && $uri !== '') {
                        $mimeForFilter = trim($this->mimeTypeResolver->resolve($uri));
                    }
                    if (!$this->matchesMimeFilter($mimeForFilter, $query->getFilter())) {
                        continue;
                    }

                    $authorizedItems[] = $this->mapHit($hit, $mimeForFilter);
                }

                $esFrom += $requestSize;

                if ($esFrom >= $esTotal) {
                    break;
                }

                if (
                    $page > 1
                    && count($authorizedItems) >= $requiredAuthorizedCount
                    && $esFrom < $esTotal
                ) {
                    $scanTruncated = true;
                    break;
                }

                if ($scannedHits >= self::MAX_SCANNED_HITS && $esFrom < $esTotal) {
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
            $normalizedPage = max(1, $page);
            if (!$scanTruncated && $esFrom >= $esTotal) {
                $maxPage = max(1, (int)ceil($total / $pageSize) ?: 1);
                $normalizedPage = min($normalizedPage, $maxPage);
            }
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

        $indexedLocation = $this->resolveIndexedLocationFromParentLink($search->getParentLink());
        if ($indexedLocation !== '') {
            return $indexedLocation;
        }

        $scopeLabel = trim((string)($tree['label'] ?? ''));
        if ($scopeLabel !== '') {
            return $scopeLabel;
        }

        return trim((string)($tree['path'] ?? $search->getParentLink()));
    }

    /**
     * Matches assets index `location` (class labels joined by `/`, see IndexDocumentBuilder).
     */
    private function resolveIndexedLocationFromParentLink(string $parentLink): string
    {
        $parentLink = trim($parentLink);
        if ($parentLink === '' || $parentLink === '/') {
            return '';
        }

        $classUri = $parentLink;
        if (strpos($parentLink, MediaSource::SCHEME_NAME) === 0) {
            $classUri = \tao_helpers_Uri::decode(substr($parentLink, strlen(MediaSource::SCHEME_NAME)));
        } elseif (!preg_match('#^https?://#i', $parentLink)) {
            $classUri = \tao_helpers_Uri::decode($parentLink);
        }

        try {
            $class = new \core_kernel_classes_Class($classUri);
        } catch (Exception $exception) {
            return '';
        }

        if (!$class->exists()) {
            return '';
        }

        $labels = [$class->getLabel()];
        foreach ($class->getParentClasses(true) as $parentClass) {
            if ($this->isIndexedLocationRootClass($parentClass->getUri())) {
                break;
            }
            $labels[] = $parentClass->getLabel();
        }

        if ($labels === []) {
            return '';
        }

        return implode('/', array_reverse($labels));
    }

    private function isIndexedLocationRootClass(string $classUri): bool
    {
        return in_array($classUri, self::INDEXED_LOCATION_ROOT_CLASS_URIS, true);
    }

    /**
     * @param array<string, mixed> $hit
     * @return array<string, mixed>
     */
    private function mapHit(array $hit, string $prefilledMime = ''): array
    {
        $label = $this->stringifyHitValue($hit['label'] ?? '');
        $uri = (string)($hit['id'] ?? '');
        $mime = $prefilledMime !== ''
            ? $prefilledMime
            : trim($this->stringifyHitValue($hit['mime_type'] ?? ''));
        // Do not fall back to indexed `type` (often an ontology URI array).
        if ($mime === '' && $uri !== '') {
            $mime = $this->mimeTypeResolver->resolve($uri);
        }

        return [
            'uri' => $this->toMediaBrowserUri($uri),
            'label' => $label,
            'name' => $label,
            'mime' => $mime,
            'location' => (string)($hit['location'] ?? ''),
            'updatedAt' => $this->updatedAtResolver->resolve($hit['updated_at'] ?? null, $uri),
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

        return MediaSource::SCHEME_NAME . $this->uriEncoder->encode($resourceUri);
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
        if (!$this->hasActiveMimeFilter($allowedMimes)) {
            return true;
        }

        $normalizedAllowed = array_values(array_filter($allowedMimes, static function ($value): bool {
            return is_string($value) && $value !== '';
        }));

        return $mime !== '' && in_array($mime, $normalizedAllowed, true);
    }

    /**
     * @param array<int, mixed> $allowedMimes
     */
    private function hasActiveMimeFilter(array $allowedMimes): bool
    {
        foreach ($allowedMimes as $value) {
            if (is_string($value) && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
