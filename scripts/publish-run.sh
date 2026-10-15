#!/bin/sh
# Publishes a workspace from the browser and checks Live afterwards.
set -eu
cd "$(dirname "$0")/.."
ddev exec 'for s in $(curl -s http://chrome:4444/status | python3 -c "import sys,json; d=json.load(sys.stdin); print(\" \".join(sl[\"session\"][\"sessionId\"] for n in d[\"value\"][\"nodes\"] for sl in n[\"slots\"] if sl.get(\"session\")))"); do curl -s -X DELETE http://chrome:4444/session/$s >/dev/null; done'
rm -f demo-shots/publish-*.png
LINK=$(ddev drush uli --no-browser | tail -1)
ddev exec php -d memory_limit=1G scripts/publish-run.php "$LINK" "${1:-Summer update}" ${2:+"$2"}
