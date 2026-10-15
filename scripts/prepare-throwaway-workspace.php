<?php
// Creates the "Agent experiment" workspace and stages the changes the throwaway
// step asks the agent for: a new field on blog, a site name change and the
// Projects page unpublished. Idempotent.
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\workspaces\Entity\Workspace;
$label = $extra[0] ?? 'Agent experiment';
$existing = \Drupal::entityTypeManager()->getStorage('workspace')->loadByProperties(['label' => $label]);
$ws = $existing ? current($existing) : Workspace::create(['id' => 'agent_experiment', 'label' => $label]);
if (!$existing) { $ws->save(); }
\Drupal::service('workspaces.manager')->executeInWorkspace($ws->id(), function () {
  if (!FieldStorageConfig::loadByName('node', 'field_experiment')) {
    FieldStorageConfig::create(['field_name' => 'field_experiment', 'entity_type' => 'node', 'type' => 'string'])->save();
  }
  if (!FieldConfig::loadByName('node', 'blog', 'field_experiment')) {
    FieldConfig::create(['field_name' => 'field_experiment', 'entity_type' => 'node', 'bundle' => 'blog', 'label' => 'Experiment'])->save();
  }
  \Drupal::configFactory()->getEditable('system.site')->set('name', 'Reef Rescue HQ')->save();
  $pages = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'page', 'title' => 'Projects']);
  foreach ($pages as $page) { $page->setUnpublished()->setNewRevision(); $page->save(); }
  echo "staged: field_experiment, site name, ", count($pages), " page(s) unpublished\n";
});
echo "workspace {$ws->id()} ready\n";
