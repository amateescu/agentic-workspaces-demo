# Walkthrough

A guided tour of the demo, about 20 minutes, most of it waiting for the agent. Set up the
site first ([README.md](README.md)); background and known issues are in [NOTES.md](NOTES.md).

Paste each prompt exactly as written, straight quotes included: they are tested, and
paraphrased prompts tend to run out of agent steps. The site is at
https://agentic-workspaces-demo.ddev.site, login admin / admin.

## Before you start

1. Start from a fresh site: `ddev snapshot restore fresh-build && ddev drush cr`. Get a login
   link with `ddev drush uli`.
2. Main browser window: log in as admin.
3. A private window that stays logged out, so it always shows Live. Open:
   - https://agentic-workspaces-demo.ddev.site/
   - https://agentic-workspaces-demo.ddev.site/node/3 (the "Six months without a car" blog
     post; it redirects to the post's URL alias)
4. Optional, to look at the database: `ddev phpmyadmin`.

The chat is the **Drupal Agent** button in the admin toolbar, on any admin page. Its dropdown
has **Clear History**, which starts a new conversation. Each reply takes 40 to 70 seconds.

## 1. Look around Live

In the main window:

- https://agentic-workspaces-demo.ddev.site/
- https://agentic-workspaces-demo.ddev.site/blog
- https://agentic-workspaces-demo.ddev.site/node/3
- https://agentic-workspaces-demo.ddev.site/admin/reports/activity-log, the PAL report.

## 2. One task, one workspace

Go to https://agentic-workspaces-demo.ddev.site/admin/content and open the chat.

Prompt 1:

```
In a new workspace called "Summer update", add a "Reading time" plain text field to blog posts and show it on blog post pages next to the byline, using a badge component bound to the field in the blog content template. Do it now, no need to ask me for confirmation.
```

The agent creates the workspace and switches to it; the toolbar switcher now shows
**Summer update**.

Prompt 2:

```
Now set the Reading time on the existing blog posts to "5 min read", "7 min read" and "4 min read", and write a short new blog post about the reef restoration project with a reading time of "3 min read". Go ahead without asking for confirmation.
```

Open https://agentic-workspaces-demo.ddev.site/node/3 in the main window: the post shows the
reading time badge. Refresh it in the private window: no badge, Live is unchanged.

## 3. Under the hood

- https://agentic-workspaces-demo.ddev.site/admin/structure/types/manage/blog/fields lists
  **Reading time**. Switch to **Live** in the toolbar and reload: it is gone. Switch back to
  **Summer update**.
- https://agentic-workspaces-demo.ddev.site/admin/config/workflow/workspaces, click
  **Summer update**: the tracked changes list the staged config (field storage, field,
  displays, content template) next to the three posts and the new one.

In phpMyAdmin, SQL tab, database `db`:

```
SHOW TABLES LIKE 'node%reading_time';
```

The field's two tables exist: staging the field storage created them.

```
SELECT entity_id, revision_id, field_reading_time_value FROM node__field_reading_time;
```

No rows: Live's revisions of the posts have no reading time.

```
SELECT entity_id, revision_id, field_reading_time_value FROM node_revision__field_reading_time ORDER BY entity_id, revision_id;
```

The workspace revisions of the posts hold the values.

## 4. The agent builds a page

Prompt 3, in the same conversation:

```
Still in the Summer update workspace: build a new Canvas page called "Reef restoration" with a hero component and a text section that summarises the project, publish its draft, and then change the URL alias of the new reef restoration blog post to /blog/reef-restoration. Go ahead without asking for confirmation.
```

In the main window (inside the workspace):

- https://agentic-workspaces-demo.ddev.site/reef-restoration
- https://agentic-workspaces-demo.ddev.site/blog/reef-restoration
- https://agentic-workspaces-demo.ddev.site/admin/config/search/redirect shows the staged
  redirect from the post's old URL.

Keep these URLs out of the private window until step 7.

## 5. A throwaway workspace

In the chat dropdown, click **Clear History**.

Prompt 4:

```
In a new workspace called "Agent experiment", add a "Read time" plain text field (machine name field_read_time) to blog posts and show it on blog post pages next to the byline, using a badge component bound to the field in the blog content template. Do it now, no need to ask me for confirmation.
```

It uses a different field name because two workspaces cannot stage the same field.

- https://agentic-workspaces-demo.ddev.site/admin/config/workflow/workspaces, click
  **Agent experiment**: its tracked changes.
- https://agentic-workspaces-demo.ddev.site/admin/reports/activity-log, set **Workspace** to
  **Agent experiment**: the agent's runs.
- Delete it: https://agentic-workspaces-demo.ddev.site/admin/config/workflow/workspaces,
  **Agent experiment** row, operations, **Delete**, confirm.
- Switch back to **Summer update** in the toolbar.
- In the private window, refresh the blog post: nothing changed.

## 6. The audit trail

https://agentic-workspaces-demo.ddev.site/admin/reports/activity-log, set **Workspace** to
**Summer update**.

Each agent step is its own "AI agent run" row, and one chat turn is one job. Open a run: the
request, tool calls, tokens, participants, "part of a job of N activities". Open
**PROV document** on the same page for the W3C PROV version.

## 7. Review and publish

1. https://agentic-workspaces-demo.ddev.site/admin/config/workflow/workspaces, click
   **Summer update**: the tracked changes.
2. https://agentic-workspaces-demo.ddev.site/admin/content, open the new reef post, **Edit**,
   change one sentence, **Save**.
3. https://agentic-workspaces-demo.ddev.site/admin/config/workflow/workspaces, **Summer update**
   row, operations, **Publish content**, confirm.
4. In the private window, refresh or open:
   - https://agentic-workspaces-demo.ddev.site/node/3 (badge "7 min read")
   - https://agentic-workspaces-demo.ddev.site/reef-restoration
   - https://agentic-workspaces-demo.ddev.site/blog/reef-restoration
   - the post's old URL (from the redirect list in step 4) now redirects to the new one. It
     can take a few seconds after publishing.
5. https://agentic-workspaces-demo.ddev.site/admin/reports/activity-log: **Workspace
   published** is at the top.

## If something goes wrong

- A reply says it could not finish "within the allowed number of processing steps": the
  prompt was probably paraphrased. Reset the site and use the exact text above.
- The agent asks for confirmation instead of acting: reply `Yes, go ahead.`
- The agent gets stuck on the field in step 2: run
  `ddev drush scr scripts/stage-field.php -- "Summer update"` to stage the field without the
  agent (and the workspace, if it does not exist yet), switch to **Summer update** in the
  toolbar, then continue with prompt 2. The badge is not added this way, so the post page
  does not show the reading time; the field and its values are still there to explore.
