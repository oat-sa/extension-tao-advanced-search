#!/bin/bash
set -e

EXPORTER_LOCK_FILE="/tmp/.export.lock"
CHUNK_SIZE=100
BATCH_SIZE_LIMIT=${1:-100} # if $1 is not provided, default is 100
LIMIT=100
OFFSET=0;
CLASS=

rm -f $EXPORTER_LOCK_FILE && touch $EXPORTER_LOCK_FILE

#
# Index delivery results
#
php -d memory_limit=512M index.php "\oat\tao\scripts\tools\MigrationAction" \
-c $CHUNK_SIZE \
-cp "start=0" \
-t "oat\taoAdvancedSearch\model\DeliveryResult\Service\DeliveryResultMigrationTask" -rp

#
# Index class metadata
#
php -d memory_limit=512M index.php "\oat\tao\scripts\tools\MigrationAction" \
-c $CHUNK_SIZE \
-cp "start=0" \
-t "oat\taoAdvancedSearch\model\Metadata\Task\MetadataResultMigrationTask" -rp

#
# Index RDF classes
#
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

  OFFSET=$(($OFFSET + $LIMIT))
done
