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

namespace oat\taoAdvancedSearch\model\SearchEngine\Contract;

use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\TaoOntology;
use oat\taoResultServer\models\classes\ResultService;
use Iterator;

interface IndexerInterface
{
    public const ITEMS_INDEX = 'items';
    public const TESTS_INDEX = 'tests';
    public const TEST_TAKERS_INDEX = 'test-takers';
    public const DELIVERIES_INDEX = 'deliveries';
    public const DELIVERY_RESULTS_INDEX = 'delivery-results';
    public const GROUPS_INDEX = 'groups';
    public const ASSETS_INDEX = 'assets';
    public const PROPERTY_LIST = 'property-list';
    public const UNCLASSIFIEDS_DOCUMENTS_INDEX = 'unclassifieds';

    public const MEDIA_CLASS_URI = 'http://www.tao.lu/Ontologies/TAOMedia.rdf#Media';

    public const AVAILABLE_INDEXES = [
        ResultService::DELIVERY_RESULT_CLASS_URI => self::DELIVERY_RESULTS_INDEX,
        TaoOntology::CLASS_URI_ASSEMBLED_DELIVERY => self::DELIVERIES_INDEX,
        TaoOntology::CLASS_URI_DELIVERY => self::DELIVERIES_INDEX,
        TaoOntology::CLASS_URI_GROUP => self::GROUPS_INDEX,
        TaoOntology::CLASS_URI_ITEM => self::ITEMS_INDEX,
        TaoOntology::CLASS_URI_RESULT => self::DELIVERIES_INDEX,
        TaoOntology::CLASS_URI_SUBJECT => self::TEST_TAKERS_INDEX,
        TaoOntology::CLASS_URI_TEST => self::TESTS_INDEX,
        self::MEDIA_CLASS_URI => self::ASSETS_INDEX,
        self::PROPERTY_LIST => self::PROPERTY_LIST
    ];

    public const INDEXES_WITH_ACCESS_CONTROL = [
        self::ITEMS_INDEX,
        self::TESTS_INDEX,
        self::TEST_TAKERS_INDEX
    ];

    public function getIndexNameByDocument(IndexDocument $document): string;

    public function buildIndex(Iterator $documents): int;

    public function deleteDocument($id): bool;

    public function searchResourceByIds($ids = []): array;
}
