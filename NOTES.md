# Notes

What the build contains, the patches it carries, known issues, and the helper scripts. Setup
is in [README.md](README.md); the guided tour is in [WALKTHROUGH.md](WALKTHROUGH.md).

## What the build is

- Drupal CMS 2.2.5 project template, core **11.4.10**, **Haven** 1.1.1 site template
  (non-profit: 9 nodes over blog/project/person/page, Canvas pages, media, menus; the blog
  posts are nodes 2 to 4). Gin admin theme, Navigation, Canvas, trash, pathauto, redirect,
  search_api, webform.
- **Content moderation is uninstalled** by the build. The review step is a person looking at
  the workspace's tracked changes and the audit trail, then publishing; moderation states on
  every node save in a workspace would add risk for no gain. Nodes are plain
  published/unpublished.
- Extra modules: `workspaces`, `workspaces_ui`, `wse` (simplified toolbar switcher on, so the
  switcher lives in the Navigation bar; workspace IDs become UUIDs), `workspace_config`
  1.0.0-beta1, `pal` + `provenance` 1.0.0-alpha1, the `drupal_cms_ai` recipe (ai 1.5,
  ai_agents 1.3.x, canvas_ai, chatbot, Anthropic + amazee.ai providers), `tool` +
  `tool_ai_connector` + `tool_belt` 1.0.x (workspace, content, entity tool sets),
  `canvas_tools` beta2.
- The Drupal CMS assistant agent has the workspace, content and Canvas tools, a loop cap of
  15, and an added instruction: every change happens inside a workspace, never publish
  ([scripts/configure-agent.php](scripts/configure-agent.php)). Chat runs on Claude Sonnet 5
  ([scripts/configure-anthropic.php](scripts/configure-anthropic.php)).
