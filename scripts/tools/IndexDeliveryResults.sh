#!/bin/bash

if [ "$1" = "-h" ] || [ "$1" = "--help" ]
then
    FILE_NAME=$(basename "$0")
    echo ""
    echo " Populate delivery results indexes. Usage:"
    echo ""
    echo "    CHUNK_SIZE: Integer size of items to be processed on each migration task. Default: 100"
    echo ""
    echo " Example:"
    echo ""
    echo "    ./${FILE_NAME} <<CHUNK_SIZE>>"
    echo ""

    exit;
fi

CHUNK_SIZE=${1:=100} # Default will be 100

php -d memory_limit=512M index.php "\oat\tao\scripts\tools\MigrationAction" \
-c "$CHUNK_SIZE" \
-cp "start=0" \
-t "oat\taoAdvancedSearch\model\DeliveryResult\Service\DeliveryResultMigrationTask" -rp
