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

namespace oat\taoAdvancedSearch\model\SearchEngine\Service;

/**
 * Loads index definitions from config/index.conf.php (or a test override).
 */
class IndexConfigurationProvider
{
    /** @var string|null */
    private $indexFile;

    /** @var array<int|string, array>|null */
    private $indexes;

    /**
     * @param array<int|string, array>|null $indexes optional in-memory defs (tests)
     */
    public function __construct(?array $indexes = null)
    {
        $this->indexes = $indexes;
    }

    public function setIndexFile(string $indexFile): void
    {
        $this->indexFile = $indexFile;
        $this->indexes = null;
    }

    /**
     * @return array<int|string, array>
     */
    public function getIndexes(): array
    {
        if ($this->indexes !== null) {
            return $this->indexes;
        }

        $indexFile = $this->getIndexFile();

        return is_readable($indexFile) ? require $indexFile : [];
    }

    public function getIndexFile(): string
    {
        return $this->indexFile ?? dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'index.conf.php';
    }
}
