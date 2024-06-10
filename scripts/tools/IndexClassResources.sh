#!/bin/sh
set -e

if [ "$1" = "-h" ] || [ "$1" = "--help" ]
then
    FILE_NAME=$(basename "$0")
    echo ""
    echo " Populate resource indexes (Items, tests, etc.). Usage:"
    echo ""
    echo "    CLASS_URI: The class URI to index"
    echo "    OFFSET: From where to start indexing resources. Default: 0"
    echo "    LIMIT: Limit of resources to index per time. Default: 100"
    echo ""
    echo " Example:"
    echo ""
    echo "    ./${FILE_NAME} <<CLASS_URI>> <<OFFSET>> <<LIMIT>>"
    echo ""

    exit;
fi

CLASS_URI=${1:-"http://www.tao.lu/Ontologies/TAOItem.rdf#Item"} # Default will be http://www.tao.lu/Ontologies/TAOItem.rdf#Item
OFFSET=${2:-0} # Default will be 0
LIMIT=${3:-100} # Default will be 100

PARAMETERS="start=${OFFSET}&classUri=${CLASS_URI}"

php -d memory_limit=512M index.php "\oat\tao\scripts\tools\MigrationAction" \
-c $LIMIT \
-cp $PARAMETERS \
-t "oat\taoAdvancedSearch\model\Resource\Task\ResourceMigrationTask" -rp