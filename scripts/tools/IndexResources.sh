#!/bin/bash
set -e

if [ "$1" = "-h" ] || [ "$1" = "--help" ]
then
    FILE_NAME=$(basename "$0")
    echo ""
    echo " Populate resource indexes (Items, tests, etc.). Usage:"
    echo ""
    echo "    BATCH_SIZE_LIMIT: Integer size of documents sent in each batch to index resources. Default: 50"
    echo "    LIMIT: Limit of resources per class to be found. Default: 100"
    echo ""
    echo " Example:"
    echo ""
    echo "    ./${FILE_NAME} <<BATCH_SIZE_LIMIT>> <<LIMIT>>"
    echo ""

    exit;
fi

EXPORTER_LOCK_FILE="/tmp/.export.lock"
BATCH_SIZE_LIMIT=${1:-50} # Default will be 50
LIMIT=${2:-100} # Default will be 100
OFFSET=0;
CLASS=

rm -f $EXPORTER_LOCK_FILE && touch $EXPORTER_LOCK_FILE

while [ "$(awk 'FNR==2' ${EXPORTER_LOCK_FILE})" != 'FINISHED' ]; do
  LOCK_CLASS=$(awk 'FNR==1' ${EXPORTER_LOCK_FILE})

  if [ "$CLASS" != "$LOCK_CLASS" ]; then
    CLASS=$LOCK_CLASS
    OFFSET=0;
  fi

  php -d memory_limit=512M index.php "oat\tao\scripts\tools\index\IndexPopulator" \
  --indexBatchSize $BATCH_SIZE_LIMIT \
  --limit $LIMIT \
  --offset $OFFSET \
  --lock $EXPORTER_LOCK_FILE \
  --class $CLASS

  # Necessary, cause CLASS is empty first time, since there is nothing in the lock file
  if [ -z "$CLASS" ]; then
     CLASS=$(awk 'FNR==1' ${EXPORTER_LOCK_FILE})
  fi

  OFFSET=$(($OFFSET + $LIMIT))
done
