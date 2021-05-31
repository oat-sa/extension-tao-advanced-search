#!/bin/bash

if [ "$1" = "-h" ] || [ "$1" = "--help" ]
then
    FILE_NAME=$(basename "$0")
    echo ""
    echo " Populate all indexes. Usage:"
    echo ""
    echo "    BATCH_SIZE_LIMIT: Integer size of documents sent in each batch to index resources. Default: 50"
    echo "    CHUNK_SIZE: Integer size of items to be processed on each migration task. Default: 100"
    echo "    LIMIT: Limit of resources per class to be found. Default: 100"
    echo ""
    echo " Example:"
    echo ""
    echo "    ./${FILE_NAME} <<BATCH_SIZE_LIMIT>> <<CHUNK_SIZE>> <<LIMIT>>"
    echo ""

    exit;
fi

CURRENT_DIR=$(dirname "$0");
BATCH_SIZE_LIMIT=${1:-50} # Default will be 50
CHUNK_SIZE=${2:-100} # Default will be 100
LIMIT=${3:-100} # Default will be 100

"${CURRENT_DIR}/CacheWarmup.sh"
"${CURRENT_DIR}/IndexResources.sh" "$BATCH_SIZE_LIMIT" "$LIMIT"
"${CURRENT_DIR}/IndexClassMetadata.sh" "$CHUNK_SIZE"
"${CURRENT_DIR}/IndexDeliveryResults.sh" "$CHUNK_SIZE"
