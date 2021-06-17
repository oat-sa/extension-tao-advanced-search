#!/bin/bash

if [ "$1" = "-h" ] || [ "$1" = "--help" ]
then
    FILE_NAME=$(basename "$0")
    echo ""
    echo " Populate resources indexes. Usage:"
    echo ""
    echo "    OFFSET: Integer offset. Default: 0"
    echo "    LIMIT: Integer size of items to be processed on each migration task. Default: 100"
    echo ""
    echo " Example:"
    echo ""
    echo "    ./${FILE_NAME} <<LIMIT>>"
    echo ""

    exit;
fi

OFFSET=${1:-0} # Default will be 0
LIMIT=${2:-100} # Default will be 100

php -d memory_limit=512M index.php "oat\taoAdvancedSearch\scripts\tools\IndexResourcePopulator" \
-o $OFFSET \
-l $LIMIT
