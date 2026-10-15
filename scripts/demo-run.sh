#!/bin/sh
# Runs the browser demo. Frees stale Selenium sessions, prepares a workspace with
# the Reading time field staged (via drush, the API path an agent uses), then the
# browser script activates it and drives review -> isolation -> PAL -> publish.
set -eu
cd "$(dirname "$0")/.."
ddev exec 'for s in $(curl -s http://chrome:4444/status | python3 -c "import sys,json; d=json.load(sys.stdin); print(\" \".join(sl[\"session\"][\"sessionId\"] for n in d[\"value\"][\"nodes\"] for sl in n[\"slots\"] if sl.get(\"session\")))"); do curl -s -X DELETE http://chrome:4444/session/$s >/dev/null; done'
rm -f demo-shots/*.png
LABEL="Reading time demo"
ddev exec php -d memory_limit=1G vendor/bin/drush.php scr scripts/delete-workspaces.php >/dev/null
ddev exec php -d memory_limit=1G vendor/bin/drush.php scr scripts/prepare-demo-workspace.php -- "$LABEL"
NID=$(ddev drush sqlq "SELECT MIN(nid) FROM node_field_data WHERE type = 'blog'")
LINK=$(ddev drush uli --no-browser | tail -1)
ddev exec php -d memory_limit=1G scripts/demo-run.php "$LINK" "$LABEL" "$NID"
