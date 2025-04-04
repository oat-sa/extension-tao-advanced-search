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
 * Copyright (c) 2025 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\SearchEngine;

class AggregationQuery
{
    private Query $query;
    private array $aggregations;
    private array $terms;

    public function __construct(Query $query, array $aggregations, array $terms)
    {
        $this->query = $query;
        $this->aggregations = $aggregations;
        $this->terms = $terms;
    }

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getTerms(): array
    {
        return $this->terms;
    }
}
