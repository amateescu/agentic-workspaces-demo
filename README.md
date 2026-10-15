# Agentic Workspaces Demo

An AI agent changes a Drupal site, content and structure alike, strictly inside a
workspace. A person reviews the workspace's changes and the audit trail, then publishes.
Live is never touched until that publish.

The site is Drupal CMS 2.2 with the Haven site template on Drupal core 11.4, plus:

- **Workspaces** (core) and **WSE** for staging content.
- **workspace_config** for staging configuration, new fields included.
- **PAL** (Provenance Activity Log) for the audit trail: every agent run, tool call, config
  change, content edit and publish.
- **AI**, **AI Agents**, **Tool Belt** and **canvas_tools**: the Drupal CMS assistant gets
  workspace, content and Canvas tools, and is told to work in a workspace and never publish.

## What the demo shows

[WALKTHROUGH.md](WALKTHROUGH.md) takes you through it step by step, in about 20 minutes:

1. One chat prompt makes the agent create a workspace, add a "Reading time" field to blog
   posts and show it on the post pages. A second prompt fills in values and writes a new post.
2. Inside the workspace the field and the posts exist; on Live (a logged-out private window)
   nothing changed.
3. The agent builds a Canvas landing page and changes a post's URL, which stages a redirect.
4. A second, throwaway workspace with its own agent changes is reviewed and deleted. Live
   stays untouched.
5. The PAL report shows every agent run with its tool calls and token usage, and every staged
   change.
6. A person reviews the workspace, fixes a sentence by hand and publishes. Live now has the
   field, the values, the new post, the landing page and the redirect.

## Requirements

- [DDEV](https://ddev.com/get-started/) and Docker (tested with DDEV 1.25).
- An Anthropic API key from the [Anthropic Console](https://console.anthropic.com/). The
  Drupal provider calls the API directly, so a claude.ai subscription does not work. Every
  agent run is billed to that key's account.

## Set up

From the project root:

1. Put your key in `.ddev/config.local.yaml` (git ignores this file):

   ```
   printf 'web_environment:\n  - ANTHROPIC_API_KEY=%s\n' 'sk-ant-...' > .ddev/config.local.yaml
   ```

2. Start DDEV and build the site (about 5 minutes):

   ```
   ddev start && sh scripts/build.sh
   ```

   The build installs the Composer dependencies and the site, connects the assistant to
   Claude Sonnet 5, saves the result as the DDEV snapshot `fresh-build` and prints a login
   link.

The site is at https://agentic-workspaces-demo.ddev.site (`ddev launch` opens it), login
`admin` / `admin`, or get a one-time login link with `ddev drush uli`.

If you change the key later, run `ddev restart`; the site reads it on every request, so no
rebuild is needed.

## Reset between runs

Restore the snapshot the build saved (about 20 seconds):

```
ddev snapshot restore fresh-build && ddev drush cr
```

`sh scripts/build.sh` rebuilds from scratch and saves a new snapshot.

## Try it

Follow [WALKTHROUGH.md](WALKTHROUGH.md): the exact prompts to paste into the chat (the
**Drupal Agent** button in the admin toolbar), the URLs to open, and what to look for. Use
the prompts as written; paraphrased prompts tend to run out of agent steps. Each reply takes
40 to 70 seconds.

## Automated runs

- `sh scripts/run-agent-steps.sh` sends the walkthrough's three main prompts through the chat
  API, prints what the agent staged, then publishes the workspace in a headless browser. It
  uses your API key.
- `sh scripts/demo-run.sh` runs the non-AI steps in a headless browser (stage a field, edit a
  post in the workspace, check Live, PAL report, publish). Screenshots land in `demo-shots/`.
- `ddev drush scr scripts/verify-workspace-field.php` followed by
  `ddev drush scr scripts/verify-publish.php` checks field staging and publishing from drush.

## Customize

- **Model:** change `$model` in [scripts/configure-anthropic.php](scripts/configure-anthropic.php),
  then run `ddev drush scr scripts/configure-anthropic.php`.
- **Agent tools and instructions:** [scripts/configure-agent.php](scripts/configure-agent.php),
  applied the same way.
- **Site template:** one line in [scripts/build.sh](scripts/build.sh) plus the template's
  `drupal/<template>` requirement in `composer.json`.

## What is in the repo

- `composer.json` / `composer.lock`: every module version is pinned. [patches/](patches/)
  holds five fixes that are filed upstream and not merged yet.
- [scripts/](scripts/): the build, the agent setup, the automated runs and drush checks.
  [NOTES.md](NOTES.md#scripts) lists them all.
- [WALKTHROUGH.md](WALKTHROUGH.md): the guided tour with the exact prompts.
- [NOTES.md](NOTES.md): what the build contains, the patch list with upstream issues, and
  known issues.

## Known rough edges

- Two workspaces cannot stage the same new field; the second attempt fails with a generic
  chat error. The run sheet uses a different field name in the second workspace.
- A redirect published from a workspace can take a few seconds to show up on Live.
- Canvas drafts are shared across workspaces: finish or discard a Canvas draft before
  switching to another workspace.

The full list is in [NOTES.md](NOTES.md#known-issues).

## License

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).
