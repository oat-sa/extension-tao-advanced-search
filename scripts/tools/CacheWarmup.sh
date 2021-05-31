#!/bin/bash

if [ "$1" = "-h" ] || [ "$1" = "--help" ]
then
    FILE_NAME=$(basename "$0")
    echo ""
    echo " Warmup cache for indexation"
    echo ""
    echo " Example:"
    echo ""
    echo "    ./${FILE_NAME}"
    echo ""

    exit;
fi

php -d memory_limit=512M index.php "oat\taoAdvancedSearch\scripts\tools\CacheWarmup"
