#!/bin/sh
# Deletes a workspace from the browser and checks that Live is untouched.
#
#   sh scripts/discard-run.sh                       # prepares "Agent experiment" via drush, then deletes it
#   sh scripts/discard-run.sh "<label>" "<field label>" <strings expected on the tracked-changes page>...
set -eu
cd "$(dirname "$0")/.."
ddev exec 'for s in $(curl -s http://chrome:4444/status | python3 -c "import sys,json; d=json.load(sys.stdin); print(\" \".join(sl[\"session\"][\"sessionId\"] for n in d[\"value\"][\"nodes\"] for sl in n[\"slots\"] if sl.get(\"session\")))"); do curl -s -X DELETE http://chrome:4444/session/$s >/dev/null; done'
rm -f demo-shots/discard-*.png
if [ $# -eq 0 ]; then
  ddev exec php -d memory_limit=1G vendor/bin/drush.php scr scripts/prepare-throwaway-workspace.php -- "Agent experiment"
  set -- "Agent experiment" Experiment Projects field.storage.node.field_experiment system.site
fi
LINK=$(ddev drush uli --no-browser | tail -1)
ddev exec php -d memory_limit=1G scripts/discard-run.php "$LINK" "$@"
