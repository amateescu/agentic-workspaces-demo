#!/bin/sh
# Runs the agent steps of WALKTHROUGH.md on a fresh site: the three main prompts
# through the chat API, a summary of what the agent staged, then publish in the browser.
#
#   sh scripts/run-agent-steps.sh
#
# Chat logs land in chat-logs/, screenshots in demo-shots/.
cd "$(dirname "$0")/.."
mkdir -p chat-logs
rm -f chat-logs/r.log chat-logs/cookies-r.txt
t() { timeout 660 sh scripts/chat.sh r "$1" | head -c 400; echo; grep "^--- " chat-logs/r.log | tail -1; }
t 'In a new workspace called "Summer update", add a "Reading time" plain text field to blog posts and show it on blog post pages next to the byline, using a badge component bound to the field in the blog content template. Do it now, no need to ask me for confirmation.'
t 'Now set the Reading time on the existing blog posts to "5 min read", "7 min read" and "4 min read", and write a short new blog post about the reef restoration project with a reading time of "3 min read". Go ahead without asking for confirmation.'
t 'Still in the Summer update workspace: build a new Canvas page called "Reef restoration" with a hero component and a text section that summarises the project, publish its draft, and then change the URL alias of the new reef restoration blog post to /blog/reef-restoration. Go ahead without asking for confirmation.'
ddev drush php:eval '$w=current(\Drupal::entityTypeManager()->getStorage("workspace")->loadByProperties(["label"=>"Summer update"])); \Drupal::service("workspaces.manager")->executeInWorkspace($w->id(), function () { $t=\Drupal::entityTypeManager()->getStorage("content_template")->load("node.blog.full"); echo "bound=", var_export(str_contains(json_encode($t->get("component_tree")),"reading_time"),1), "\n"; foreach (\Drupal::entityTypeManager()->getStorage("node")->loadByProperties(["type"=>"blog"]) as $n) echo $n->id(), " rt=", $n->get("field_reading_time")->value ?? "-", " ", $n->toUrl()->toString(), "\n"; foreach (\Drupal::entityTypeManager()->getStorage("redirect")->loadMultiple() as $r) echo "redirect ", $r->getSourceUrl(), " -> ", $r->getRedirectUrl()->toString(), "\n"; foreach (\Drupal::entityTypeManager()->getStorage("canvas_page")->loadByProperties(["title"=>"Reef restoration"]) as $p) echo "page ", $p->toUrl()->toString(), " comps=", count($p->get("components")), "\n"; });' 2>&1 | grep -v '^\['
OLD=$(ddev drush php:eval 'foreach (\Drupal::entityTypeManager()->getStorage("redirect")->loadMultiple() as $r) { echo "/", ltrim($r->getSourceUrl(), "/"); break; }' 2>/dev/null)
echo "old url: $OLD"
timeout 600 sh scripts/publish-run.sh "Summer update" "$OLD"
