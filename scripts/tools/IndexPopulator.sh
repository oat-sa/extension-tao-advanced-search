#!/bin/bash

if [ "$1" = "-h" ] || [ "$1" = "--help" ]
then
    FILE_NAME=$(basename "$0")
    echo ""
    echo " Populate all indexes. Usage:"
    echo ""
    echo "    OFFSET: Offset to start index. Default: 0"
    echo "    LIMIT: Limit of resources per class to be found. Default: 50"
    echo ""
    echo " Example:"
    echo ""
    echo "    ./${FILE_NAME} <<OFFSET>> <<LIMIT>>"
    echo ""

    exit;
fi

CURRENT_DIR=$(dirname "$0");
OFFSET=${1:-0} # Default will be 0
LIMIT=${2:-50} # Default will be 50

"${CURRENT_DIR}/CacheWarmup.sh"
"${CURRENT_DIR}/IndexResources.sh" "$OFFSET" "$LIMIT"
"${CURRENT_DIR}/IndexClassMetadata.sh" "$OFFSET" "$LIMIT"
"${CURRENT_DIR}/IndexDeliveryResults.sh" "$OFFSET" "$LIMIT"