- Site settings the build changes: pathauto `update_action = 0` ("leave the old alias
  intact") and `page_cache` uninstalled. Both are explained under known issues.

Contrib projects that need a patch run on their dev branch, the branch the patch's MR
targets, so the MR diff applies as is: `redirect` 1.x-dev, `search_api` 1.x-dev, `tool_belt`
1.0.x-dev, `ai_agents` 1.3.x-dev. `wse` is on 3.0.x-dev for
[#3622082](https://www.drupal.org/i/3622082) (Save on a new workspace set `publish_on` to
now), which is not in 3.0.0-alpha5. Everything else, core included, is a drupal.org release.
`composer.lock` pins every dev branch to a commit.

## Patches

Every fix below is filed upstream. The `patches/` files are pinned copies of the posted MR
diffs. When an MR is merged, drop its patch (and move the project back to a release once one
includes the fix), then `ddev composer update <project> && ddev composer patches-relock`.

Core is the exception to the dev-branch rule: its MR targets `main` (12.x), and Drupal CMS 2.2
needs core 11.4, so core stays on the 11.4.10 release with the runtime part of the MR.

| Target | Fix | Upstream | Where |
| --- | --- | --- | --- |
| core 11.4.10 | `installFieldableEntityType()` overwrote hook-provided base field providers (trash's `deleted` field on node got provider `node`) | [#3624771](https://www.drupal.org/i/3624771), MR !17205 open | [`patches/core-keep-base-field-provider-on-entity-type-install.patch`](patches/core-keep-base-field-provider-on-entity-type-install.patch) (runtime change only) |
| search_api 1.x-dev | `ReverseEntityReferences` type-hints the concrete field manager, which breaks with workspace_config's decorator | [#3624776](https://www.drupal.org/i/3624776), MR !360 open | [`patches/search_api-entity-field-manager-interface.patch`](patches/search_api-entity-field-manager-interface.patch) |
| tool_belt 1.0.x-dev | aliases set with `field_set_value` lost to pathauto | [#3616166](https://www.drupal.org/i/3616166), MR !35 open | [`patches/tool_belt-manual-alias-turns-pathauto-off.patch`](patches/tool_belt-manual-alias-turns-pathauto-off.patch) |
| ai_agents 1.3.x-dev | display tool on a field not in the display | [#3586082](https://www.drupal.org/i/3586082), MR !361 open | [`patches/ai_agents-display-form-default-formatter.patch`](patches/ai_agents-display-form-default-formatter.patch) |
| redirect 1.x-dev | redirects are not revisionable, so they cannot be saved in a workspace (title changes create redirects) | [#3313540](https://www.drupal.org/i/3313540), MR !138 open | [`patches/redirect-revisionable-workspace-support.patch`](patches/redirect-revisionable-workspace-support.patch) |

## Known issues

- **Haven renders nodes through Canvas content templates**, so a field placed on the core
  view display never shows on the page. Showing a field means adding a component to
  `node.blog.full` bound to the field (`canvas_add_component` + `canvas_bind_component_props`
  + `canvas_publish_auto_saves`). In a workspace the template change is staged as workspace
  config, Live keeps its tree, and deleting the workspace drops it.
- **An integer field cannot be bound**: Haven's components only have string props, and the
  binding tool matches shapes. With an integer Reading time the assistant looped until the
  request died. Hence a plain text field in the walkthrough, and the loop cap of 15.
- **Canvas auto-saves are not workspace-scoped**: the auto-save store is shared, so a draft
  left in one workspace shows up (and blocks "publish auto-saves" with "content is being
  edited in workspace X") in another. Keep Canvas drafts to one workspace, or discard them.
- **PAL records the agent run, not each write**: one `ai_agent_run` activity per chat turn
  with every touched entity as a participant, all tool calls, LLM calls and tokens. The
  workspace dimension is the workspace active when the turn started, so the turn that
  creates the workspace files under Live. Filter by agent, or open the run from the
  workspace's participant list.
- **Two workspaces cannot stage the same field storage**: workspace_config refuses the second
  one ("would share one set of database tables"). The field agent throws, so the chat shows
  a generic error instead of the reason. Use distinct field names per workspace.
- **Alias changes and pathauto**: through the node's `path` field, pathauto would silently
  regenerate the alias on save; the tool_belt patch marks an alias set by the agent as
  manual. Publishing re-saves the node on Live, and pathauto's default "update the alias"
  setting would regenerate the old alias and make the redirect module delete the redirect;
  the build sets "leave the old alias intact".
- **Redirect appears a moment after publish**: the old URL can answer 404 right after the
  publish form and 301 a few seconds later. Not root-caused.
- **The internal page cache keeps 404s**: a URL viewed anonymously on Live before its
  workspace is published would stay a 404 for 15 minutes after publish. The build uninstalls
  `page_cache`; the dynamic page cache stays.
- **Theme warnings** "Theme hook field not found" are logged inside requests that switch
  workspace mid-way (create-and-switch, publish); pages render fine afterwards.
- **Tool IDs**: canvas_tools tools are `tool:canvas_*` in the AI function-call manager; a
  wrong ID makes every chat turn fail with a generic error.
- `webform_submission` is neither supported nor ignored in workspaces; irrelevant unless a
  form gets submitted while a workspace is active.

## Scripts

All in [scripts/](scripts/), run from the project root.

| Script | What it does |
| --- | --- |
| `build.sh` | Full rebuild, saves the `fresh-build` snapshot. |
| `configure-anthropic.php`, `configure-agent.php` | AI provider and assistant setup (run by the build). |
| `chat.sh <thread> "<message>"` | Sends one chat message as admin through the chatbot's HTTP API; logs in `chat-logs/`. |
| `run-agent-steps.sh` | The walkthrough's three main prompts through `chat.sh`, a summary of what got staged, then `publish-run.sh`. Uses your API key. |
| `demo-run.sh` | The non-AI steps in headless Chrome (the `chrome` DDEV service), screenshots in `demo-shots/`. |
| `publish-run.sh [label] [old url]` | Publishes a workspace from the browser and checks Live. |
| `discard-run.sh` | Prepares the "Agent experiment" workspace via drush, deletes it from the browser, checks Live. |
| `stage-field.php "<label>"` | Stages the walkthrough's Reading time field in a workspace without the agent. |
| `prepare-demo-workspace.php`, `prepare-throwaway-workspace.php`, `delete-workspaces.php` | Drush helpers for the browser runs. |
| `verify-workspace-field.php`, `verify-publish.php`, `verify-redirect.php`, `canvas-tools-check.php` | Drush checks of field staging, publishing, redirects and Canvas pages in a workspace. |

## Design choices

- Drupal CMS instead of plain core: it brings the site, the assistant and the agents. Plain
  core would need Canvas, the AI modules and a site template wired by hand.
- Haven as the site template: first-party, light dependencies, and enough content to show the
  idea. Switching templates is one line in `scripts/build.sh` plus a `drupal/<template>`
  requirement in composer.json.
- No content moderation as the review gate: WSE is installed for its navigation switcher and
  its workspace status, and the review is a person looking at the workspace's tracked
  changes and the audit trail, then publishing.

## Open ideas

- Seed a few historic PAL activities so the report has content before the first agent run.
- Decide whether the agent may publish (currently forbidden by its instructions and by not
  having the tool).
