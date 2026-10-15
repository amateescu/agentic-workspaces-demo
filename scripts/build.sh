#!/bin/sh
# Rebuilds the demo site from scratch and saves it as the `fresh-build` snapshot.
# Run from the project root:
#   sh scripts/build.sh
# Put the Anthropic key in .ddev/config.local.yaml (see README.md).
set -eu

ddev composer install --no-interaction
ddev mysql -e 'DROP DATABASE IF EXISTS db; CREATE DATABASE db;'
ddev drush site:install ../recipes/haven --yes --account-pass=admin --site-name="Haven"
ddev drush user:role:add administrator admin
# The demo does not use editorial workflows, and content moderation adds a
# moderation state to every node save in a workspace. Take it out after Haven.
ddev drush entity:delete content_moderation_state -y
ddev drush pmu scheduler_content_moderation_integration content_moderation -y
# PAL goes in on its own so its schema exists before other installs fire hooks.
ddev drush en workspaces workspaces_ui wse workspace_config tool tool_ai_connector tool_explorer tool_belt_workspace tool_belt_content tool_belt_entity canvas_tools -y
ddev drush en pal -y
# WSE replaces workspace IDs with UUIDs and adds the navigation switcher.
ddev drush cset wse.settings simplified_toolbar_switcher 1 -y
# Publishing re-saves nodes on Live; "leave the old alias intact" keeps pathauto from
# regenerating an alias the agent changed in a workspace (which would kill the redirect).
ddev drush cset pathauto.settings update_action 0 -y
# The internal page cache keeps a 404 for a URL viewed on Live before the workspace that
# adds it is published (only cron drops 4xx entries); the dynamic page cache stays on.
ddev drush pmu page_cache -y
ddev drush recipe ../recipes/drupal_cms_ai --input=drupal_cms_ai.provider=anthropic --no-interaction
# The Anthropic key comes from ANTHROPIC_API_KEY in .ddev/config.local.yaml.
ddev drush scr scripts/configure-anthropic.php
ddev drush scr scripts/configure-agent.php
ddev drush cr
ddev drush watchdog:delete all -y
# Reset to this state later with: ddev snapshot restore fresh-build && ddev drush cr
ddev snapshot --cleanup --name fresh-build --yes >/dev/null 2>&1 || true
ddev snapshot --name fresh-build
ddev drush uli
