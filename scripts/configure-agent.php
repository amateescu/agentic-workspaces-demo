<?php

/**
 * @file
 * Gives the Drupal CMS assistant agent workspace and content tools, and tells
 * it to work inside a workspace. Idempotent.
 *
 *   ddev drush scr scripts/configure-agent.php
 */

$agent = \Drupal::entityTypeManager()->getStorage('ai_agent')->load('drupal_cms_assistant');
$tools = $agent->get('tools');
$settings = $agent->get('tool_settings');
$add = [
  'tool:tool_belt:workspace_get_active',
  'tool:tool_belt:workspace_create_and_switch',
  'tool:tool_belt:workspace_switch',
  'tool:tool_belt:workspace_tracked_content',
  'tool:tool_belt:entity_list',
  'tool:tool_belt:entity_load_by_id',
  'tool:tool_belt:entity_field_value_definitions',
  'tool:tool_belt:entity_stub',
  'tool:tool_belt:field_set_value',
  'tool:tool_belt:entity_save',
  'tool:tool_belt:entity_revision_add',
  'tool:canvas_list_components',
  'tool:canvas_get_component_schema',
  'tool:canvas_create_page',
  'tool:canvas_add_component',
  'tool:canvas_update_component_props',
  'tool:canvas_get_page_tree',
  'tool:canvas_publish_auto_saves',
  'tool:canvas_list_targets',
  'tool:canvas_bind_component_props',
  'tool:canvas_list_auto_saves',
];
foreach ($add as $id) {
  $tools[$id] = TRUE;
  $settings[$id] = $settings[$id] ?? ['return_directly' => 0];
}
$agent->set('tools', $tools);
$agent->set('max_loops', 15);
$agent->set('tool_settings', $settings);

$marker = '### Workspaces ###';
$prompt = $agent->get('system_prompt');
// Replace the block from the marker on, so edits here reach an existing agent.
if (($pos = strpos($prompt, $marker)) !== FALSE) {
  $prompt = rtrim(substr($prompt, 0, $pos));
}
$prompt .= "\r\n\r\n$marker\r\n"
  . "Every change you make, to content or to site structure, must happen inside a workspace, never on the live site. "
  . "Before you create or change anything, check the active workspace. If the live site is active, "
  . "create a workspace named after the task and switch to it, then do the work. "
  . "Tell the user which workspace you are working in. "
  . "Never publish a workspace: a person reviews the changes and publishes them.\r\n"
  . "To write content, stub the entity, set its field values, then save it. "
  . "To build a Canvas page, create the page, add components and set their props, then publish the page's auto-saves; "
  . "that publishes the draft into the workspace, not to the live site. "
  . "Blog posts and pages are rendered by Canvas content templates (for example node.blog.full), not by the core view display: to show a field on a page, add a component to that content template, bind its prop to the field with canvas_bind_component_props, then publish the template's auto-save. "
  . "To change a page's URL alias, set the entity's path field (alias) and save it; that alias is then kept as a manual alias.";
$agent->set('system_prompt', $prompt);
$agent->save();
echo "drupal_cms_assistant now has " . count($tools) . " tools.\n";
