#!/bin/bash

if [ "$1" = "-h" ] || [ "$1" = "--help" ]
then
    FILE_NAME=$(basename "$0")
    echo ""
    echo " Garbage collection. Usage:"
    echo ""
    echo "    OFFSET: Offset to start index. Default: 0"
    echo "    LIMIT: Limit of resources to be found. Default: 100"
    echo ""
    echo " Example:"
    echo ""
    echo "    ./${FILE_NAME} <<OFFSET>> <<LIMIT>>"
    echo ""

    exit;
fi

CURRENT_DIR=$(dirname "$0");
OFFSET=${1:-0} # Default will be 0
LIMIT=${2:-100} # Default will be 100

php -d memory_limit=512M index.php "\oat\taoAdvancedSearch\scripts\tools\ResourceIndexGarbageCollector" -i 'items' -l $LIMIT -o $OFFSET
php -d memory_limit=512M index.php "\oat\taoAdvancedSearch\scripts\tools\ResourceIndexGarbageCollector" -i 'assets' -l $LIMIT -o $OFFSET
php -d memory_limit=512M index.php "\oat\taoAdvancedSearch\scripts\tools\ResourceIndexGarbageCollector" -i 'deliveries' -l $LIMIT -o $OFFSET
php -d memory_limit=512M index.php "\oat\taoAdvancedSearch\scripts\tools\ResourceIndexGarbageCollector" -i 'groups' -l $LIMIT -o $OFFSET
php -d memory_limit=512M index.php "\oat\taoAdvancedSearch\scripts\tools\ResourceIndexGarbageCollector" -i 'test-takers' -l $LIMIT -o $OFFSET
php -d memory_limit=512M index.php "\oat\taoAdvancedSearch\scripts\tools\ResourceIndexGarbageCollector" -i 'tests' -l $LIMIT -o $OFFSET
php -d memory_limit=512M index.php "\oat\taoAdvancedSearch\scripts\tools\ResourceIndexGarbageCollector" -i 'property-list' -l $LIMIT -o $OFFSET
